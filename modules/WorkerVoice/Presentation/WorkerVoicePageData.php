<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Presentation;

use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Authorization\WorkerVoicePermissions;
use WorkEddy\Platform\Session\UserContext;

final class WorkerVoicePageData
{
    public function __construct(
        private readonly GetWorkerFeedbackUseCase $getWorkerFeedback,
        private readonly IWorksiteRepository $worksites,
        private readonly IDepartmentRepository $departments,
        private readonly IJobRoleRepository $jobRoles,
    ) {}

    /** @return array<string, mixed> */
    public function common(UserContext $ctx, string $title): array
    {
        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Worker voice and discomfort reporting',
            'organizationUuid' => $ctx->organizationUuid,
            'can' => [
                'submit' => in_array(WorkerVoicePermissions::SUBMIT, $ctx->privileges, true),
                'view' => in_array(WorkerVoicePermissions::VIEW, $ctx->privileges, true),
                'viewAggregates' => in_array(WorkerVoicePermissions::VIEW_AGGREGATES, $ctx->privileges, true),
                'viewSensitive' => in_array(WorkerVoicePermissions::VIEW_SENSITIVE, $ctx->privileges, true),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function show(UserContext $ctx, string $feedbackUuid): array
    {
        $feedback = $this->getWorkerFeedback->execute($feedbackUuid, $ctx);

        return [
            'feedback' => $feedback,
            'feedbackLabels' => [
                'worksite' => $this->resolveLabel($this->worksites, $feedback['worksiteUuid'] ?? null),
                'department' => $this->resolveLabel($this->departments, $feedback['departmentUuid'] ?? null),
                'jobRole' => $this->resolveLabel($this->jobRoles, $feedback['jobRoleUuid'] ?? null),
            ],
        ];
    }

    /**
     * @param object $repository
     */
    private function resolveLabel(object $repository, ?string $uuid): ?string
    {
        $uuid = is_string($uuid) ? trim($uuid) : '';
        if ($uuid === '') {
            return null;
        }

        $entity = null;
        if ($repository instanceof IWorksiteRepository) {
            $entity = $repository->findByUuid($uuid);
        } elseif ($repository instanceof IDepartmentRepository) {
            $entity = $repository->findByUuid($uuid);
        } elseif ($repository instanceof IJobRoleRepository) {
            $entity = $repository->findByUuid($uuid);
        }

        if ($entity === null) {
            return $uuid;
        }

        return method_exists($entity, 'getName') ? (string) $entity->getName() : $uuid;
    }
}
