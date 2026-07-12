<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Finance;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Finance\Authorization\FinancePermissionDefinitionProvider;

final class FinancePermissionDefinitionProviderTest extends TestCase
{
    public function test_finance_permissions_are_system_only(): void
    {
        $provider = new FinancePermissionDefinitionProvider();

        foreach ($provider->definitions() as $definition) {
            self::assertTrue($definition->systemOnly, $definition->key . ' should be system-only.');
            self::assertSame([], $definition->defaultAssignments, $definition->key . ' should not declare customer default assignments.');
        }
    }
}
