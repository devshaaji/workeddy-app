<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

use WorkEddy\Platform\Session\UserContext;

final class SettingsPageMetadata
{
    /**
     * @param list<string> $viewPermissions
     * @param list<string> $editPermissions
     */
    public function __construct(
        public readonly string $module,
        public readonly string $label,
        public readonly array $viewPermissions,
        public readonly array $editPermissions,
        public readonly ?string $customPageUrl = null,
        public readonly int $sortOrder = 500,
    ) {}

    public function canView(?UserContext $ctx): bool
    {
        if ($ctx === null) {
            return false;
        }

        foreach ($this->viewPermissions as $permission) {
            if ($ctx->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canEdit(?UserContext $ctx): bool
    {
        if ($ctx === null) {
            return false;
        }

        foreach ($this->editPermissions as $permission) {
            if ($ctx->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function pageUrl(): string
    {
        return $this->customPageUrl ?? ('/settings/page?module=' . rawurlencode($this->module));
    }
}
