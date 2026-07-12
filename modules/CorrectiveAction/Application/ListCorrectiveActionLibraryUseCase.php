<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class ListCorrectiveActionLibraryUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $filters @return array{summary:array<string,int>,meta:array<string,int>,items:list<array<string,mixed>>} */
    public function execute(UserContext $actor, array $filters = []): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::VIEW);

        $allItems = $this->repository->listLibraryItems();
        $filteredItems = $this->repository->listLibraryItems($filters);

        return [
            'summary' => [
                'totalActions' => count($allItems),
                'activeActions' => count(array_filter($allItems, static fn(CorrectiveActionLibraryItem $item): bool => $item->isActive)),
            ],
            'meta' => [
                'total' => count($filteredItems),
            ],
            'items' => array_map(
                fn(CorrectiveActionLibraryItem $item): array => $this->normalize($item),
                $filteredItems,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function normalize(CorrectiveActionLibraryItem $item): array
    {
        $view = $item->toView();

        return $view + [
            'category' => $item->hierarchyLevel,
            'bodyArea' => $item->riskFactor,
            'riskLevel' => $item->priority,
            'status' => $item->isActive ? 'active' : 'inactive',
            'linkedRuleCount' => $this->repository->countRulesForLibraryItem($item->uuid),
            'reasonText' => $item->reason,
        ];
    }
}
