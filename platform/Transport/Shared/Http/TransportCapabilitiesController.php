<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Shared\Http;

use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Transport\Shared\TransportCapabilityService;

final class TransportCapabilitiesController
{
    public function __construct(private readonly TransportCapabilityService $capabilities) {}

    public function show(): Response
    {
        return Response::json($this->capabilities->localCapabilities()->toArray());
    }
}
