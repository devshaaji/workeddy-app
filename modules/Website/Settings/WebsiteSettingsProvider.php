<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class WebsiteSettingsProvider implements IModuleSettingsProvider
{
    public function getModuleName(): string
    {
        return 'website';
    }

    /** @return SettingDefinition[] */
    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'site_name',
                module: 'website',
                type: SettingType::STRING,
                default: 'BrowseMX Website',
                label: 'Site Name',
                description: 'The name of the website displayed in the header and metadata.',
            ),
            new SettingDefinition(
                key: 'maintenance_mode',
                module: 'website',
                type: SettingType::BOOLEAN,
                default: false,
                label: 'Maintenance Mode',
                description: 'When enabled, the public website displays a maintenance page.',
            ),
            new SettingDefinition(
                key: 'contact_email',
                module: 'website',
                type: SettingType::STRING,
                default: 'support@browsemx.local',
                label: 'Contact Email',
                description: 'The primary email address displayed on the contact page.',
            ),
            new SettingDefinition(
                key: 'support_phone',
                module: 'website',
                type: SettingType::STRING,
                default: '+1234567890',
                label: 'Support Phone',
                description: 'The primary support phone number displayed on the website.',
            ),
            new SettingDefinition(
                key: WebsiteSettings::COVERAGE_DIRECT_RADIUS_KM,
                module: 'website',
                type: SettingType::FLOAT,
                default: 25.0,
                label: 'Direct Coverage Radius (km)',
                description: 'Distance from a POP that is considered directly serviceable.',
            ),
            new SettingDefinition(
                key: WebsiteSettings::COVERAGE_EXTENDED_RADIUS_KM,
                module: 'website',
                type: SettingType::FLOAT,
                default: 90.0,
                label: 'Extended Coverage Radius (km)',
                description: 'Distance from a POP that is considered eligible for engineering review.',
            ),
            new SettingDefinition(
                key: WebsiteSettings::COVERAGE_AVAILABLE_PLANS,
                module: 'website',
                type: SettingType::JSON,
                default: ['Starter 100Mbps', 'Professional 1Gbps', 'Enterprise 10Gbps'],
                label: 'Coverage Plans',
                description: 'Plan labels shown when service is available in a checked area.',
            ),
            new SettingDefinition(
                key: WebsiteSettings::COVERAGE_WAITLIST_PLAN_INTEREST,
                module: 'website',
                type: SettingType::STRING,
                default: 'Coverage Waitlist',
                label: 'Coverage Waitlist Lead Tag',
                description: 'Plan interest tag attached to CRM leads created from the coverage waitlist.',
            ),
            new SettingDefinition(
                key: WebsiteSettings::COVERAGE_POP_LOCATIONS,
                module: 'website',
                type: SettingType::JSON,
                default: [
                    [
                        'name' => 'Lagos POP',
                        'label' => 'LOS',
                        'latitude' => 6.5244,
                        'longitude' => 3.3792,
                        'latency' => '< 10ms',
                        'max_speed' => '10Gbps',
                        'extended_latency' => '15-25ms',
                        'extended_speed' => '500Mbps',
                        'aliases' => ['lagos', 'ikeja', 'lekki', 'victoria island'],
                    ],
                    [
                        'name' => 'Nairobi POP',
                        'label' => 'NBO',
                        'latitude' => -1.286389,
                        'longitude' => 36.817223,
                        'latency' => '< 10ms',
                        'max_speed' => '10Gbps',
                        'extended_latency' => '15-25ms',
                        'extended_speed' => '500Mbps',
                        'aliases' => ['nairobi', 'westlands', 'upper hill'],
                    ],
                    [
                        'name' => 'Cape Town POP',
                        'label' => 'CPT',
                        'latitude' => -33.9249,
                        'longitude' => 18.4241,
                        'latency' => '< 10ms',
                        'max_speed' => '10Gbps',
                        'extended_latency' => '15-25ms',
                        'extended_speed' => '500Mbps',
                        'aliases' => ['cape town', 'claremont', 'bellville'],
                    ],
                ],
                label: 'Coverage POP Locations',
                description: 'Configured service POPs used by the public coverage checker.',
            ),
        ];
    }
}
