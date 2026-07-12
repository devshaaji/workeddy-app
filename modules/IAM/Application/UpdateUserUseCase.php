<?php

/**
 * UpdateUserUseCase — update identity fields (admin).
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Application\DTOs\UpdateUserRequest;
use WorkEddy\Modules\IAM\Application\DTOs\UserDTO;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class UpdateUserUseCase
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly IUserRepository       $userRepo,
        private readonly IUserProfileRepository $profileRepo,
        private readonly IRoleRepository        $roleRepo,
        private readonly IPermissionRepository  $permissionRepo,
        private readonly IPermissionService     $permissionService,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService          $auditService,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    public function execute(UpdateUserRequest $request, UserContext $ctx): UserDTO
    {
        if ($ctx->userId !== $request->userId) {
            $this->permissionService->requirePrivilege($ctx, IAMPermissions::USER_UPDATE);
        }

        // Validate
        $errors = [];
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }
        if (strlen(trim($request->fullName)) < 2) {
            $errors['fullName'] = 'Full name is required.';
        }
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Load
        $user = $this->userRepo->findById($request->userId);
        if ($user === null) {
            throw new NotFoundException('User', $request->userId);
        }

        // Email uniqueness (if changed)
        if (strtolower($request->email) !== strtolower($user->getEmail())) {
            $existing = $this->userRepo->findByEmail($request->email);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                throw new ConflictException("Email '{$request->email}' is already registered.");
            }
        }

        // Capture before state
        $before = [
            'fullName' => $user->getFullName(),
            'email'    => $user->getEmail(),
            'phone'    => $user->getPhone(),
        ];

        $after = [
            'fullName' => $request->fullName,
            'email'    => $request->email,
            'phone'    => $request->phone,
        ];

        // Apply changes
        $this->tx->transactional(function () use ($user, $request): void {
            $user->updateProfile($request->fullName, $request->email, $request->phone);
            $this->userRepo->update($user);

            $profile = $this->profileRepo->findByUserId((int) $user->getId());
            if ($profile === null) {
                $this->profileRepo->create(new \WorkEddy\Modules\IAM\Domain\UserProfile(
                    id: null,
                    uuid: '',
                    userId: (int) $user->getId(),
                    fullName: $request->fullName,
                    phone: $request->phone,
                ));
                return;
            }

            $profile->update($request->fullName, $request->phone);
            $this->profileRepo->update($profile);
        });

        $this->auditService->record(
            action: 'iam.user.updated',
            entityType: 'User',
            entityId: (string) $user->getId(),
            beforeState: $before,
            afterState: $after,
            actorId: (string) $ctx->userId,
            actorType: 'User'
        );

        $changedFields = [];
        foreach ($before as $field => $beforeValue) {
            if (($after[$field] ?? null) !== $beforeValue) {
                $changedFields[] = $field;
            }
        }

        $this->logger->info('User profile updated.', [
            'actorId' => $ctx->userId,
            'targetUserId' => $user->getId(),
            'changedFields' => $changedFields,
        ]);

        // Build response
        $roleId = $user->getMembershipRoleId() ?? (int) $user->getRoleId();
        $roleSlug = $user->getMembershipRoleSlug() ?? $user->getRoleSlug();
        $role = $this->roleRepo->findById($roleId);
        $permissions = $this->permissionRepo->resolveEffectivePermissions(
            $user->getId(),
            $roleId,
        );

        return new UserDTO(
            id: $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            roleSlug: $roleSlug,
            roleName: $role?->getName() ?? $roleSlug,
            status: $user->getStatus()->value,
            phone: $user->getPhone(),
            permissions: $permissions,
            lastLoginAt: $user->getLastLoginAt(),
            createdAt: $user->getCreatedAt() ?? '',
            authzVersion: $user->getAuthzVersion(),
        );
    }
}
