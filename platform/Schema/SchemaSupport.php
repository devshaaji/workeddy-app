<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema;

use Doctrine\DBAL\Schema\Table;

abstract class SchemaSupport
{
    protected function uuid(Table $table, string $name, bool $notNull = true): void
    {
        $table->addColumn($name, 'string', ['length' => 36, 'notnull' => $notNull]);
    }

    protected function decimal(Table $table, string $name, int|string|null $default = null, int $precision = 14, int $scale = 2): void
    {
        $options = ['precision' => $precision, 'scale' => $scale];
        if ($default !== null) {
            $options['default'] = (string) $default;
        }

        $table->addColumn($name, 'decimal', $options);
    }

    protected function timestamps(Table $table): void
    {
        $this->createdAt($table);
        $table->addColumn('updated_at', 'datetime_immutable');
    }

    protected function createdAt(Table $table): void
    {
        $table->addColumn('created_at', 'datetime_immutable');
    }

    protected function softDeletes(Table $table): void
    {
        $table->addColumn('deleted_at', 'datetime_immutable', ['notnull' => false]);
    }
}
