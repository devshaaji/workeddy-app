<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

interface TransportInboundSourceRepository
{
    public function save(TransportInboundSource $source): TransportInboundSource;

    public function findByName(string $name): ?TransportInboundSource;
}
