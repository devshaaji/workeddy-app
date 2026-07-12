<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Outbound;

use WorkEddy\Platform\Transport\TransportDispatcher;
use WorkEddy\Platform\Transport\TransportDispatchReport;

final class TransportOutboxDispatcher
{
    public function __construct(private readonly TransportDispatcher $dispatcher) {}

    public function runOnce(int $limit = 100): TransportDispatchReport
    {
        return $this->dispatcher->runOnce($limit);
    }
}
