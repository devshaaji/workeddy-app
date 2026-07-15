<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Domain;

/**
 * The required topic areas for the National Importance dashboard's industry
 * risk cards. Kept as an application-level allow-list (not a DB enum) so a
 * new topic can be added without a migration, matching this codebase's
 * existing preference for app-level validation over DB-level enums.
 */
final class NationalStatisticCategory
{
    public const MUSCULOSKELETAL_STRAIN = 'musculoskeletal_strain';
    public const WAREHOUSE_WORK = 'warehouse_work';
    public const HEALTH_CARE_SUPPORT = 'health_care_support';
    public const MANUAL_MATERIAL_HANDLING = 'manual_material_handling';
    public const LONG_TERM_CARE = 'long_term_care';
    public const FOOD_SERVICE = 'food_service';
    public const MANUFACTURING = 'manufacturing';
    public const DELIVERY_WORK = 'delivery_work';
    public const REPETITIVE_HIGH_STRAIN = 'repetitive_high_strain';

    /** @return array<string, string> Machine key => display label */
    public static function labels(): array
    {
        return [
            self::MUSCULOSKELETAL_STRAIN => 'Musculoskeletal Strain',
            self::WAREHOUSE_WORK => 'Warehouse Work',
            self::HEALTH_CARE_SUPPORT => 'Health Care Support Work',
            self::MANUAL_MATERIAL_HANDLING => 'Manual Material Handling',
            self::LONG_TERM_CARE => 'Long-Term Care',
            self::FOOD_SERVICE => 'Food Service',
            self::MANUFACTURING => 'Manufacturing',
            self::DELIVERY_WORK => 'Delivery Work',
            self::REPETITIVE_HIGH_STRAIN => 'Repetitive & High-Strain Jobs',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function isValid(string $category): bool
    {
        return array_key_exists($category, self::labels());
    }

    public static function label(string $category): string
    {
        return self::labels()[$category] ?? $category;
    }
}
