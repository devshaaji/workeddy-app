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

final class CreateVideoAssessmentForProcessingUseCase
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
        private readonly UploadAssessmentVideoForProcessingUseCase $uploadVideo,
    ) {}

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function execute(
        string $organizationUuid,
        string $taskUuid,
        UserContext $actor,
        array $file,
        int $durationSeconds,
        string $consentTextVersion,
        bool $acceptedNotice,
        bool $faceBlurRequested,
        ?string $planCode = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
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
        $this->assertVideoSupported($model);

        if ($this->limits->wouldExceed((int) $organization->getId(), SubscriptionMetricCatalog::MAX_ASSESSMENTS_PER_MONTH)) {
            throw new ValidationException(['assessment' => 'Plan assessment limit reached for the current billing period.']);
        }

        $assessment = Assessment::reconstitute(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: (int) $organization->getId(),
            organizationUuid: $organization->getUuid(),
            taskId: (int) $task->getId(),
            taskUuid: $task->getUuid(),
            model: $model,
            metrics: [],
            initialScore: $this->pendingInitialScore(),
            riskFactors: [],
            bodyRegions: [],
            createdBy: $actor->userId,
            status: 'pending_review',
            scoreSource: 'video_pending',
            finalScore: null,
            reviewerId: null,
            reviewerName: null,
            reviewerCredentials: null,
            reviewerNotes: null,
            adjustmentReason: null,
            isBaseline: false,
            videos: [],
            createdAt: null,
        );

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
        $this->audit->record('assessment.submitted', 'assessment', $created->getUuid(), afterState: $created->toView(), actorId: (string) $actor->userId, actorType: 'user');

        $upload = $this->uploadVideo->execute(
            assessmentUuid: $created->getUuid(),
            organizationUuid: $organization->getUuid(),
            actor: $actor,
            file: $file,
            durationSeconds: $durationSeconds,
            consentTextVersion: $consentTextVersion,
            acceptedNotice: $acceptedNotice,
            faceBlurRequested: $faceBlurRequested,
            planCode: $planCode,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return [
            'assessment' => $this->assessments->findByUuid($created->getUuid())?->toView() ?? $created->toView(),
            'upload' => $upload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingInitialScore(): array
    {
        return [
            'raw_score' => 0.0,
            'normalized_score' => 0.0,
            'risk_level' => 'Pending review',
            'risk_category' => 'pending',
            'algorithm_version' => 'video_pending',
        ];
    }

    private function assertVideoSupported(string $model): void
    {
        if (!in_array('video', $this->engine->resolve($model)->supportedInputTypes(), true)) {
            throw new ValidationException(['taskUuid' => 'The selected task uses a manual-only assessment model and cannot accept video input.']);
        }
    }
}
