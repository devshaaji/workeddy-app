<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Notification\Contracts\ChannelResolverInterface;
use WorkEddy\Modules\Notification\Contracts\NotificationPreferenceRepositoryInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;

final class ResolveRecipientNotificationChannels
{
    public function __construct(
        private readonly ChannelResolverInterface $channelResolver,
        private readonly NotificationPreferenceRepositoryInterface $preferences,
    ) {}

    /**
     * @return list<NotificationChannel>
     */
    public function resolve(NotificationRequest $request): array
    {
        if ($request->requiredChannel !== null) {
            return [$request->requiredChannel];
        }

        $preference = $this->preferences->findForRecipient(
            $request->recipient->recipientType,
            $request->recipient->recipientId,
        );

        $channels = [];

        if ($request->preferredChannel !== null && $preference->allows($request->preferredChannel)) {
            $channels[] = $request->preferredChannel;
        }

        foreach ($this->channelResolver->resolve($request->type) as $channel) {
            if ($preference->allows($channel)) {
                $channels[] = $channel;
            }
        }

        $unique = [];
        foreach ($channels as $channel) {
            $unique[$channel->value] = $channel;
        }

        return array_values($unique);
    }
}
