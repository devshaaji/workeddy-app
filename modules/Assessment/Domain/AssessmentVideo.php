<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Domain;

final class AssessmentVideo
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $uuid,
        private readonly int $assessmentId,
        private readonly string $storageFileUuid,
        private readonly string $originalFilename,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly int $durationSeconds,
        private readonly string $consentTextVersion,
        private readonly bool $faceBlurRequested,
        private readonly string $processingStatus = 'pending',
        private readonly ?string $processingStartedAt = null,
        private readonly ?string $processingCompletedAt = null,
        private readonly ?string $processingError = null,
        private readonly ?string $thumbnailStorageFileUuid = null,
        private readonly ?string $poseVideoStorageFileUuid = null,
        private readonly bool $facesBlurred = false,
        private readonly ?float $processingConfidence = null,
        private readonly ?string $createdAt = null,
        private readonly ?string $blurredStorageFileUuid = null,
    ) {}

    public function withId(int $id): self
    {
        return new self($id, $this->uuid, $this->assessmentId, $this->storageFileUuid, $this->originalFilename, $this->mimeType, $this->sizeBytes, $this->durationSeconds, $this->consentTextVersion, $this->faceBlurRequested, $this->processingStatus, $this->processingStartedAt, $this->processingCompletedAt, $this->processingError, $this->thumbnailStorageFileUuid, $this->poseVideoStorageFileUuid, $this->facesBlurred, $this->processingConfidence, $this->createdAt, $this->blurredStorageFileUuid);
    }

    public function markQueued(): self
    {
        return $this->withProcessing(status: 'queued', error: null);
    }

    public function markProcessing(?string $startedAt = null): self
    {
        return $this->withProcessing(status: 'processing', startedAt: $startedAt, error: null);
    }

    public function markCompleted(?string $poseVideoStorageFileUuid, ?string $thumbnailStorageFileUuid, bool $facesBlurred, ?float $processingConfidence, ?string $completedAt = null, ?string $blurredStorageFileUuid = null): self
    {
        return $this->withProcessing(
            status: 'completed',
            completedAt: $completedAt,
            error: null,
            poseVideoStorageFileUuid: $poseVideoStorageFileUuid,
            thumbnailStorageFileUuid: $thumbnailStorageFileUuid,
            facesBlurred: $facesBlurred,
            processingConfidence: $processingConfidence,
            blurredStorageFileUuid: $blurredStorageFileUuid,
        );
    }

    public function markFailed(string $error, ?string $completedAt = null): self
    {
        return $this->withProcessing(status: 'failed', completedAt: $completedAt, error: trim($error) !== '' ? trim($error) : 'Processing failed.');
    }

    private function withProcessing(
        string $status,
        ?string $startedAt = null,
        ?string $completedAt = null,
        ?string $error = null,
        ?string $poseVideoStorageFileUuid = null,
        ?string $thumbnailStorageFileUuid = null,
        bool $facesBlurred = false,
        ?float $processingConfidence = null,
        ?string $blurredStorageFileUuid = null,
    ): self {
        return new self(
            $this->id,
            $this->uuid,
            $this->assessmentId,
            $this->storageFileUuid,
            $this->originalFilename,
            $this->mimeType,
            $this->sizeBytes,
            $this->durationSeconds,
            $this->consentTextVersion,
            $this->faceBlurRequested,
            $status,
            $startedAt ?? $this->processingStartedAt,
            $completedAt ?? $this->processingCompletedAt,
            $error,
            $thumbnailStorageFileUuid ?? $this->thumbnailStorageFileUuid,
            $poseVideoStorageFileUuid ?? $this->poseVideoStorageFileUuid,
            $facesBlurred || $this->facesBlurred,
            $processingConfidence ?? $this->processingConfidence,
            $this->createdAt,
            $blurredStorageFileUuid ?? $this->blurredStorageFileUuid,
        );
    }

    public function withBlurredStorageFileUuid(?string $blurredStorageFileUuid): self
    {
        return new self(
            $this->id,
            $this->uuid,
            $this->assessmentId,
            $this->storageFileUuid,
            $this->originalFilename,
            $this->mimeType,
            $this->sizeBytes,
            $this->durationSeconds,
            $this->consentTextVersion,
            $this->faceBlurRequested,
            $this->processingStatus,
            $this->processingStartedAt,
            $this->processingCompletedAt,
            $this->processingError,
            $this->thumbnailStorageFileUuid,
            $this->poseVideoStorageFileUuid,
            $this->facesBlurred,
            $this->processingConfidence,
            $this->createdAt,
            $blurredStorageFileUuid,
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getUuid(): string { return $this->uuid; }
    public function getAssessmentId(): int { return $this->assessmentId; }
    public function getStorageFileUuid(): string { return $this->storageFileUuid; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function getDurationSeconds(): int { return $this->durationSeconds; }
    public function getConsentTextVersion(): string { return $this->consentTextVersion; }
    public function isFaceBlurRequested(): bool { return $this->faceBlurRequested; }
    public function getProcessingStatus(): string { return $this->processingStatus; }
    public function getProcessingStartedAt(): ?string { return $this->processingStartedAt; }
    public function getProcessingCompletedAt(): ?string { return $this->processingCompletedAt; }
    public function getProcessingError(): ?string { return $this->processingError; }
    public function getThumbnailStorageFileUuid(): ?string { return $this->thumbnailStorageFileUuid; }
    public function getPoseVideoStorageFileUuid(): ?string { return $this->poseVideoStorageFileUuid; }
    public function areFacesBlurred(): bool { return $this->facesBlurred; }
    public function getProcessingConfidence(): ?float { return $this->processingConfidence; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getBlurredStorageFileUuid(): ?string { return $this->blurredStorageFileUuid; }

    /**
     * @return array<string, mixed>
     */
    public function toView(): array
    {
        return [
            'uuid' => $this->uuid,
            'storageFileUuid' => $this->storageFileUuid,
            'originalFilename' => $this->originalFilename,
            'mimeType' => $this->mimeType,
            'sizeBytes' => $this->sizeBytes,
            'durationSeconds' => $this->durationSeconds,
            'consentTextVersion' => $this->consentTextVersion,
            'faceBlurRequested' => $this->faceBlurRequested,
            'processingStatus' => $this->processingStatus,
            'processingStartedAt' => $this->processingStartedAt,
            'processingCompletedAt' => $this->processingCompletedAt,
            'processingError' => $this->processingError,
            'thumbnailStorageFileUuid' => $this->thumbnailStorageFileUuid,
            'poseVideoStorageFileUuid' => $this->poseVideoStorageFileUuid,
            'blurredStorageFileUuid' => $this->blurredStorageFileUuid,
            'facesBlurred' => $this->facesBlurred,
            'processingConfidence' => $this->processingConfidence,
            'createdAt' => $this->createdAt,
        ];
    }
}
