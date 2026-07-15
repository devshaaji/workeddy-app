<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserProfile;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Modules\IAM\Application\Services\PlatformRoleResolver;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository;
use WorkEddy\Modules\Subscription\Domain\Enums\SubscriptionStatus;
use WorkEddy\Modules\IAM\Application\DTOs\PublicRegisterRequest;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Organization;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Platform\Events\EventPublisherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WorkEddy\Platform\Logging\ILoggerFactory;

final class PublicRegisterUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository $userRepo,
        private readonly IUserProfileRepository $profileRepo,
        private readonly IRoleRepository $roleRepo,
        private readonly IOrganizationRepository $organizationRepo,
        private readonly IOrganizationMembershipRepository $membershipRepo,
        private readonly TransactionManagerInterface $tx,
        private readonly ISubscriptionRepository $subscriptionRepo,
        private readonly ISubscriptionPlanRepository $subscriptionPlans,
        private readonly IAMSettings $iamSettings,
        private readonly IAuditService $auditService,
        private readonly IAMNotificationDispatcher $notifications,
        private readonly IClock $clock,
        private readonly EventPublisherInterface $events,
        private readonly PlatformRoleResolver $platformRoleResolver,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('iam') ?? new NullLogger();
    }

    public function execute(PublicRegisterRequest $request): User
    {
        // 1. Check if user already exists
        $existing = $this->userRepo->findByEmail($request->email);
        if ($existing !== null) {
            throw new ConflictException('Email address is already registered.');
        }

        // 2. Resolve the public registration role from the allowlist.
        $allowedRoles = $this->iamSettings->publicRegistrationAllowedRoles();
        if ($allowedRoles === []) {
            throw new \RuntimeException('Public registration is disabled because no allowlisted roles are configured.');
        }

        $selectedRoleSlug = $allowedRoles[0];
        if (!in_array($selectedRoleSlug, $allowedRoles, true)) {
            throw new \RuntimeException('Public registration role selection is misconfigured.');
        }

        $role = $this->roleRepo->findBySlug($selectedRoleSlug);
        if ($role === null) {
            throw new \RuntimeException(sprintf('Public registration role "%s" is missing.', $selectedRoleSlug));
        }
        if (strtolower($role->getScope()) !== 'customer') {
            throw new \RuntimeException(sprintf('Public registration role "%s" must be customer-scoped.', $selectedRoleSlug));
        }

        $organizationName = trim($request->organizationName);
        if ($organizationName === '') {
            throw new ValidationException(['organizationName' => 'Organization name is required.']);
        }

        // 3. Hash password
        $passwordHash = password_hash(
            $request->password,
            $this->iamSettings->passwordAlgorithmConstant(),
            $this->iamSettings->passwordHashOptions(),
        );

        // 4. Generate user UUID
        $userUuid = UuidSupport::generate();
        $organizationUuid = UuidSupport::generate();
        $now = $this->clock->now();
        $organization = new Organization(
            id: null,
            uuid: $organizationUuid,
            name: $organizationName,
            slug: $this->uniqueSlug($organizationName),
            status: 'active',
            contactEmail: $request->email,
            phone: $request->phone,
        );

        // 5. Ensure "free" plan exists
        $plan = $this->subscriptionPlans->findByCode('free');
        if ($plan === null) {
            $plan = $this->subscriptionPlans->upsert([
                'code' => 'free',
                'name' => 'Pilot',
                'description' => 'For organizations evaluating WorkEddy with a limited number of authorized tasks and users.',
                'billing_cycle' => 'monthly',
                'price' => 0.0,
                'currency' => 'USD',
                'features' => [
                    'max_worksites'                => 1,
                    'video_scan_limit'              => 5,
                    'live_session_limit'            => 5,
                    'live_session_minutes_limit'    => 60,
                    'max_assessments_per_month'     => 10,
                    'video_storage_gb'              => 1,
                    'max_video_retention_days'      => 30,
                    'max_users'                     => 3,
                    'max_live_concurrent_sessions'  => 1,
                ],
                'is_active' => true,
            ]);
        }

        // 6. Build entities
        $platformRole = $this->platformRoleResolver->resolveBaseRole($role);
        $user = new User(
            id: null,
            uuid: $userUuid,
            email: $request->email,
            fullName: $request->fullName,
            passwordHash: $passwordHash,
            roleId: $platformRole->getId(),
            roleSlug: $platformRole->getSlug(),
            status: UserStatus::ACTIVE,
            phone: $request->phone,
        );

        // 7. Persist atomically in a transaction
        $organizationId = null;
        $userId = $this->tx->transactional(function () use ($user, $organization, $role, $plan, $now, &$organizationId) {
            $organizationId = $this->organizationRepo->create($organization);
            $userId = (int) $this->userRepo->create($user);
            $this->profileRepo->create(new UserProfile(
                id: null,
                uuid: '',
                userId: $userId,
                fullName: $user->getFullName(),
                phone: $user->getPhone(),
            ));
            $this->membershipRepo->create(new OrganizationMembership(
                id: null,
                uuid: UuidSupport::generate(),
                userId: $userId,
                organizationId: (int) $organizationId,
                organizationUuid: $organization->getUuid(),
                roleId: (int) $role->getId(),
                roleSlug: $role->getSlug(),
            ));
            $this->subscriptionRepo->createSubscription([
                'uuid' => UuidSupport::generate(),
                'organization_id' => (int) $organizationId,
                'organization_uuid' => $organization->getUuid(),
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'billing_cycle' => $plan->billingCycle,
                'status' => SubscriptionStatus::ACTIVE,
                'start_date' => $now,
                'activated_at' => $now,
            ]);
            return $userId;
        });

        // 8. Reload user
        $createdUser = $this->userRepo->findById($userId);
        if ($createdUser === null) {
            throw new \RuntimeException('User was created but could not be reloaded.');
        }

        // 9. Record audit trail & dispatch notifications
        $this->auditService->record(
            action: 'iam.user.public_register',
            entityType: 'User',
            entityId: (string) $userId,
            beforeState: null,
            afterState: [
                'email' => $request->email,
                'roleSlug' => $role->getSlug(),
                'organizationUuid' => $organization->getUuid(),
            ],
            actorId: (string) $userId,
            actorType: 'User'
        );
        $this->events->publish(
            'organization.created',
            [
                'organization_id' => $organizationId,
                'organization_uuid' => $organization->getUuid(),
                'name' => $organization->getName(),
                'slug' => $organization->getSlug(),
                'contact_email' => $organization->getContactEmail(),
            ],
            idempotencyKey: sprintf('organization.created:%s', $organization->getUuid()),
        );

        $this->logger->info('Public workspace registration completed.', [
            'userId' => $userId,
            'email' => $request->email,
            'organizationUuid' => $organization->getUuid(),
        ]);

        $this->notifications->userEvent(
            event: 'user_created',
            recipientUserId: $userId,
            sourceUserId: $userId,
            sourceUserUuid: $createdUser->getUuid(),
            actorUserId: $userId,
            context: [
                'name' => $request->fullName,
                'email' => $request->email,
                'role' => $role->getName(),
            ]
        );

        return $createdUser;
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        $base = $base !== '' ? $base : 'organization';

        $slug = $base;
        $suffix = 2;
        while ($this->organizationRepo->findBySlug($slug) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
