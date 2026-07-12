<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class FailAssessmentVideoJobUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IQueueService $queue,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $jobId, string $workerId, string $assessmentUuid, string $assessmentVideoUuid, string $organizationUuid, string $errorMessage): array
    {
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || $assessment->getOrganizationUuid() !== UuidSupport::requireValid($organizationUuid, 'organizationUuid')) {
            throw new NotFoundException('Assessment not found.');
        }

        foreach ($assessment->getVideos() as $candidate) {
            if ($candidate->getUuid() === UuidSupport::requireValid($assessmentVideoUuid, 'assessmentVideoUuid')) {
                $video = $candidate->markFailed($errorMessage, date('Y-m-d H:i:s'));
                $this->assessments->updateVideoProcessing($video);
                $this->queue->fail($jobId, $workerId, $errorMessage, 60);
                $this->audit->record('assessment.video.processing_failed', 'assessment_video', $video->getUuid(), afterState: $video->toView(), actorType: 'worker');

                return $video->toView();
            }
        }

        throw new NotFoundException('Assessment video not found.');
    }
}
