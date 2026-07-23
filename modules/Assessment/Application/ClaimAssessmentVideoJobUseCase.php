<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Platform\Queue\IQueueService;

final class ClaimAssessmentVideoJobUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IQueueService $queue,
    ) {}

    /** @return array<string, mixed>|null */
    public function execute(string $workerId, int $lockSeconds = 300): ?array
    {
        $queues = [
            EnqueueAssessmentVideoProcessingUseCase::QUEUE . '.highest',
            EnqueueAssessmentVideoProcessingUseCase::QUEUE . '.high',
            EnqueueAssessmentVideoProcessingUseCase::QUEUE . '.normal',
            EnqueueAssessmentVideoProcessingUseCase::QUEUE . '.low',
            EnqueueAssessmentVideoProcessingUseCase::QUEUE . '.trial',
            EnqueueAssessmentVideoProcessingUseCase::QUEUE,
        ];

        $jobs = [];
        foreach ($queues as $queue) {
            $jobs = $this->queue->claimAvailable($queue, $workerId, 1, $lockSeconds);
            if ($jobs !== []) {
                break;
            }
        }

        if ($jobs === []) {
            return null;
        }

        $job = $jobs[0];
        $payload = $job->payload + ['job_id' => $job->jobId];
        $assessment = $this->assessments->findByUuid((string) $payload['assessment_uuid']);
        if ($assessment !== null) {
            foreach ($assessment->getVideos() as $video) {
                if ($video->getUuid() === ($payload['assessment_video_uuid'] ?? null)) {
                    $this->assessments->updateVideoProcessing($video->markProcessing(date('Y-m-d H:i:s')));
                    break;
                }
            }
        }

        return $payload;
    }
}
