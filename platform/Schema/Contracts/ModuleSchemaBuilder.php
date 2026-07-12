<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Contracts;

use Doctrine\DBAL\Schema\Schema;

interface ModuleSchemaBuilder
{
    public function module(): string;

    /**
     * @return list<string>
     */
    public function tables(): array;

    public function build(Schema $schema): void;
}
