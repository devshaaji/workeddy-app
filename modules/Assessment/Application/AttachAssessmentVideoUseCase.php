<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\AssessmentVideo;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class AttachAssessmentVideoUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $assessmentUuid, UserContext $actor, string $storageFileUuid, string $originalFilename, string $mimeType, int $sizeBytes, int $durationSeconds, string $consentTextVersion, bool $faceBlurRequested): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIDEO_UPLOAD);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || $assessment->getId() === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }
        $assessment->assertMutable();

        if (trim($consentTextVersion) === '') {
            throw new ValidationException(['consentTextVersion' => 'Video consent is required.']);
        }
        if (trim($originalFilename) === '') {
            throw new ValidationException(['originalFilename' => 'Original video filename is required.']);
        }
        if (!str_starts_with(strtolower(trim($mimeType)), 'video/')) {
            throw new ValidationException(['mimeType' => 'Assessment evidence must be a video file.']);
        }
        if ($sizeBytes <= 0) {
            throw new ValidationException(['sizeBytes' => 'Video size must be greater than zero.']);
        }
        if ($durationSeconds <= 0) {
            throw new ValidationException(['durationSeconds' => 'Video duration must be greater than zero.']);
        }

        $video = new AssessmentVideo(
            id: null,
            uuid: UuidSupport::generate(),
            assessmentId: (int) $assessment->getId(),
            storageFileUuid: UuidSupport::requireValid($storageFileUuid, 'storageFileUuid'),
            originalFilename: trim($originalFilename),
            mimeType: trim($mimeType),
            sizeBytes: $sizeBytes,
            durationSeconds: $durationSeconds,
            consentTextVersion: trim($consentTextVersion),
            faceBlurRequested: $faceBlurRequested,
        );

        $videoId = $this->tx->transactional(fn(): int => $this->assessments->addVideo($video));
        $assessmentWithVideo = $this->assessments->findByUuid($assessmentUuid);
        $persistedVideo = $assessmentWithVideo?->toView()['videos'][array_key_last($assessmentWithVideo->toView()['videos'] ?? [])] ?? $video->withId($videoId)->toView();
        $this->audit->record('assessment.video.attached', 'assessment_video', (string) ($persistedVideo['uuid'] ?? $video->getUuid()), afterState: $persistedVideo, actorId: (string) $actor->userId, actorType: 'user');

        return $persistedVideo;
    }
}
