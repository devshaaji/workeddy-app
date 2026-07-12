<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
