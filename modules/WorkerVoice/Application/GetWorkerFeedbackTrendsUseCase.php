<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackTrendService;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;

final class GetWorkerFeedbackTrendsUseCase
{
    public function __construct(
        private readonly IWorkerVoiceRepository $feedback,
        private readonly IPermissionService $permissions,
        private readonly WorkerFeedbackTrendService $trends,
        private readonly WorkerVoiceSettings $settings,
        private readonly IAuditService $audit,
    ) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function execute(UserContext $actor, array $filters = []): array
    {
        $this->permissions->requirePrivilege($actor, WorkerVoicePermissions::VIEW_AGGREGATES);
        $items = $this->feedback->findAllByOrganizationId($actor->organizationId ?? 0, $filters, $this->settings->maxTrendSampleSize(), 0);
        $result = $this->trends->summarize($items);
        $this->audit->record('worker_feedback.trends_viewed', 'worker_feedback', (string) ($actor->organizationUuid ?? 'organization'), beforeState: null, afterState: ['filters' => $filters, 'total' => $result['summary']['totalResponses'] ?? 0], actorId: (string) $actor->userId, actorType: 'user');

        return $result;
    }
}
