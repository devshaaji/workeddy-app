<?php

/**
 * OTP repository — manages iam_otp_challenges for MFA.
 *
 * Internal to IAM module. Not exposed via contract.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Infrastructure;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\UuidSupport;
use Doctrine\DBAL\Connection;

final class OTPRepository
{
    private const OTP_EXPIRY_MINUTES = 10;
    public const PURPOSE_LOGIN = 'login_otp';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';
    private const HASH_PREFIX = 'hmac-sha256:';

    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigLoader $config,
        private readonly IClock $clock,
    ) {}

    /**
     * Create a new OTP record and return the generated challenge UUID.
     */
    public function create(int|string $userId, string $code, string $purpose = self::PURPOSE_LOGIN): string
    {
        $purpose = $this->normalizePurpose($purpose);
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTimeImmutable('+' . self::OTP_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

        $challengeId = UuidSupport::generate();
        $this->connection->insert('iam_otp_challenges', [
            'challenge_id' => $challengeId,
            'user_id'      => (int) $userId,
            'purpose'      => $purpose,
            'code_hash'    => $this->hashCode((int) $userId, $purpose, $code),
            'expires_at'   => $expiresAt,
            'created_at'   => $nowStr,
            'updated_at'   => $nowStr,
        ]);
        return $challengeId;
    }

    /**
     * Find the latest valid (unused + non-expired) OTP for a user.
     *
     * @return array{id: string, code: string, expires_at: string, purpose: string}|null
     */
    public function findLatestValid(int|string $userId, ?string $purpose = null): ?array
    {
        $params = [(int) $userId];
        $purposeSql = '';
        if ($purpose !== null) {
            $purposeSql = ' AND purpose = ?';
            $params[] = $this->normalizePurpose($purpose);
        }

        $row = $this->connection->fetchAssociative(
            'SELECT challenge_id AS id, code_hash AS code, expires_at, purpose
             FROM iam_otp_challenges
             WHERE user_id = ?
               AND consumed_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
               ' . $purposeSql . '
             ORDER BY created_at DESC
             LIMIT 1',
            $params
        );

        if (!$row) {
            return null;
        }

        return [
            'id'         => (string) $row['id'],
            'code'       => (string) $row['code'],
            'expires_at' => (string) $row['expires_at'],
            'purpose'    => (string) $row['purpose'],
        ];
    }

    /**
     * @return array{id: string, code: string, expires_at: string, purpose: string}|null
     */
    public function verifyLatestValid(int|string $userId, string $purpose, string $code): ?array
    {
        $otp = $this->findLatestValid($userId, $purpose);
        if ($otp === null) {
            return null;
        }

        return $this->matches((int) $userId, $this->normalizePurpose($purpose), trim($code), (string) $otp['code'])
            ? $otp
            : null;
    }

    /**
     * Mark an OTP as used.
     */
    public function markUsed(int|string $otpId): void
    {
        $this->connection->update('iam_otp_challenges', [
            'consumed_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
            'updated_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
        ], ['challenge_id' => (string) $otpId]);
    }

    /**
     * Invalidate all unused OTPs for a user.
     */
    public function invalidateAll(int|string $userId, ?string $purpose = null): void
    {
        $nowStr = ($this->clock->now())->format('Y-m-d H:i:s');
        $params = [(int) $userId];
        $purposeSql = '';
        if ($purpose !== null) {
            $purposeSql = ' AND purpose = ?';
            $params[] = $this->normalizePurpose($purpose);
        }

        $this->connection->executeStatement(
            'UPDATE iam_otp_challenges SET consumed_at = ?, updated_at = ? WHERE user_id = ? AND consumed_at IS NULL' . $purposeSql,
            array_merge([$nowStr, $nowStr], $params)
        );
    }

    public function expiryMinutes(): int
    {
        return self::OTP_EXPIRY_MINUTES;
    }

    private function matches(int $userId, string $purpose, string $plainCode, string $storedCode): bool
    {
        $expected = $this->hashCode($userId, $purpose, $plainCode);
        return hash_equals($storedCode, $expected);
    }

    private function hashCode(int $userId, string $purpose, string $code): string
    {
        return self::HASH_PREFIX . hash_hmac('sha256', $purpose . '|' . $userId . '|' . trim($code), $this->hashSecret());
    }

    private function hashSecret(): string
    {
        $secret = trim((string) ($_ENV['APP_KEY'] ?? $this->config->get('app.key', '')));
        if ($secret !== '') {
            return $secret;
        }

        return hash('sha256', (string) $this->config->get('app.url', 'nisepa-v2'));
    }

    private function normalizePurpose(string $purpose): string
    {
        $purpose = trim($purpose);
        if (in_array($purpose, [self::PURPOSE_LOGIN, self::PURPOSE_PASSWORD_RESET], true)) {
            return $purpose;
        }

        throw new \InvalidArgumentException('Unsupported OTP purpose.');
    }
}
