<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Settings\IAMSettings;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Platform\Logging\ILoggerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class IAMNotificationDispatcher
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly NotificationServiceInterface $notifications,
        private readonly IUserRepository $userRepository,
        private readonly IAMSettings $settings,
        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('notification') ?? new NullLogger();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function userEvent(
        string $event,
        int $recipientUserId,
        int $sourceUserId,
        string $sourceUserUuid,
        int $actorUserId,
        array $context = [],
    ): void {
        $this->send(
            groupKey: 'iam.' . $event,
            recipientUserId: $recipientUserId,
            context: $context,
            metadata: $this->metadata('iam.' . $event, 'User', $sourceUserUuid, $recipientUserId, $actorUserId, $context),
        );
    }


    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $metadata
     */
    private function send(string $groupKey, int $recipientUserId, array $context, array $metadata): void
    {
        if ($recipientUserId <= 0) {
            return;
        }

        try {
            $user = $this->userRepository->findById($recipientUserId);
            if (!$user) {
                $this->logger->warning('IAM notification enqueue failed: User not found.', [
                    'recipientUserId' => $recipientUserId
                ]);
                return;
            }

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
            $this->logger->warning('IAM notification enqueue failed.', [
                'groupKey' => $groupKey,
                'recipientUserId' => $recipientUserId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function metadata(
        string $type,
        string $sourceEntityType,
        string $sourceEntityId,
        int $recipientUserId,
        int $actorUserId,
        array $context,
    ): array {
        $encodedContext = json_encode($context);
        $contextHash = sha1($encodedContext === false ? '' : $encodedContext);

        return [
            'type' => $type,
            'sourceModule' => 'IAM',
            'sourceEntityType' => $sourceEntityType,
            'sourceEntityId' => $sourceEntityId,
            'dedupeKey' => implode(':', [
                $type,
                $sourceEntityType,
                $sourceEntityId,
                $recipientUserId,
                $actorUserId,
                $contextHash,
            ]),
        ];
    }
}
