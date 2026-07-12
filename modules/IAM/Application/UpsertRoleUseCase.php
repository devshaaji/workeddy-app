<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Role;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class UpsertRoleUseCase
{
    public function __construct(
        private readonly IRoleRepository $roles,
        private readonly SyncRolePermissionsUseCase $syncPermissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    public function execute(?int $roleId, array $body, UserContext $ctx): Role
    {
        $isCreate = $roleId === null;
        $name = trim((string) ($body['name'] ?? ''));
        $description = isset($body['description']) && trim((string) $body['description']) !== ''
            ? trim((string) $body['description'])
            : null;
        $scope = strtolower(trim((string) ($body['scope'] ?? 'staff')));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Role name is required.';
        }
        if (!in_array($scope, ['staff', 'customer', 'system'], true)) {
            $errors['scope'] = 'Role scope must be staff, customer, or system.';
        }

        if ($isCreate) {
            $slug = trim((string) ($body['slug'] ?? ''));
            if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/', $slug)) {
                $errors['slug'] = 'Role slug must be 2-64 lowercase letters, numbers, dots, underscores, or dashes.';
            } elseif ($this->roles->findBySlug($slug) !== null) {
                $errors['slug'] = 'Role slug already exists.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->tx->transactional(function () use ($roleId, $isCreate, $body, $ctx, $name, $description, $scope): Role {
            $before = null;
            if ($isCreate) {
                $createdRoleId = $this->roles->create(new Role(
                    id: null,
                    uuid: '',
                    slug: trim((string) $body['slug']),
                    name: $name,
                    description: $description,
                    isSystem: false,
                    scope: $scope,
                ));
                $roleId = $createdRoleId;
            } else {
                $existing = $this->roles->findById($roleId ?? 0);
                if ($existing === null) {
                    throw new NotFoundException('Role', $roleId ?? 0);
                }
                if ($existing->isSystem()) {
                    throw new ValidationException(['role' => 'System roles cannot be edited.']);
                }

                $before = $this->serialize($existing);
                $existing->updateName($name);
                $existing->updateDescription($description);
                $existing->updateScope($scope);
                $this->roles->update($existing);
            }

            if (isset($body['permissionIds'])) {
                if (!is_array($body['permissionIds'])) {
                    throw new ValidationException(['permissionIds' => 'Permission IDs must be an array.']);
                }
                $role = $this->syncPermissions->execute($roleId ?? 0, $body['permissionIds'], $ctx);
            } else {
                $role = $this->roles->findById($roleId ?? 0);
                if ($role === null) {
                    throw new NotFoundException('Role', $roleId ?? 0);
                }
            }

            $this->audit->record(
                action: $isCreate ? 'iam.role.created' : 'iam.role.updated',
                entityType: 'Role',
                entityId: (string) ($role->getId() ?? 0),
                beforeState: $before,
                afterState: array_merge($this->serialize($role), ['module' => 'IAM', 'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null]),
                actorId: (string) $ctx->userId,
            );

            return $role;
        });
    }

    private function serialize(Role $role): array
    {
        return [
            'id' => $role->getId(),
            'slug' => $role->getSlug(),
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'isSystem' => $role->isSystem(),
            'scope' => $role->getScope(),
            'permissions' => $role->getPermissions(),
        ];
    }
}
