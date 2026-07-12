<?php

declare(strict_types=1);

namespace WorkEddy\Tests\IAM;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\IAM\Settings\IAMSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class IAMSettingsProviderTest extends TestCase
{
    public function testGetDefinitionsReturnsExpectedNotificationSettings(): void
    {
        $provider = new IAMSettingsProvider();
        $definitions = $provider->getDefinitions();

        $this->assertIsArray($definitions);
        $this->assertNotEmpty($definitions);

        $notificationKeys = [
            'notifications.iam.user_created.enabled',
            'notifications.iam.user_activated.enabled',
            'notifications.iam.user_suspended.enabled',
            'notifications.iam.role_assigned.enabled',
            'notifications.iam.force_logout.enabled',
            'notifications.iam.password_changed.enabled',
        ];

        $foundKeys = [];

        foreach ($definitions as $definition) {
            $this->assertInstanceOf(SettingDefinition::class, $definition);
            if (in_array($definition->key, $notificationKeys, true)) {
                $this->assertSame(SettingType::BOOLEAN, $definition->type);
                $this->assertTrue($definition->default);
                $foundKeys[] = $definition->key;
            }
        }

        $this->assertCount(count($notificationKeys), $foundKeys);
        foreach ($notificationKeys as $expectedKey) {
            $this->assertContains($expectedKey, $foundKeys);
        }
    }
}
