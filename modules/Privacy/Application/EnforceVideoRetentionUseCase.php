<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\Privacy\Domain\RetentionPolicy;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class EnforceVideoRetentionUseCase
{
    public function __construct(
        private readonly IPrivacyRepository $privacy,
        private readonly IAssessmentRepository $assessments,
        private readonly IStorageService $storage,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $assessmentUuid, UserContext $actor): array
    {
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }

        $policy = $this->privacy->findRetentionPolicyByOrganizationId($assessment->getOrganizationId());
        $deleted = [];
        if ($policy !== null && $policy->rawVideoPolicy === RetentionPolicy::RAW_DELETE_AFTER_PROCESSING) {
            foreach ($assessment->getVideos() as $video) {
                if ($video->getProcessingStatus() === 'completed') {
                    $this->storage->delete($video->getStorageFileUuid(), $actor->userId);
                    $deleted[] = $video->getStorageFileUuid();
                }
            }
        }

        $result = [
            'assessmentUuid' => $assessment->getUuid(),
            'organizationUuid' => $assessment->getOrganizationUuid(),
            'policy' => $policy?->toView(),
            'deletedStorageFileUuids' => $deleted,
        ];
        $this->audit->record('privacy.video.retention_enforced', 'assessment', $assessment->getUuid(), afterState: $result, actorId: (string) $actor->userId, actorType: 'user');

        return $result;
    }
}
