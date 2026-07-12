<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackViewService;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Platform\Session\UserContext;

final class ListWorkerFeedbackUseCase
{
    public function __construct(
        private readonly IWorkerVoiceRepository $feedback,
        private readonly IPermissionService $permissions,
        private readonly WorkerFeedbackViewService $views,
    ) {}

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function execute(UserContext $actor, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, WorkerVoicePermissions::VIEW);
        $includeSensitive = in_array(WorkerVoicePermissions::VIEW_SENSITIVE, $actor->privileges, true);

        return array_map(
            fn($item): array => $this->views->make($item, $includeSensitive && !$item->anonymousStatus),
            $this->feedback->findAllByOrganizationId($actor->organizationId ?? 0, $filters, $limit, $offset),
        );
    }
}
