<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class TransportInboundSource
{
    /**
     * @param list<string> $allowedTopics
     * @param list<string> $allowedIpRanges
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly bool $enabled,
        public readonly string $authType,
        public readonly ?string $secretHash,
        public readonly array $allowedTopics,
        public readonly array $allowedIpRanges,
        public readonly bool $requireSignature,
        public readonly string $signatureHeader,
        public readonly string $timestampHeader,
        public readonly int $maxClockSkewSeconds,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
