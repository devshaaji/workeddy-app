<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Settings;

use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingDefinition;
use WorkEddy\Platform\Settings\SettingType;

final class ReportingSettingsProvider implements IModuleSettingsProvider, \WorkEddy\Platform\Settings\ISettingsPageProvider
{
    public function getModuleName(): string
    {
        return 'reporting';
    }

    public function getDefinitions(): array
    {
        return [
            new SettingDefinition(
                key: 'default_revenue_window_days',
                module: 'reporting',
                type: SettingType::INTEGER,
                default: 30,
                label: 'Default Revenue Window Days',
                description: 'Default lookback window for management revenue reporting.',
            ),
            new SettingDefinition(
                key: 'include_expired_customers',
                module: 'reporting',
                type: SettingType::BOOLEAN,
                default: true,
                label: 'Include Expired Customers',
                description: 'Include expired customers in aggregate reporting totals.',
            ),
            new SettingDefinition(
                key: 'template_version',
                module: 'reporting',
                type: SettingType::STRING,
                default: 'v1',
                label: 'Report Template Version',
                description: 'Canonical template version stamped on generated report artifacts.',
            ),
            new SettingDefinition(
                key: 'methodology_note',
                module: 'reporting',
                type: SettingType::STRING,
                default: "This assessment was generated using WorkEddy's ergonomic evaluation framework with reviewer-approved scoring where available.",
                label: 'Methodology Note',
                description: 'Methodology note appended to exported reports.',
            ),
            new SettingDefinition(
                key: 'limitations_note',
                module: 'reporting',
                type: SettingType::STRING,
                default: 'This report is a point-in-time assessment and may not capture all exposure variation across shifts, teams, or changing site conditions.',
                label: 'Limitations Note',
                description: 'Limitations note appended to exported reports.',
            ),
            new SettingDefinition(
                key: 'privacy_note',
                module: 'reporting',
                type: SettingType::STRING,
                default: 'WorkEddy supports ergonomic risk prevention, not worker discipline. Raw worker identifiers stay hidden unless policy and permission allow disclosure.',
                label: 'Privacy Note',
                description: 'Privacy note appended to exported reports.',
            ),
            new SettingDefinition(
                key: 'download_link_ttl_minutes',
                module: 'reporting',
                type: SettingType::INTEGER,
                default: 15,
                label: 'Report Download Link TTL Minutes',
                description: 'Target expiry window for future signed report download links.',
            ),
            new SettingDefinition(
                key: 'impact_injury_prevention_rate',
                module: 'reporting',
                type: SettingType::FLOAT,
                default: 0.15,
                label: 'Impact Tracker: Injury Prevention Rate Assumption',
                description: 'Assumed fraction of resolved high-risk tasks (baseline High Risk -> non-High Risk after a corrective action) that correspond to one potential injury avoided. A conservative, editable planning assumption, not a measured outcome.',
            ),
            new SettingDefinition(
                key: 'impact_lost_workdays_per_injury',
                module: 'reporting',
                type: SettingType::FLOAT,
                default: 8.0,
                label: 'Impact Tracker: Lost Workdays per Injury Assumption',
                description: 'Assumed average lost workdays per potential injury, used only to estimate the Impact Tracker\'s \'potential lost workdays avoided\' figure.',
            ),
            new SettingDefinition(
                key: 'impact_cost_per_lost_workday',
                module: 'reporting',
                type: SettingType::FLOAT,
                default: 450.0,
                label: 'Impact Tracker: Cost per Lost Workday Assumption',
                description: 'Assumed fully-burdened cost per lost workday (wages, replacement labor, claims overhead), used only to estimate the Impact Tracker\'s \'potential cost savings\' figure.',
            ),
            new SettingDefinition(
                key: 'impact_estimate_disclaimer',
                module: 'reporting',
                type: SettingType::STRING,
                default: 'Estimated figures are planning approximations derived from observed high-risk task reduction and editable assumption rates. They are not guarantees of injuries prevented, workdays saved, or costs avoided, and should not be presented as confirmed outcomes, OSHA compliance, or eliminated risk.',
                label: 'Impact Tracker Estimate Disclaimer',
                description: 'Cautionary language shown wherever estimated impact figures are displayed or exported.',
            ),
        ];
    }

    public function getSettingsPageMetadata(): \WorkEddy\Platform\Settings\SettingsPageMetadata
    {
        return new \WorkEddy\Platform\Settings\SettingsPageMetadata(
            module: 'reporting',
            label: 'Reporting',
            viewPermissions: [\WorkEddy\Modules\Reporting\Authorization\ReportingPermissions::SETTINGS],
            editPermissions: [\WorkEddy\Modules\Reporting\Authorization\ReportingPermissions::SETTINGS],
            sortOrder: 180,
        );
    }
}
