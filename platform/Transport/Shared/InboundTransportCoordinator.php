<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared;

final class InboundTransportCoordinator
{
    public function __construct(private readonly TransportCapabilityService $capabilities) {}

    public function chooseModeForRemote(TransportCapability $remote, ?string $preferredMode = null): TransportModeSelection
    {
        return $this->capabilities->selectMode($remote, $preferredMode);
    }
}
