<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Platform\Module\ModuleRegistry;

/**
 * Syncs code-owned module permissions into IAM persistence.
 */
final class PermissionCatalogSyncService
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly IPermissionRepository $permissionRepository,
    ) {}

    /**
     * @return int Number of inserted/updated permission records.
     */
    public function sync(): int
    {
        $definitions = [];
        foreach ($this->moduleRegistry->permissionProviders() as $provider) {
            $definitions = array_merge($definitions, $provider->definitions());
        }

        return $this->permissionRepository->upsertCatalog($definitions);
    }
}
