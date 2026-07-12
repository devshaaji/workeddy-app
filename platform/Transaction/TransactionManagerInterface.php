<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transaction;

interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
