<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Shared\Exceptions\ValidationException;

final class ReadSignedVideoAccessUseCase
{
    public function __construct(
        private readonly IStorageService $storage,
        private readonly IClock $clock,
        private readonly IAuditService $audit,
        private readonly string $secret = '',
    ) {}

    /** @return array{body:string,mimeType:string,filename:string} */
    public function execute(string $token): array
    {
        $claims = $this->decode($token);
        if ((int) ($claims['expires_at'] ?? 0) < $this->clock->now()->getTimestamp()) {
            throw new ForbiddenException('Signed video access link has expired.');
        }

        $uuid = (string) ($claims['storage_file_uuid'] ?? '');
        $file = $this->storage->findByUuid($uuid);
        if (!str_starts_with((string) $file->mimeType, 'video/')) {
            throw new ValidationException(['file' => 'Signed video access is only valid for video files.']);
        }
        $this->audit->record(
            'privacy.video.signed_access_streamed',
            'stored_file',
            $file->uuid,
            afterState: [
                'storageFileUuid' => $file->uuid,
                'assessmentUuid' => $claims['assessment_uuid'] ?? null,
                'organizationUuid' => $claims['organization_uuid'] ?? null,
                'purpose' => $claims['purpose'] ?? null,
            ],
            actorId: isset($claims['actor_id']) ? (string) $claims['actor_id'] : null,
            actorType: 'user',
        );

        return [
            'body' => $this->storage->read($file->uuid),
            'mimeType' => $file->mimeType ?? 'application/octet-stream',
            'filename' => $file->originalName,
        ];
    }

    /** @return array<string, mixed> */
    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new ForbiddenException('Invalid signed video access token.');
        }

        [$body, $signature] = $parts;
        $expected = self::base64Url(hash_hmac('sha256', $body, $this->resolvedSecret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new ForbiddenException('Invalid signed video access token.');
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        $claims = $json !== false ? json_decode($json, true) : null;
        if (!is_array($claims)) {
            throw new ForbiddenException('Invalid signed video access token.');
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
