<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

final class InboundSourceValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?TransportInboundSource $source,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
    ) {}
}
