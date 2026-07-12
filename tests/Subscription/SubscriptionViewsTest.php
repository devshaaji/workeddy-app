<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Subscription;

use PHPUnit\Framework\TestCase;

final class SubscriptionViewsTest extends TestCase
{
    public function test_views_use_page_header_and_correct_layout_markers(): void
    {
        $root = dirname(__DIR__, 2);
        
        $index = file_get_contents($root . '/modules/Subscription/Presentation/Views/Index/index.php');
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $index);
        self::assertStringContainsString('subscriptionsTable', $index);
        self::assertStringContainsString('subscriptionsBody', $index);

        $detail = file_get_contents($root . '/modules/Subscription/Presentation/Views/Index/detail.php');
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $detail);
        self::assertStringContainsString('quota-progress', $detail);

        $settings = file_get_contents($root . '/modules/Subscription/Presentation/Views/Index/settings.php');
        self::assertStringContainsString("require \$v2Root . '/shared/Views/Partials/page_header.php';", $settings);
        self::assertStringContainsString('subscription-settings-form', $settings);
    }
}
