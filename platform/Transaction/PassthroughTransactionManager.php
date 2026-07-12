<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transaction;

final class PassthroughTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $callback): mixed
    {
        return $callback();
    }
}
