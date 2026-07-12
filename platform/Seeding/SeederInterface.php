<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Seeding;

use Doctrine\DBAL\Connection;

interface SeederInterface
{
    public function run(Connection $db): void;
}
