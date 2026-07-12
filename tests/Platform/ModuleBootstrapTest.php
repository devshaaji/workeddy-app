<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Platform;

use PHPUnit\Framework\TestCase;

final class ModuleBootstrapTest extends TestCase
{
    public function test_bootstrap_modules_register_all_module_service_providers(): void
    {
        $providers = require dirname(__DIR__, 2) . '/bootstrap/modules.php';
        $providerClasses = array_values(array_filter($providers, 'is_string'));

        foreach (glob(dirname(__DIR__, 2) . '/modules/*/ServiceProvider.php') ?: [] as $file) {
            $module = basename(dirname($file));
            $expected = 'WorkEddy\\Modules\\' . $module . '\\ServiceProvider';

            self::assertContains(
                $expected,
                $providerClasses,
                sprintf('Module "%s" is not registered in bootstrap/modules.php.', $module),
            );
        }
    }
}
