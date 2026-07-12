<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederInterface;

require_once __DIR__ . '/Support/ProductionSeedHelper.php';

return new class implements SeederInterface
{
    public function run(Connection $db): void
    {
        (new ProductionSeedHelper($db))->seedOrganizationPack(ProductionSeedHelper::sunriseHealthSpec());
    }
};
