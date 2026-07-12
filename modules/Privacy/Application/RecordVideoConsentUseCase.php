<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class RecordVideoConsentUseCase
{
    public function __construct(
        private readonly IPrivacyRepository $privacy,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, UserContext $actor, string $textVersion, bool $acceptedNotice, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        if (!$acceptedNotice) {
            throw new ValidationException(['acceptedNotice' => 'Video consent notice must be accepted.']);
        }
        $textVersion = trim($textVersion);
        if ($textVersion === '') {
            throw new ValidationException(['textVersion' => 'Consent text version is required.']);
        }

        $record = $this->privacy->createConsent([
            'uuid' => UuidSupport::generate(),
            'organizationUuid' => UuidSupport::requireValid($organizationUuid, 'organizationUuid'),
            'assessmentUuid' => UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'),
            'storageFileUuid' => UuidSupport::requireValid($storageFileUuid, 'storageFileUuid'),
            'userId' => $actor->userId,
            'textVersion' => $textVersion,
            'acceptedNotice' => true,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'acceptedAt' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        $this->audit->record('privacy.video.consent_recorded', 'video_consent', (string) $record['uuid'], afterState: $record, actorId: (string) $actor->userId, actorType: 'user');

        return $record;
    }
}
