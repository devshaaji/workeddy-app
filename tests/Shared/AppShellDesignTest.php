<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Shared;

use PHPUnit\Framework\TestCase;

final class AppShellDesignTest extends TestCase
{
    public function testAppShellUsesSneatCoreLayoutClasses(): void
    {
        $root = dirname(__DIR__, 2);

        $layout = file_get_contents($root . '/shared/Views/Layouts/app.php');
        self::assertIsString($layout);
        self::assertStringContainsString('layout-content-navbar', $layout);
        self::assertStringContainsString('layout-menu-fixed', $layout);
        self::assertStringContainsString('layout-navbar-fixed', $layout);

        $navbar = file_get_contents($root . '/shared/Views/Partials/navbar.php');
        self::assertIsString($navbar);
        self::assertStringContainsString('navbar-nav-right', $navbar);
        self::assertStringContainsString('dropdown-shortcuts', $navbar);
        self::assertStringContainsString('dropdown-notifications', $navbar);
        self::assertStringContainsString('dropdown-user', $navbar);
        self::assertStringContainsString('data-bs-theme-value="light"', $navbar);
        self::assertStringNotContainsString('dropdown-menu-shortcuts', $navbar);
        self::assertStringNotContainsString('dropdown-menu-notifications', $navbar);
        self::assertStringNotContainsString('dropdown-menu-user', $navbar);
        self::assertStringNotContainsString('navbar-actions', $navbar);
        self::assertStringNotContainsString('search-toggler', $navbar);
    }
}
