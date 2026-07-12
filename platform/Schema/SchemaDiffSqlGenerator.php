<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

final class SchemaDiffSqlGenerator
{
    /**
     * @return list<string>
     */
    public function diff(Connection $connection, Schema $targetSchema, bool $safe = true): array
    {
        $platform = $connection->getDatabasePlatform();
        if (method_exists($platform, 'registerDoctrineTypeMapping')) {
            $platform->registerDoctrineTypeMapping('enum', Types::STRING);
            $platform->registerDoctrineTypeMapping('set', Types::STRING);
        }

        $schemaManager = $connection->createSchemaManager();
        $diff = $schemaManager->createComparator()->compareSchemas(
            $schemaManager->introspectSchema(),
            $targetSchema,
        );

        return $safe ? $diff->toSaveSql($platform) : $diff->toSql($platform);
    }
}
