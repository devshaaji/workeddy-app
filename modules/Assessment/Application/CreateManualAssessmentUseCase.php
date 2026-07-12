<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Assessment;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateManualAssessmentUseCase
{
    public function __construct(
        private readonly IOrganizationRepository $organizations,
        private readonly ITaskRepository $tasks,
        private readonly IAssessmentRepository $assessments,
        private readonly AssessmentEngine $engine,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
        private readonly ISubscriptionLimitGuard $limits,
        private readonly ISubscriptionUsageRecorder $usage,
    ) {}

    /**
     * @param array<string, mixed> $metrics
     * @param list<string> $riskFactors
     * @param list<array<string, mixed>> $bodyRegions
     * @return array<string, mixed>
     */
    public function execute(string $organizationUuid, string $taskUuid, string $model, array $metrics, UserContext $actor, array $riskFactors = [], array $bodyRegions = [], bool $submitForReview = true): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::CREATE);
        $organization = $this->organizations->findByUuid(UuidSupport::requireValid($organizationUuid, 'organizationUuid'));
        if ($organization === null || $organization->getId() === null) {
            throw new NotFoundException('Organization not found.');
        }
        if ($actor->organizationId !== null && $actor->organizationId !== $organization->getId()) {
            throw new NotFoundException('Organization not found.');
        }

        $task = $this->tasks->findByUuid(UuidSupport::requireValid($taskUuid, 'taskUuid'));
        if ($task === null || $task->getId() === null || $task->getOrganizationId() !== $organization->getId()) {
            throw new NotFoundException('Task not found.');
        }
        $model = $task->getAssessmentModel();
        if ($this->limits->wouldExceed((int) $organization->getId(), SubscriptionMetricCatalog::MAX_ASSESSMENTS_PER_MONTH)) {
            throw new ValidationException(['assessment' => 'Plan assessment limit reached for the current billing period.']);
        }

        $score = $this->engine->assess($model, $metrics);
        $assessment = Assessment::create(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: (int) $organization->getId(),
            organizationUuid: $organization->getUuid(),
            taskId: (int) $task->getId(),
            taskUuid: $task->getUuid(),
            model: $model,
            metrics: $metrics,
            initialScore: $score,
            riskFactors: $this->normalizeRiskFactors($riskFactors),
            bodyRegions: $this->normalizeBodyRegions($bodyRegions),
            createdBy: $actor->userId,
        );
        if ($submitForReview) {
            $assessment = $assessment->markSubmitted();
        }

        $id = $this->tx->transactional(function () use ($assessment, $organization): int {
            $id = $this->assessments->create($assessment);
            $this->usage->forOrganization((int) $organization->getId(), SubscriptionMetricCatalog::MAX_ASSESSMENTS_PER_MONTH);

            return $id;
        });

        $created = $this->assessments->findById($id);
        if ($created === null) {
            throw new \RuntimeException('Assessment was created but could not be reloaded.');
        }

        $this->audit->record('assessment.created', 'assessment', $created->getUuid(), afterState: $created->toView(), actorId: (string) $actor->userId, actorType: 'user');
        if ($submitForReview) {
            $this->audit->record('assessment.submitted', 'assessment', $created->getUuid(), afterState: $created->toView(), actorId: (string) $actor->userId, actorType: 'user');
        }

        return $created->toView();
    }

    /**
     * @param list<string> $riskFactors
     * @return list<string>
     */
    private function normalizeRiskFactors(array $riskFactors): array
    {
        return array_values(array_filter(array_map(static fn(string $value): string => trim($value), $riskFactors), static fn(string $value): bool => $value !== ''));
    }

    /**
     * @param list<array<string, mixed>> $bodyRegions
     * @return list<array<string, mixed>>
     */
    private function normalizeBodyRegions(array $bodyRegions): array
    {
        return array_map(static function (array $region): array {
            $name = trim((string) ($region['region'] ?? ''));
            if ($name === '') {
                throw new ValidationException(['bodyRegions' => 'Body region is required.']);
            }

            return [
                'region' => $name,
                'side' => trim((string) ($region['side'] ?? 'front')),
                'intensity' => max(0, min(5, (int) ($region['intensity'] ?? 0))),
            ];
        }, $bodyRegions);
    }
}
