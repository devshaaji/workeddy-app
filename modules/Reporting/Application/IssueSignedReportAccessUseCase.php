<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application;

use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Reporting\Settings\ReportingSettings;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class IssueSignedReportAccessUseCase
{
    public function __construct(
        private readonly IReportArtifactRepository $artifacts,
        private readonly ReportingSettings $settings,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly string $secret = '',
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $artifactUuid, UserContext $actor, string $purpose = 'download'): array
    {
        $artifact = $this->artifacts->findByUuid($artifactUuid);
        if ($artifact === null) {
            throw new NotFoundException('Report artifact not found.');
        }

        $ttlSeconds = max(60, min(3600, $this->settings->downloadLinkTtlMinutes() * 60));
        $claims = [
            'artifact_uuid' => $artifact->uuid,
            'storage_file_uuid' => $artifact->storageFileUuid,
            'report_type' => $artifact->reportType,
            'source_uuid' => $artifact->sourceUuid,
            'format' => $artifact->format,
            'actor_id' => $actor->userId,
            'purpose' => trim($purpose) !== '' ? trim($purpose) : 'download',
            'expires_at' => $this->clock->now()->getTimestamp() + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ];
        $token = $this->encode($claims);
        $result = [
            'artifactUuid' => $artifact->uuid,
            'reportType' => $artifact->reportType,
            'format' => $artifact->format,
            'signedUrl' => '/api/v1/reporting/signed-access/' . rawurlencode($token),
            'expiresAt' => date('Y-m-d H:i:s', (int) $claims['expires_at']),
        ];

        $this->audit->record(
            'reporting.report.signed_access_issued',
            'report_artifact',
            $artifact->uuid,
            afterState: $result,
            actorId: (string) $actor->userId,
            actorType: 'user',
            metadata: [
                'storageFileUuid' => $artifact->storageFileUuid,
                'sourceUuid' => $artifact->sourceUuid,
                'purpose' => $claims['purpose'],
            ],
        );

        return $result;
    }

    /** @param array<string, mixed> $claims */
    private function encode(array $claims): string
    {
        $body = self::base64Url(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = self::base64Url(hash_hmac('sha256', $body, $this->resolvedSecret(), true));

        return $body . '.' . $signature;
    }

    private function resolvedSecret(): string
    {
        return $this->secret !== '' ? $this->secret : (string) (getenv('APP_KEY') ?: getenv('WORKER_API_TOKEN') ?: 'workeddy-local-signing-key');
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
