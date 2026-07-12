<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Application;

use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Support\UuidSupport;

final class IssueSignedVideoAccessUseCase
{
    public function __construct(
        private readonly IAuditService $audit,
        private readonly IClock $clock,
        private readonly string $secret = '',
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $organizationUuid, string $assessmentUuid, string $storageFileUuid, UserContext $actor, string $purpose, int $ttlSeconds = 300): array
    {
        $ttlSeconds = max(30, min(900, $ttlSeconds));
        $claims = [
            'organization_uuid' => UuidSupport::requireValid($organizationUuid, 'organizationUuid'),
            'assessment_uuid' => UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'),
            'storage_file_uuid' => UuidSupport::requireValid($storageFileUuid, 'storageFileUuid'),
            'actor_id' => $actor->userId,
            'purpose' => trim($purpose) !== '' ? trim($purpose) : 'review',
            'expires_at' => $this->clock->now()->getTimestamp() + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ];
        $token = $this->encode($claims);
        $result = [
            'token' => $token,
            'signedUrl' => '/api/v1/privacy/signed-video-access/' . rawurlencode($token),
            'expiresAt' => date('Y-m-d H:i:s', (int) $claims['expires_at']),
        ];

        $this->audit->record('privacy.video.signed_access_issued', 'stored_file', $storageFileUuid, afterState: $claims, actorId: (string) $actor->userId, actorType: 'user');

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
        $secret = $this->secret !== '' ? $this->secret : (string) (getenv('APP_KEY') ?: getenv('WORKER_API_TOKEN') ?: 'workeddy-local-signing-key');

        return $secret;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
