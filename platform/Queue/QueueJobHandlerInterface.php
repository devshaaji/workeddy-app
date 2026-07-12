<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Queue;

interface QueueJobHandlerInterface
{
    public function handle(QueueJob $job): void;
}
