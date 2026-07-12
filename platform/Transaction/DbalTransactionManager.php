<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transaction;

final class DbalTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly object $connection) {}

    public function transactional(callable $callback): mixed
    {
        if (method_exists($this->connection, 'isTransactionActive') && $this->connection->isTransactionActive()) {
            return $callback();
        }

        if (method_exists($this->connection, 'transactional')) {
            return $this->connection->transactional($callback);
        }

        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
