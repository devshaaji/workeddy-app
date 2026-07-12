<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Domain;

use WorkEddy\Shared\Exceptions\ValidationException;

final class RetentionPolicy
{
    public const RAW_RETAIN_FOR_REVIEW = 'retain_for_review';
    public const RAW_DELETE_AFTER_PROCESSING = 'delete_after_processing';
    public const RAW_RETAIN_DEIDENTIFIED_ONLY = 'retain_deidentified_only';

    public function __construct(
        public readonly ?int $id,
        public readonly int $organizationId,
        public readonly string $organizationUuid,
        public readonly string $rawVideoPolicy,
        public readonly bool $retainScreenshotsOnly,
        public readonly bool $retainForPilotEvidence,
        public readonly int $retentionDays,
        public readonly int $updatedBy,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
        if (!in_array($rawVideoPolicy, [self::RAW_RETAIN_FOR_REVIEW, self::RAW_DELETE_AFTER_PROCESSING, self::RAW_RETAIN_DEIDENTIFIED_ONLY], true)) {
            throw new ValidationException(['rawVideoPolicy' => 'Unsupported raw video retention policy.']);
        }
        if ($retentionDays < 0) {
            throw new ValidationException(['retentionDays' => 'Retention days cannot be negative.']);
        }
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'organizationUuid' => $this->organizationUuid,
            'rawVideoPolicy' => $this->rawVideoPolicy,
            'retainScreenshotsOnly' => $this->retainScreenshotsOnly,
            'retainForPilotEvidence' => $this->retainForPilotEvidence,
            'retentionDays' => $this->retentionDays,
            'updatedBy' => $this->updatedBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
