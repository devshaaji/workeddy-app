<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Application;

use WorkEddy\Modules\Organization\Domain\Contracts\IDepartmentRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Organization\Domain\Contracts\IJobRoleRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IWorksiteRepository;
use WorkEddy\Modules\Task\Domain\Task;

final class TaskViewFactory
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly IWorksiteRepository $worksites,
        private readonly IDepartmentRepository $departments,
        private readonly IJobRoleRepository $jobRoles,
        private readonly AssessmentEngine $engine,
    ) {}

    /**
     * @return array{
     *   id: string,
     *   organizationId: string,
     *   worksiteId: ?string,
     *   departmentId: ?string,
     *   jobRoleId: ?string,
     *   name: string,
     *   assessmentModel: string,
     *   supportedInputTypes: string[],
     *   supportsVideo: bool,
     *   taskCode: ?string,
     *   status: string,
     *   description: ?string
     * }
     */
    public function make(Task $task): array
    {
        $organization = $this->organizations->findByUuid($this->resolveOrganizationUuid($task->getOrganizationId()));
        if ($organization === null) {
            foreach ($this->organizations->findAll(1000, 0) as $candidate) {
                if ($candidate->getId() === $task->getOrganizationId()) {
                    $organization = $candidate;
                    break;
                }
            }
        }

        $model = $this->engine->resolve($task->getAssessmentModel());

        return [
            'id' => $task->getUuid(),
            'organizationId' => $organization?->getUuid() ?? '',
            'worksiteId' => $this->worksites->findById($task->getWorksiteId() ?? 0)?->getUuid(),
            'departmentId' => $this->departments->findById($task->getDepartmentId() ?? 0)?->getUuid(),
            'jobRoleId' => $this->jobRoles->findById($task->getJobRoleId() ?? 0)?->getUuid(),
            'name' => $task->getName(),
            'assessmentModel' => $task->getAssessmentModel(),
            'supportedInputTypes' => $model->supportedInputTypes(),
            'supportsVideo' => in_array('video', $model->supportedInputTypes(), true),
            'taskCode' => $task->getTaskCode(),
            'status' => $task->getStatus(),
            'description' => $task->getDescription(),
        ];
    }

    private function resolveOrganizationUuid(int $organizationId): string
    {
        foreach ($this->organizations->findAll(1000, 0) as $organization) {
            if ($organization->getId() === $organizationId) {
                return $organization->getUuid();
            }
        }

        return '';
    }
}
