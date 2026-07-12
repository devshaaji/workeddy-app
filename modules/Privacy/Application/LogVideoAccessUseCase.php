<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class LogVideoAccessUseCase
{
    public function __construct(
        private readonly IPrivacyRepository $privacy,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, UserContext $actor, string $purpose, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $purpose = trim($purpose);
        if ($purpose === '') {
            throw new ValidationException(['purpose' => 'Video access purpose is required.']);
        }

        $record = $this->privacy->createVideoAccessLog([
            'uuid' => UuidSupport::generate(),
            'organizationUuid' => UuidSupport::requireValid($organizationUuid, 'organizationUuid'),
            'assessmentUuid' => UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'),
            'storageFileUuid' => UuidSupport::requireValid($storageFileUuid, 'storageFileUuid'),
            'userId' => $actor->userId,
            'purpose' => $purpose,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'accessedAt' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        $this->audit->record('privacy.video.access_logged', 'video_access_log', (string) $record['uuid'], afterState: $record, actorId: (string) $actor->userId, actorType: 'user');

        return $record;
    }
}
