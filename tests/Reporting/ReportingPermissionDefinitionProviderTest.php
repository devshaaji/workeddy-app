<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Reporting;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissionDefinitionProvider;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;

final class ReportingPermissionDefinitionProviderTest extends TestCase
{
    public function test_system_reporting_permission_is_system_only(): void
    {
        $provider = new ReportingPermissionDefinitionProvider();
        $definitions = [];

        foreach ($provider->definitions() as $definition) {
            $definitions[$definition->key] = $definition;
        }

        self::assertArrayHasKey(ReportingPermissions::SYSTEM_VIEW, $definitions);
        self::assertTrue($definitions[ReportingPermissions::SYSTEM_VIEW]->systemOnly);
        self::assertFalse($definitions[ReportingPermissions::VIEW]->systemOnly);
    }
}
