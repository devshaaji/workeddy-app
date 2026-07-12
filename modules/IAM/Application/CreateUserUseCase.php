<?php

/**
 * CreateUserUseCase — register a new user account.
 *
 * Called by admins. Validates uniqueness, hashes password, assigns role.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\DTOs\CreateUserRequest;
use WorkEddy\Modules\IAM\Application\DTOs\UserDTO;
use WorkEddy\Modules\IAM\Application\Services\IAMNotificationDispatcher;
use WorkEddy\Modules\IAM\Application\Services\PlatformRoleResolver;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Modules\IAM\Domain\UserProfile;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use Doctrine\DBAL\Connection;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CreateUserUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository       $userRepo,
        private readonly IRoleRepository        $roleRepo,
        private readonly IPermissionRepository  $permissionRepo,
        private readonly IPermissionService     $permissionService,
        private readonly IUserProfileRepository $profileRepo,
        private readonly IOrganizationMembershipRepository $membershipRepo,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService          $auditService,
        private readonly IAMSettings            $iamSettings,
        private readonly IAMNotificationDispatcher $notifications,
        private readonly PlatformRoleResolver $platformRoleResolver,
        private readonly Connection             $connection,
        private readonly ?IClock $clock = null,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    /**
     * @throws ValidationException If input is invalid.
     * @throws ConflictException If username or email already exists.
     * @throws NotFoundException If role not found.
     */
    public function execute(CreateUserRequest $request, UserContext $ctx): UserDTO
    {
        $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_CREATE);

        // Step 1: Input validation
        $errors = [];
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }
        if (strlen(trim($request->fullName)) < 2) {
            $errors['fullName'] = 'Full name is required.';
        }
        $minPasswordLen = $this->iamSettings->minPasswordLength();
        if (strlen($request->password) < $minPasswordLen) {
            $errors['password'] = "Password must be at least {$minPasswordLen} characters.";
        }
        if (trim($request->roleSlug) === '') {
            $errors['roleSlug'] = 'Role is required.';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Step 2: Uniqueness checks
        if ($this->userRepo->findByEmail($request->email) !== null) {
            throw new ConflictException("Email '{$request->email}' is already registered.");
        }

        // Step 3: Resolve role
        $role = $this->roleRepo->findBySlug($request->roleSlug);
        if ($role === null) {
            throw new NotFoundException('Role');
        }
        if ($request->organizationUuid !== null && trim($request->organizationUuid) !== '' && strtolower($role->getScope()) !== 'customer') {
            throw new ValidationException(['roleSlug' => 'Organization users must use a customer-scoped role.']);
        }
        if (($request->organizationUuid === null || trim($request->organizationUuid) === '') && strtolower($role->getScope()) === 'customer') {
            throw new ValidationException(['organizationUuid' => 'Customer-scoped roles require an organization membership.']);
        }

        // Step 4: Hash password (using configurable policy)
        $passwordHash = password_hash(
            $request->password,
            $this->iamSettings->passwordAlgorithmConstant(),
            $this->iamSettings->passwordHashOptions(),
        );

        $defaultStatus = $this->iamSettings->defaultUserStatusActive()
            ? UserStatus::ACTIVE
            : UserStatus::PENDING;

        // Step 5: Build entity
        $platformRole = $this->platformRoleResolver->resolveBaseRole($role);
        $user = new User(
            id: null,
            uuid: '',
            email: $request->email,
            fullName: $request->fullName,
            passwordHash: $passwordHash,
            roleId: $platformRole->getId(),
            roleSlug: $platformRole->getSlug(),

            status: $defaultStatus,
            phone: $request->phone,
        );

        // Step 6: Persist atomically
        $userId = $this->tx->transactional(function () use ($request, $user, $role) {
            $userId = (int) $this->userRepo->create($user);
            $this->profileRepo->create(new UserProfile(
                id: null,
                uuid: '',
                userId: $userId,
                fullName: $user->getFullName(),
                phone: $user->getPhone(),
            ));

            if ($request->organizationUuid !== null && trim($request->organizationUuid) !== '') {
                $organizationId = $this->connection->fetchOne(
                    'SELECT id FROM organizations WHERE uuid = ? AND deleted_at IS NULL',
                    [$request->organizationUuid],
                );
                if ($organizationId === false || $organizationId === null) {
                    throw new NotFoundException('Organization');
                }

                $this->membershipRepo->create(new OrganizationMembership(
                    id: null,
                    uuid: '',
                    userId: $userId,
                    organizationId: (int) $organizationId,
                    organizationUuid: $request->organizationUuid,
                    roleId: (int) $role->getId(),
                    roleSlug: $role->getSlug(),
                ));
            }

            return $userId;
        });
        $createdUser = $this->userRepo->findById($userId);
        if ($createdUser === null) {
            throw new \RuntimeException('User was created but could not be reloaded.');
        }

        // Step 7: Resolve permissions for response
        $permissions = $this->permissionRepo->resolveEffectivePermissions($userId, $role->getId());

        $this->auditService->record(
            action: 'iam.user.created',
            entityType: 'User',
            entityId: (string) $userId,
            beforeState: null,
            afterState: [
                'email' => $request->email,
                'roleSlug' => $request->roleSlug,
            ],
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        $this->logger->info('User account created.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $userId,
            'roleSlug' => $request->roleSlug,
            'status' => $defaultStatus->value,
        ]);

        $this->notifications->userEvent(
            event: 'user_created',
            recipientUserId: $userId,
            sourceUserId: $userId,
            sourceUserUuid: $createdUser->getUuid(),
            actorUserId: $ctx->userId,
            context: [
                'name' => $request->fullName,
                'email' => $request->email,
                'role' => $role->getName(),
                'status' => $defaultStatus->value,
            ],
        );

        return new UserDTO(
            id: $createdUser->getUuid(),
            email: $request->email,
            fullName: $request->fullName,
            roleSlug: $role->getSlug(),
            roleName: $role->getName(),
            status: $defaultStatus->value,
            phone: $request->phone,
            permissions: $permissions,
            lastLoginAt: null,
            createdAt: $this->clock()->now()->format('Y-m-d H:i:s'),
            authzVersion: 1,
        );
    }

    private function clock(): IClock
    {
        return $this->clock ?? new SystemClock();
    }
}
