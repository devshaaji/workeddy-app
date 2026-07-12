<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Logging;

use Psr\Log\LoggerInterface;

interface ILoggerFactory
{
    public function channel(string $name): LoggerInterface;
}
