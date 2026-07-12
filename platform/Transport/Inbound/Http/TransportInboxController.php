<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound\Http;

use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Transport\Inbound\TransportReceiverService;

final class TransportInboxController
{
    public function __construct(private readonly TransportReceiverService $receiver) {}

    public function receive(Request $request): Response
    {
        $payload = $request->json;
        $source = (string) ($payload['source'] ?? '');
        $topic = (string) ($payload['topic'] ?? '');
        $bodyPayload = $payload['payload'] ?? [];
        if ($source === '' || $topic === '' || !is_array($bodyPayload)) {
            return Response::json([
                'success' => false,
                'duplicate' => false,
                'rejected' => true,
                'status' => 'rejected',
                'error_message' => 'source, topic, and object payload are required.',
            ], 422);
        }

        $result = $this->receiver->receive(
            source: $source,
            topic: $topic,
            payload: $bodyPayload,
            headers: is_array($payload['headers'] ?? null) ? $payload['headers'] : $request->headers,
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            correlationId: isset($payload['correlation_id']) ? (string) $payload['correlation_id'] : null,
            remoteMessageId: isset($payload['remote_message_id']) ? (string) $payload['remote_message_id'] : null,
            rawMessage: $payload,
            receivedAckRequired: (bool) ($payload['received_ack_required'] ?? true),
            processedAckRequired: (bool) ($payload['processed_ack_required'] ?? false),
        );

        return Response::json([
            'success' => $result->success,
            'duplicate' => $result->duplicate,
            'rejected' => $result->rejected,
            'inbox_message_id' => $result->inboxMessageId,
            'inbox_uuid' => $result->inboxUuid,
            'status' => $result->status,
            'error_message' => $result->errorMessage,
        ], $result->rejected ? 403 : 202);
    }

    public function receiveBatch(Request $request): Response
    {
        $messages = $request->json['messages'] ?? [];
        if (!is_array($messages)) {
            return Response::json([
                'success' => false,
                'error_message' => 'messages must be an array.',
            ], 422);
        }

        $results = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $payload = $message['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }
            $result = $this->receiver->receive(
                source: (string) ($message['source'] ?? ''),
                topic: (string) ($message['topic'] ?? ''),
                payload: $payload,
                headers: is_array($message['headers'] ?? null) ? $message['headers'] : [],
                idempotencyKey: isset($message['idempotency_key']) ? (string) $message['idempotency_key'] : null,
                correlationId: isset($message['correlation_id']) ? (string) $message['correlation_id'] : null,
                remoteMessageId: isset($message['remote_message_id']) ? (string) $message['remote_message_id'] : null,
                rawMessage: $message,
                receivedAckRequired: (bool) ($message['received_ack_required'] ?? true),
                processedAckRequired: (bool) ($message['processed_ack_required'] ?? false),
            );
            $results[] = [
                'success' => $result->success,
                'duplicate' => $result->duplicate,
                'rejected' => $result->rejected,
                'inbox_message_id' => $result->inboxMessageId,
                'inbox_uuid' => $result->inboxUuid,
                'status' => $result->status,
                'error_message' => $result->errorMessage,
            ];
        }

        return Response::json(['success' => true, 'results' => $results], 202);
    }
}
