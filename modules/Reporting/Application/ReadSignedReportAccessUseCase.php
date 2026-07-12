<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application;

use WorkEddy\Modules\Reporting\Domain\Contracts\IReportArtifactRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class ReadSignedReportAccessUseCase
{
    public function __construct(
        private readonly IReportArtifactRepository $artifacts,
        private readonly IStorageService $storage,
        private readonly IClock $clock,
        private readonly IAuditService $audit,
        private readonly string $secret = '',
    ) {}

    /** @return array{body:string,mimeType:string,filename:string,disposition:string} */
    public function execute(string $token): array
    {
        $claims = $this->decode($token);
        if ((int) ($claims['expires_at'] ?? 0) < $this->clock->now()->getTimestamp()) {
            throw new ForbiddenException('Signed report access link has expired.');
        }

        $artifactUuid = (string) ($claims['artifact_uuid'] ?? '');
        $storageFileUuid = (string) ($claims['storage_file_uuid'] ?? '');
        $artifact = $this->artifacts->findByUuid($artifactUuid);
        if ($artifact === null || $artifact->storageFileUuid !== $storageFileUuid) {
            throw new NotFoundException('Report artifact not found.');
        }

        $file = $this->storage->findByUuid($storageFileUuid);
        if (!in_array((string) $file->mimeType, ['application/pdf', 'text/csv', 'text/plain'], true)) {
            throw new ValidationException(['file' => 'Signed report access is only valid for report files.']);
        }

        $this->audit->record(
            'reporting.report.signed_access_streamed',
            'report_artifact',
            $artifact->uuid,
            afterState: [
                'artifactUuid' => $artifact->uuid,
                'storageFileUuid' => $artifact->storageFileUuid,
                'reportType' => $artifact->reportType,
                'sourceUuid' => $artifact->sourceUuid,
                'format' => $artifact->format,
                'purpose' => $claims['purpose'] ?? null,
            ],
            actorId: isset($claims['actor_id']) ? (string) $claims['actor_id'] : null,
            actorType: 'user',
        );

        return [
            'body' => $this->storage->read($file->uuid),
            'mimeType' => $file->mimeType ?? 'application/octet-stream',
            'filename' => $file->originalName,
            'disposition' => $artifact->format === 'pdf' ? 'attachment' : 'attachment',
        ];
    }

    /** @return array<string, mixed> */
    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new ForbiddenException('Invalid signed report access token.');
        }

        [$body, $signature] = $parts;
        $expected = self::base64Url(hash_hmac('sha256', $body, $this->resolvedSecret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new ForbiddenException('Invalid signed report access token.');
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        $claims = $json !== false ? json_decode($json, true) : null;
        if (!is_array($claims)) {
            throw new ForbiddenException('Invalid signed report access token.');
        }

        return $claims;
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
