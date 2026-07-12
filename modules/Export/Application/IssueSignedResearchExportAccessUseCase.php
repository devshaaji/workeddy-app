<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Application;

use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Authorization\ExportPermissions;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class IssueSignedResearchExportAccessUseCase
{
    public function __construct(
        private readonly IResearchExportRepository $exports,
        private readonly ExportSettings $settings,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly IPermissionService $permissions,
        private readonly string $secret = '',
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $exportUuid, UserContext $actor, string $purpose = 'download'): array
    {
        $this->permissions->requirePrivilege($actor, ExportPermissions::DOWNLOAD);

        $export = $this->exports->findByUuid($exportUuid);
        if ($export === null) {
            throw new NotFoundException('Research export not found.');
        }
        if (($actor->organizationUuid ?? null) !== null && $actor->organizationUuid !== '' && $export->organizationUuid !== $actor->organizationUuid) {
            throw new NotFoundException('Research export not found.');
        }
        if ($export->storageFileUuid === null) {
            throw new NotFoundException('Research export file is not ready.');
        }

        $ttlSeconds = max(60, min(3600, $this->settings->signedLinkTtlMinutes() * 60));
        $claims = [
            'export_uuid' => $export->uuid,
            'storage_file_uuid' => $export->storageFileUuid,
            'organization_uuid' => $export->organizationUuid,
            'dataset' => $export->dataset,
            'format' => $export->format,
            'actor_id' => $actor->userId,
            'purpose' => trim($purpose) !== '' ? trim($purpose) : 'download',
            'expires_at' => $this->clock->now()->getTimestamp() + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ];
        $token = $this->encode($claims);
        $result = [
            'exportUuid' => $export->uuid,
            'signedUrl' => '/api/v1/research-exports/signed-access/' . rawurlencode($token),
            'expiresAt' => date('Y-m-d H:i:s', (int) $claims['expires_at']),
        ];

        $this->audit->record(
            'export.research.signed_access_issued',
            'research_export',
            $export->uuid,
            afterState: $result,
            actorId: (string) $actor->userId,
            actorType: 'user',
            metadata: ['purpose' => $claims['purpose'], 'storageFileUuid' => $export->storageFileUuid],
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
