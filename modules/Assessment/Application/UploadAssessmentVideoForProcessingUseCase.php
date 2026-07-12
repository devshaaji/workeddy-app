<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\Processing\SubscriptionAssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettings;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Subscription\Application\Support\SubscriptionMetricCatalog;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;

final class UploadAssessmentVideoForProcessingUseCase
{
    public function __construct(
        private readonly IStorageService $storage,
        private readonly RecordVideoConsentUseCase $recordConsent,
        private readonly AttachAssessmentVideoUseCase $attachVideo,
        private readonly EnqueueAssessmentVideoProcessingUseCase $enqueueVideo,
        private readonly ISubscriptionLimitGuard $limits,
        private readonly ISubscriptionUsageRecorder $usage,
        private readonly IOrganizationRepository $organizations,
        private readonly AssessmentVideoProcessingProfileResolver $profiles = new AssessmentVideoProcessingProfileResolver(),
        private readonly ?SubscriptionAssessmentVideoProcessingProfileResolver $subscriptionProfiles = null,
        private readonly ?AssessmentSettings $settings = null,
    ) {}

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function execute(string $assessmentUuid, string $organizationUuid, UserContext $actor, array $file, int $durationSeconds, string $consentTextVersion, bool $acceptedNotice, bool $faceBlurRequested, ?string $planCode = null, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $organizationId = $this->resolveOrganizationId($actor, $organizationUuid);
        $profile = $this->resolveProfile($organizationId, $planCode);
        $this->validateVideo($file, $durationSeconds, $profile->maxDurationSeconds);
        $estimatedUsageMb = $this->estimateUsageMb((int) ($file['size'] ?? 0));
        if ($organizationId !== null && $this->limits->wouldExceed($organizationId, SubscriptionMetricCatalog::VIDEO_STORAGE_GB, $estimatedUsageMb)) {
            throw new ValidationException(['file' => 'Plan video storage limit reached. Upgrade to upload more video.']);
        }

        $stored = $this->storage->storeUploadedFile(new StoreUploadedFileRequest(
            file: $file,
            ownerType: 'assessment',
            ownerUuid: $assessmentUuid,
            fieldName: 'video',
            visibility: 'private',
            actorId: $actor->userId,
            allowedExtensions: ['mp4', 'mov', 'webm'],
            allowedMimeTypes: ['video/mp4', 'video/quicktime', 'video/webm'],
        ));
        if ($stored === null) {
            throw new ValidationException(['file' => 'Video file is required.']);
        }
        if ($organizationId !== null) {
            $this->usage->forOrganization($organizationId, SubscriptionMetricCatalog::VIDEO_STORAGE_USED_MB, $this->estimateUsageMb($stored->sizeBytes));
        }
        $videoSha256 = $this->calculateVideoHash($file);

        $consent = $this->recordConsent->execute(
            organizationUuid: $organizationUuid,
            assessmentUuid: $assessmentUuid,
            storageFileUuid: $stored->uuid,
            actor: $actor,
            textVersion: $consentTextVersion,
            acceptedNotice: $acceptedNotice,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $video = $this->attachVideo->execute(
            assessmentUuid: $assessmentUuid,
            actor: $actor,
            storageFileUuid: $stored->uuid,
            originalFilename: $stored->originalName,
            mimeType: (string) $stored->mimeType,
            sizeBytes: $stored->sizeBytes,
            durationSeconds: $durationSeconds,
            consentTextVersion: $consentTextVersion,
            faceBlurRequested: $faceBlurRequested,
        );

        $queued = $this->enqueueVideo->execute(
            assessmentUuid: $assessmentUuid,
            assessmentVideoUuid: (string) $video['uuid'],
            actor: $actor,
            videoPath: $this->workerReadablePath($stored->path),
            planCode: $planCode,
            subscriptionProfile: $profile->toWorkerPayload(),
            videoSha256: $videoSha256,
        );

        return [
            'storageFile' => $stored->toArray(),
            'consent' => $consent,
            'video' => $queued,
            'processingProfile' => $profile->toWorkerPayload(),
        ];
    }

    /** @param array<string, mixed> $file */
    private function calculateVideoHash(array $file): ?string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path)) {
            return null;
        }

        return hash_file('sha256', $path) ?: null;
    }

    private function resolveProfile(?int $organizationId, ?string $planCode): \WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfile
    {
        if ($this->subscriptionProfiles !== null) {
            return $this->subscriptionProfiles->resolveForOrganization($organizationId, $planCode);
        }

        return $this->profiles->resolve($planCode);
    }

    private function resolveOrganizationId(UserContext $actor, string $organizationUuid): ?int
    {
        if ($actor->organizationId !== null) {
            return $actor->organizationId;
        }

        $organization = $this->organizations->findByUuid($organizationUuid);

        return $organization?->getId();
    }

    private function estimateUsageMb(int $sizeBytes): int
    {
        if ($sizeBytes <= 0) {
            return 1;
        }

        return max(1, (int) ceil($sizeBytes / 1048576));
    }

    private function workerReadablePath(string $storagePath): string
    {
        $path = trim($storagePath);
        if ($path === '') {
            return $path;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            return str_replace('\\', '/', $path);
        }

        $root = trim((string) (getenv('WorkEddy_VIDEO_WORKER_STORAGE_ROOT') ?: '/storage'));

        return rtrim(str_replace('\\', '/', $root), '/') . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    /** @param array<string, mixed> $file */
    private function validateVideo(array $file, int $durationSeconds, int $maxDurationSeconds): void
    {
        $errors = [];
        $name = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['mp4', 'mov', 'webm'], true)) {
            $errors['file'] = 'Unsupported video extension.';
        }
        $mimeType = strtolower((string) ($file['type'] ?? ''));
        if ($mimeType !== '' && !in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/webm'], true)) {
            $errors['mimeType'] = 'Unsupported video MIME type.';
        }
        if ((int) ($file['size'] ?? 0) <= 0) {
            $errors['sizeBytes'] = 'Video file is required.';
        }
        if ((int) ($file['size'] ?? 0) > $this->maxVideoSizeBytes()) {
            $errors['sizeBytes'] = 'Video file exceeds the configured upload size limit.';
        }
        if ($durationSeconds <= 0) {
            $errors['durationSeconds'] = 'Video duration must be greater than zero.';
        }
        if ($durationSeconds > $maxDurationSeconds) {
            $errors['durationSeconds'] = 'Video duration exceeds the processing profile limit.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function maxVideoSizeBytes(): int
    {
        $configured = $this->settings?->maxVideoSizeBytes() ?? 524288000;

        return $configured > 0 ? $configured : 524288000;
    }
}
