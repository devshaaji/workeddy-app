<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IOrganizationMembershipRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserProfileRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\OrganizationMembership;
use WorkEddy\Modules\IAM\Domain\Role;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\IAM\Domain\UserProfile;
use WorkEddy\Modules\IAM\Domain\UserStatus;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class ModuleUserProvisioner
    implements ModuleUserProvisionerInterface
{
    public function __construct(
        private readonly IUserRepository $users,
        private readonly IUserProfileRepository $profiles,
        private readonly IOrganizationMembershipRepository $memberships,
        private readonly IRoleRepository $roles,
        private readonly PlatformRoleResolver $platformRoleResolver,
        private readonly Connection $connection,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly ?UserInvitationSenderInterface $invitations = null,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function provisionPendingUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone = null,
        ?string $roleSlug = null,
        ?string $actorId = null,
        array $metadata = [],
    ): User {
        return $this->provision(
            sourceModule: $sourceModule,
            sourceType: $sourceType,
            sourceId: $sourceId,
            email: $email,
            fullName: $fullName,
            phone: $phone,
            roleSlug: $roleSlug,
            status: UserStatus::PENDING,
            actorId: $actorId,
            metadata: $metadata,
            requiredRoleScope: null,
            sendInvite: false,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function provisionInvitedUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone,
        string $roleSlug,
        ?string $actorId = null,
        array $metadata = [],
        string $requiredRoleScope = 'customer',
        ?string $organizationUuid = null,
    ): User {
        if (trim($roleSlug) === '') {
            throw new ValidationException(['role_slug' => 'Role is required.']);
        }

        return $this->provision(
            sourceModule: $sourceModule,
            sourceType: $sourceType,
            sourceId: $sourceId,
            email: $email,
            fullName: $fullName,
            phone: $phone,
            roleSlug: $roleSlug,
            status: UserStatus::ACTIVE,
            actorId: $actorId,
            metadata: $metadata,
            requiredRoleScope: $requiredRoleScope,
            sendInvite: true,
            organizationUuid: $organizationUuid,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function provision(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone,
        ?string $roleSlug,
        UserStatus $status,
        ?string $actorId,
        array $metadata,
        ?string $requiredRoleScope,
        bool $sendInvite,
        ?string $organizationUuid = null,
    ): User {
        return $this->tx->transactional(function () use ($sourceModule, $sourceType, $sourceId, $email, $fullName, $phone, $roleSlug, $status, $actorId, $metadata, $requiredRoleScope, $sendInvite, $organizationUuid): User {
            $sourceModule = strtolower(trim($sourceModule));
            $sourceType = strtolower(trim($sourceType));
            $sourceId = trim($sourceId);
            $email = strtolower(trim($email));
            $fullName = trim($fullName);

            $errors = [];
            if ($sourceModule === '') {
                $errors['source_module'] = 'Source module is required.';
            }
            if ($sourceType === '') {
                $errors['source_type'] = 'Source type is required.';
            }
            if ($sourceId === '') {
                $errors['source_id'] = 'Source id is required.';
            }
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'A valid email is required.';
            }
            if ($fullName === '') {
                $errors['full_name'] = 'Full name is required.';
            }
            if ($errors !== []) {
                throw new ValidationException($errors);
            }

            $existingSource = $this->connection->fetchAssociative(
                'SELECT user_id FROM iam_user_sources WHERE source_module = ? AND source_type = ? AND source_id = ?',
                [$sourceModule, $sourceType, $sourceId],
            );
            if ($existingSource !== false) {
                $existingUser = $this->users->findById((int) $existingSource['user_id']);
                if ($existingUser !== null) {
                    return $existingUser;
                }
            }

            if ($this->users->findByEmail($email) !== null) {
                throw new ConflictException("IAM user '{$email}' already exists.");
            }

            $role = $this->resolveRole($roleSlug, $requiredRoleScope);
            $platformRole = $this->platformRoleResolver->resolveBaseRole($role);
            $user = new User(
                id: null,
                uuid: UuidSupport::generate(),
                email: $email,
                fullName: $fullName,
                passwordHash: password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                roleId: (int) $platformRole->getId(),
                roleSlug: $platformRole->getSlug(),
                status: $status,
                phone: $phone !== null && trim($phone) !== '' ? trim($phone) : null,
                authzVersion: 1,
            );

            $userId = (int) $this->users->create($user);
            $this->profiles->create(new UserProfile(
                id: null,
                uuid: UuidSupport::generate(),
                userId: $userId,
                fullName: $fullName,
                phone: $phone,
            ));

            if ($organizationUuid !== null && trim($organizationUuid) !== '') {
                $organizationId = $this->connection->fetchOne(
                    'SELECT id FROM organizations WHERE uuid = ? AND deleted_at IS NULL',
                    [UuidSupport::requireValid($organizationUuid, 'organizationUuid')],
                );

                if ($organizationId === false || $organizationId === null) {
                    throw new ValidationException(['organizationUuid' => 'Selected organization does not exist.']);
                }

                $this->memberships->create(new OrganizationMembership(
                    id: null,
                    uuid: UuidSupport::generate(),
                    userId: $userId,
                    organizationId: (int) $organizationId,
                    organizationUuid: $organizationUuid,
                    roleId: (int) $role->getId(),
                    roleSlug: $role->getSlug(),
                ));
            }

            $persisted = $this->users->findById($userId);
            if ($persisted === null) {
                throw new \RuntimeException('Provisioned IAM user could not be reloaded.');
            }

            $now = $this->clock->now()->format('Y-m-d H:i:s');
            $this->connection->insert('iam_user_sources', [
                'user_id' => $userId,
                'source_module' => $sourceModule,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'metadata_json' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->record(
                action: 'iam.module_user.provisioned',
                entityType: 'iam_user',
                entityId: (string) $persisted->getId(),
                afterState: [
                    'email' => $persisted->getEmail(),
                    'role_slug' => $persisted->getRoleSlug(),
                    'status' => $persisted->getStatus()->value,
                    'source_module' => $sourceModule,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                actorId: $actorId,
                actorType: $actorId !== null ? 'user' : 'system',
            );

            if ($sendInvite) {
                if ($this->invitations === null) {
                    throw new \RuntimeException('User invitation sender is not configured.');
                }
                $this->invitations->sendPasswordSetup($persisted, $actorId);
            }

            return $persisted;
        });
    }

    private function resolveRole(?string $roleSlug, ?string $requiredScope = null): Role
    {
        $slug = trim((string) $roleSlug);
        if ($slug === '') {
            throw new ValidationException(['role_slug' => 'Role is required.']);
        }

        $role = $this->roles->findBySlug($slug);
        if ($role === null) {
            if ($requiredScope !== null) {
                throw new ValidationException(['role_slug' => 'Selected role is not available.']);
            }

            return $this->createSystemRole($slug, ucwords(str_replace(['-', '_'], ' ', $slug)), $this->fallbackScopeForSlug($slug));
        }

        if ($requiredScope !== null) {
            $scope = (string) $this->connection->fetchOne('SELECT scope FROM iam_roles WHERE id = ?', [(int) $role->getId()]);
            if (strtolower($scope) !== strtolower($requiredScope)) {
                throw new ValidationException(['role_slug' => 'Selected role is not available for the requested scope.']);
            }
        }

        return $role;
    }

    private function createSystemRole(string $slug, string $label, string $scope): Role
    {
        $role = new Role(
            id: null,
            uuid: UuidSupport::generate(),
            slug: $slug,
            name: $label,
            description: null,
            isSystem: true,
            scope: $scope,
            permissions: [],
        );

        $roleId = (int) $this->roles->create($role);
        $created = $this->roles->findById($roleId);
        if ($created === null) {
            throw new \RuntimeException("System role '{$slug}' could not be created.");
        }

        return $created;
    }

    private function fallbackScopeForSlug(string $slug): string
    {
        return match ($slug) {
            'customer' => 'customer',
            default => 'system',
        };
    }
}
