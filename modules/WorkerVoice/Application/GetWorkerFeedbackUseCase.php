<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\WorkerVoice\Application\Services\WorkerFeedbackViewService;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Modules\WorkerVoice\Domain\Contracts\IWorkerVoiceRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class GetWorkerFeedbackUseCase
{
    public function __construct(
        private readonly IWorkerVoiceRepository $feedback,
        private readonly IPermissionService $permissions,
        private readonly WorkerFeedbackViewService $views,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $feedbackUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, WorkerVoicePermissions::VIEW);
        $feedback = $this->feedback->findByUuid(UuidSupport::requireValid($feedbackUuid, 'feedbackUuid'));
        if ($feedback === null || ($actor->organizationId !== null && $feedback->organizationId !== $actor->organizationId)) {
            throw new NotFoundException('Worker feedback not found.');
        }

        $includeSensitive = in_array(WorkerVoicePermissions::VIEW_SENSITIVE, $actor->privileges, true) && !$feedback->anonymousStatus;
        $view = $this->views->make($feedback, $includeSensitive);
        if ($includeSensitive) {
            $this->audit->record('worker_feedback.viewed_sensitive', 'worker_feedback', $feedback->uuid, beforeState: null, afterState: ['viewed' => true], actorId: (string) $actor->userId, actorType: 'user');
        }

        return $view;
    }
}
