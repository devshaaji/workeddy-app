<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Presentation;

use WorkEddy\Modules\IAM\Authorization\IAMPermissions;
use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Platform\Session\UserContext;

final class IAMUserActionPolicy
{
    /**
     * @return array<int, array<string, string>>
     */
    public function tableActions(UserContext $ctx, User $target): array
    {
        $actions = [];
        $id = $target->getUuid();

        if ($this->has($ctx, IAMPermissions::USER_UPDATE)) {
            $actions[] = $this->link('edit', 'Edit', '/users/' . $id . '/edit');
        }

        if ($this->has($ctx, IAMPermissions::USER_VIEW)) {
            $actions[] = $this->link('security', 'Security', '/users/' . $id . '/security');
        }

        foreach ($this->workflowActions($ctx, $target) as $action) {
            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function workflowActions(UserContext $ctx, User $target): array
    {
        $status = $target->getStatus()->value;
        $isSelf = $ctx->userId === (int) $target->getId();
        $id = $target->getUuid();
        $actions = [];

        if (($status === 'pending' || $status === 'suspended') && $this->has($ctx, IAMPermissions::USER_ACTIVATE)) {
            $actions[] = $this->api(
                $status === 'pending' ? 'approve' : 'activate',
                $status === 'pending' ? 'Approve' : 'Activate',
                'POST',
                '/api/v1/iam/users/' . $id . '/activate',
                'success',
                $status === 'pending' ? 'Approve user?' : 'Activate user?',
                $status === 'pending' ? 'This pending account will become active.' : 'This suspended account will become active.',
                $status === 'pending' ? 'Approving' : 'Activating',
                $status === 'pending' ? 'User approved.' : 'User activated.',
            );
        }

        if ($status === 'active' && !$isSelf && $this->has($ctx, IAMPermissions::USER_SUSPEND)) {
            $actions[] = $this->api('suspend', 'Suspend', 'POST', '/api/v1/iam/users/' . $id . '/suspend', 'danger', 'Suspend user?', 'This active account will lose access to protected actions.', 'Suspending', 'User suspended.');
        }

        if (($status === 'active' || $status === 'suspended') && !$isSelf && $this->has($ctx, IAMPermissions::USER_PASSWORD_RESET)) {
            $actions[] = $this->api('force-logout', 'Force Logout', 'POST', '/api/v1/iam/users/' . $id . '/force-logout', 'secondary', 'Force logout?', 'All active sessions for this user will be revoked.', 'Logging out', 'User sessions revoked.');
        }

        if ($status !== 'deleted' && !$isSelf && $this->has($ctx, IAMPermissions::USER_SUSPEND)) {
            $actions[] = $this->api('delete', 'Delete', 'DELETE', '/api/v1/iam/users/' . $id, 'danger', 'Delete user?', 'This account will be soft deleted and hidden from normal user lists.', 'Deleting', 'User deleted.');
        }

        return $actions;
    }

    /**
     * @return array<string, bool>
     */
    public function can(UserContext $ctx): array
    {
        return [
            'viewUsers' => $this->has($ctx, IAMPermissions::USER_VIEW),
            'createUsers' => $this->has($ctx, IAMPermissions::USER_CREATE),
            'updateUsers' => $this->has($ctx, IAMPermissions::USER_UPDATE),
            'assignRoles' => $this->has($ctx, IAMPermissions::ROLE_ASSIGN),
            'manageRoles' => $this->has($ctx, IAMPermissions::ROLE_MANAGE),
            'resetPasswords' => $this->has($ctx, IAMPermissions::USER_PASSWORD_RESET),
            'assignPermissions' => $this->has($ctx, IAMPermissions::PERMISSION_ASSIGN),
            'syncPermissions' => $this->has($ctx, IAMPermissions::PERMISSION_SYNC),
            'manageSettings' => $this->has($ctx, IAMPermissions::SETTINGS_MANAGE),
        ];
    }

    private function has(UserContext $ctx, string $permission): bool
    {
        return in_array($permission, $ctx->privileges, true);
    }

    /**
     * @return array<string, string>
     */
    private function link(string $key, string $label, string $url): array
    {
        return ['key' => $key, 'label' => $label, 'method' => 'LINK', 'url' => $url, 'variant' => 'secondary'];
    }

    /**
     * @return array<string, string>
     */
    private function api(string $key, string $label, string $method, string $url, string $variant, string $confirmTitle, string $confirmText, string $loadingText, string $successMessage): array
    {
        return compact('key', 'label', 'method', 'url', 'variant', 'confirmTitle', 'confirmText', 'loadingText', 'successMessage');
    }
}
