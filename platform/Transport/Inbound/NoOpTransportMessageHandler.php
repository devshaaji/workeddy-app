<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class NoOpTransportMessageHandler implements TransportMessageHandlerInterface
{
    /**
     * @param list<string> $topics
     */
    public function __construct(private readonly array $topics) {}

    public function supports(string $topic): bool
    {
        return in_array($topic, $this->topics, true);
    }

    public function handle(TransportInboxMessage $message): TransportProcessingResult
    {
        return TransportProcessingResult::success(['handled_by' => self::class], [
            'inbox_uuid' => $message->uuid,
            'topic' => $message->topic,
            'status' => 'processed',
        ]);
    }
}
