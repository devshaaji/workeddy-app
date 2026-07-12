<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Presentation;

use WorkEddy\Modules\Customer\Domain\Contracts\ICustomerRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Notification\Authorization\NotificationPermissions;
use WorkEddy\Modules\Notification\Contracts\InAppNotificationRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationPreferenceRepositoryInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;
use WorkEddy\Modules\Notification\Contracts\TemplateRendererInterface;
use WorkEddy\Modules\Notification\Domain\InAppNotification;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationPriority;
use WorkEddy\Modules\Notification\Domain\NotificationRecipient;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;
use WorkEddy\Modules\Notification\Domain\NotificationType;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class NotificationApiController
{
    public function __construct(
        private readonly NotificationLogRepositoryInterface $logRepository,
        private readonly NotificationServiceInterface $notificationService,
        private readonly SettingsService $settings,
        private readonly TemplateRendererInterface $renderer,
        private readonly NotificationPreferenceRepositoryInterface $preferences,
        private readonly InAppNotificationRepositoryInterface $inAppNotifications,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
    ) {}

    public function listLogs(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::LOG_VIEW);

        $limit = max(1, min(100, (int) ($request->query('limit') ?? 50)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));
        $result = $this->logRepository->paginate($limit, $offset);

        return Response::json([
            'status' => 'ok',
            'data' => array_map([$this, 'serializeLog'], $result['data']),
            'meta' => [
                'total' => $result['total'],
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function showLog(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::LOG_VIEW);

        $uuid = (string) ($request->routeParam('id') ?? '');
        $log = $this->logRepository->findByUuid($uuid);
        if ($log === null) {
            throw new NotFoundException('Notification log not found');
        }

        return Response::json([
            'status' => 'ok',
            'data' => $this->serializeLog($log),
        ]);
    }

    public function retryLog(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::LOG_RETRY);

        $uuid = (string) ($request->routeParam('id') ?? '');
        $log = $this->logRepository->findByUuid($uuid);
        if ($log === null) {
            throw new NotFoundException('Notification log not found');
        }

        $notificationRequest = new NotificationRequest(
            type: new NotificationType($log->notificationType),
            recipient: new NotificationRecipient(
                recipientId: $log->recipientId,
                recipientType: $log->recipientType,
                name: $log->recipientName,
                email: $log->recipientEmail,
                phone: $log->recipientPhone
            ),
            data: $log->metadataJson,
            priority: NotificationPriority::NORMAL,
            requiredChannel: $log->channel
        );

        $this->notificationService->send($notificationRequest);

        return Response::json([
            'status' => 'ok',
            'message' => 'Notification queued for retry.',
        ]);
    }

    public function getSettings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::SETTINGS_MANAGE);

        return Response::json([
            'status' => 'ok',
            'data' => [
                'values' => $this->settings->getAllForModule('notification'),
                'definitions' => array_map(static fn($definition): array => [
                    'key' => $definition->key,
                    'type' => $definition->type->value,
                    'default' => $definition->default,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'editable' => $definition->editable,
                    'sensitive' => $definition->sensitive,
                    'restartRequired' => $definition->restartRequired,
                ], $this->settings->getRegistry()->getForModule('notification')),
            ],
        ]);
    }

    public function updateSettings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::SETTINGS_MANAGE);

        $payload = array_replace($request->body, $request->json);
        $values = $payload['values'] ?? $payload;
        $allowed = array_flip([
            'default_from_email',
            'default_from_name',
            'default_reply_to_email',
            'default_reply_to_name',
            'queue_enabled',
            'http_timeout_seconds',
            'http_connect_timeout_seconds',
            'retry_max_attempts',
            'retry_delay_seconds',
            'fallback_enabled',
            'provider_list',
            'active_provider_per_channel',
        ]);

        $this->settings->setMany('notification', is_array($values) ? array_intersect_key($values, $allowed) : [], $ctx->userId);

        return $this->getSettings($request, $request->routeParams);
    }

    public function getMyPreferences(Request $request): Response
    {
        $ctx = $this->requireContext();
        [$recipientType, $recipientId] = $this->resolveRecipientIdentity($ctx->userId);
        $preferences = $this->preferences->findForRecipient($recipientType, $recipientId);

        return Response::json([
            'status' => 'ok',
            'data' => [
                'recipientType' => $recipientType,
                'recipientId' => $recipientId,
                'channels' => $preferences->channels(),
            ],
        ]);
    }

    public function updateMyPreferences(Request $request): Response
    {
        $ctx = $this->requireContext();
        [$recipientType, $recipientId] = $this->resolveRecipientIdentity($ctx->userId);
        $payload = array_replace($request->body, $request->json);
        $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : $payload;

        $this->preferences->saveForRecipient($recipientType, $recipientId, [
            'email' => (bool) ($channels['email'] ?? false),
            'sms' => (bool) ($channels['sms'] ?? false),
            'whatsapp' => (bool) ($channels['whatsapp'] ?? false),
            'inapp' => true,
        ]);

        return $this->getMyPreferences($request, $request->routeParams);
    }

    public function listInbox(Request $request): Response
    {
        $ctx = $this->requireContext();
        [$recipientType, $recipientId] = $this->resolveRecipientIdentity($ctx->userId);
        $limit = max(1, min(100, (int) ($request->query('limit') ?? 50)));
        $offset = max(0, (int) ($request->query('offset') ?? 0));
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOL);
        $result = $this->inAppNotifications->paginateForRecipient($recipientType, $recipientId, $limit, $offset, $unreadOnly);

        return Response::json([
            'status' => 'ok',
            'data' => array_map([$this, 'serializeInboxNotification'], $result['data']),
            'meta' => [
                'total' => $result['total'],
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function markInboxRead(Request $request): Response
    {
        $ctx = $this->requireContext();
        [$recipientType, $recipientId] = $this->resolveRecipientIdentity($ctx->userId);
        $uuid = (string) ($request->routeParam('id') ?? '');
        $notification = $this->inAppNotifications->markRead($uuid, $recipientType, $recipientId);

        if ($notification === null) {
            throw new NotFoundException('In-app notification not found');
        }

        return Response::json([
            'status' => 'ok',
            'data' => $this->serializeInboxNotification($notification),
        ]);
    }

    public function listTemplates(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::TEMPLATE_VIEW);

        $templates = [];
        $directory = new \RecursiveDirectoryIterator(__DIR__ . '/../Templates', \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            if (str_ends_with($filename, '.subject.php')) {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_\.]+)\.([a-z]+)\.php$/', $filename, $matches)) {
                $templates[] = [
                    'id' => $matches[1] . ':' . $matches[2],
                    'type' => $matches[1],
                    'channel' => $matches[2],
                    'filename' => $filename,
                ];
            }
        }

        return Response::json([
            'status' => 'ok',
            'data' => $templates,
        ]);
    }

    public function previewTemplate(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, NotificationPermissions::TEMPLATE_VIEW);

        $id = (string) ($request->routeParam('id') ?? '');
        $parts = explode(':', $id);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid template ID format');
        }

        $type = new NotificationType($parts[0]);
        $channel = NotificationChannel::from($parts[1]);
        $data = [
            'code' => '123456',
            'name' => 'John Doe',
            'amount' => '$10.00',
            'invoice_id' => 'INV-001',
            'reason' => 'Preview testing',
        ];

        try {
            return Response::json([
                'status' => 'ok',
                'data' => [
                    'subject' => $this->renderer->getSubject($type, $channel, $data),
                    'body' => $this->renderer->render($type, $channel, $data),
                ],
            ]);
        } catch (\Throwable $e) {
            throw new NotFoundException('Template could not be rendered: ' . $e->getMessage());
        }
    }

    private function serializeLog(object $log): array
    {
        return [
            'id' => $log->id,
            'uuid' => $log->uuid,
            'notificationType' => $log->notificationType,
            'recipientType' => $log->recipientType,
            'recipientId' => $log->recipientId,
            'recipientName' => $log->recipientName,
            'recipientEmail' => $log->recipientEmail,
            'recipientPhone' => $log->recipientPhone,
            'channel' => $log->channel->value,
            'provider' => $log->provider,
            'subject' => $log->subject,
            'messagePreview' => $log->messagePreview,
            'status' => $log->status,
            'attemptCount' => $log->attemptCount,
            'failureReason' => $log->failureReason,
            'metadata' => $log->metadataJson,
            'queuedAt' => $log->queuedAt?->format(\DateTimeInterface::ATOM),
            'sentAt' => $log->sentAt?->format(\DateTimeInterface::ATOM),
            'failedAt' => $log->failedAt?->format(\DateTimeInterface::ATOM),
            'createdAt' => $log->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeInboxNotification(InAppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'uuid' => $notification->uuid,
            'notificationType' => $notification->notificationType,
            'subject' => $notification->subject,
            'body' => $notification->body,
            'metadata' => $notification->metadataJson,
            'readAt' => $notification->readAt?->format(\DateTimeInterface::ATOM),
            'createdAt' => $notification->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function requireContext()
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRecipientIdentity(int|string $userId): array
    {
        return ['user', (string) $userId];
    }
}
