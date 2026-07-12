<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\User;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Shared\Exceptions\GatewayException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class IAMAuthNotificationDispatcher
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly NotificationServiceInterface $notifications,
        private readonly ConfigLoader $config,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('notification') ?? new NullLogger();
    }

    public function sendLoginOtp(User $user, string $otp, int $otpId, int $expiresInMinutes): void
    {
        $this->sendRequired(
            $user,
            'iam.auth_otp',
            [
                'otp' => $otp,
                'expiresInMinutes' => $expiresInMinutes,
            ],
            $this->metadata('iam.auth_otp', (int) $user->getId(), 'login_otp', $otpId),
        );
    }

    public function sendPasswordReset(User $user, string $resetCode, int|string $otpId, int $expiresInMinutes): void
    {
        $userId = (int) $user->getId();
        $this->sendRequired(
            $user,
            'iam.password_reset',
            [
                'resetUrl' => $this->resetUrl($userId, $resetCode),
                'expiresInMinutes' => $expiresInMinutes,
            ],
            $this->metadata('iam.password_reset', $userId, 'password_reset', $otpId),
        );
    }

    public function sendPasswordResetCompleted(User $user): void
    {
        $userId = (int) $user->getId();
        try {
            $request = new NotificationRequest(
                type: new NotificationType('iam.password_reset_completed'),
                recipient: new NotificationRecipient(
                    recipientType: 'user',
                    recipientId: (string) $userId,
                    name: $user->getFullName(),
                    email: $user->getEmail(),
                    phone: $user->getPhone()
                ),
                data: [],
                metadata: $this->metadata('iam.password_reset_completed', $userId, 'password_reset_completed', 0)
            );
            $this->notifications->send($request);
        } catch (\Throwable $e) {
            $this->logger->warning('IAM password reset completion notification enqueue failed.', [
                'recipientUserId' => $userId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $metadata
     */
    private function sendRequired(User $user, string $groupKey, array $context, array $metadata): void
    {
        try {
            $request = new NotificationRequest(
                type: new NotificationType($groupKey),
                recipient: new NotificationRecipient(
                    recipientType: 'user',
                    recipientId: (string) $user->getId(),
                    name: $user->getFullName(),
                    email: $user->getEmail(),
                    phone: $user->getPhone()
                ),
                data: $context,
                metadata: $metadata
            );
            $this->notifications->send($request);
        } catch (\Throwable $e) {
            $this->logger->error('IAM auth notification enqueue failed.', [
                'groupKey' => $groupKey,
                'recipientUserId' => $user->getId(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new GatewayException('Notification', 'Could not queue authentication notification.');
        }
    }

    /** @return array<string, string> */
    private function metadata(string $type, int $userId, string $purpose, int|string $otpId): array
    {
        return [
            'type' => $type,
            'sourceModule' => 'IAM',
            'sourceEntityType' => 'User',
            'sourceEntityId' => (string) $userId,
            'dedupeKey' => implode(':', [$type, $userId, $purpose, (string) $otpId]),
        ];
    }

    private function resetUrl(int $userId, string $resetCode): string
    {
        return rtrim((string) $this->config->get('app.url', 'http://localhost'), '/')
            . '/reset-password?userId=' . rawurlencode((string) $userId)
            . '&code=' . rawurlencode($resetCode);
    }
}
