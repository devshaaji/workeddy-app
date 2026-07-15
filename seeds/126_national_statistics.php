<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Platform\Seeding\SeederInterface;

return new class implements SeederInterface
{
    private const ROWS = [
        [
            'uuid' => '00000000-0000-4000-8000-000000001261',
            'title' => 'Sprains, strains, or tears in days-away-from-work cases',
            'value' => '568,150',
            'unit' => 'cases',
            'category' => 'musculoskeletal_strain',
            'industry_relevance' => 'Cross-sector indicator of musculoskeletal strain burden.',
            'source_name' => 'U.S. Bureau of Labor Statistics',
            'source_year' => 2024,
            'source_url' => 'https://www.bls.gov/iif/latest-numbers.htm',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001262',
            'title' => 'OSHA notes MSD hazards in warehousing and distribution',
            'value' => 'High exposure',
            'unit' => null,
            'category' => 'warehouse_work',
            'industry_relevance' => 'Warehousing, fulfillment, and distribution operations.',
            'source_name' => 'Occupational Safety and Health Administration',
            'source_year' => 2026,
            'source_url' => 'https://www.osha.gov/ergonomics',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001263',
            'title' => 'Patient handling remains a major source of worker injury risk',
            'value' => 'Priority hazard',
            'unit' => null,
            'category' => 'health_care_support',
            'industry_relevance' => 'Health care support and clinical assistance work.',
            'source_name' => 'NIOSH Safe Patient Handling and Mobility',
            'source_year' => 2026,
            'source_url' => 'https://www.cdc.gov/niosh/topics/safepatient/',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001264',
            'title' => 'Manual lifting and handling are established ergonomic risk factors',
            'value' => 'Documented risk',
            'unit' => null,
            'category' => 'manual_material_handling',
            'industry_relevance' => 'Material handling, lifting, carrying, and pushing tasks.',
            'source_name' => 'NIOSH Ergonomics and Musculoskeletal Disorders',
            'source_year' => 2026,
            'source_url' => 'https://www.cdc.gov/niosh/topics/ergonomics/',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001265',
            'title' => 'Safe resident handling is a continuing long-term-care workforce priority',
            'value' => 'Ongoing need',
            'unit' => null,
            'category' => 'long_term_care',
            'industry_relevance' => 'Long-term care, nursing, and resident support settings.',
            'source_name' => 'NIOSH Safe Patient Handling and Mobility',
            'source_year' => 2026,
            'source_url' => 'https://www.cdc.gov/niosh/topics/safepatient/',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001266',
            'title' => 'Fast-paced food service work combines repetition, force, and awkward posture',
            'value' => 'Elevated exposure',
            'unit' => null,
            'category' => 'food_service',
            'industry_relevance' => 'Kitchen, prep, and front-line food service operations.',
            'source_name' => 'Occupational Safety and Health Administration',
            'source_year' => 2026,
            'source_url' => 'https://www.osha.gov/ergonomics',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001267',
            'title' => 'Manufacturing remains a core setting for repetitive and forceful task exposure',
            'value' => 'Persistent burden',
            'unit' => null,
            'category' => 'manufacturing',
            'industry_relevance' => 'Assembly, fabrication, and repetitive production work.',
            'source_name' => 'Occupational Safety and Health Administration',
            'source_year' => 2026,
            'source_url' => 'https://www.osha.gov/ergonomics',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001268',
            'title' => 'Delivery work often combines manual handling, time pressure, and repetitive loading',
            'value' => 'Compound exposure',
            'unit' => null,
            'category' => 'delivery_work',
            'industry_relevance' => 'Parcel, route, and field-delivery operations.',
            'source_name' => 'NIOSH Ergonomics and Musculoskeletal Disorders',
            'source_year' => 2026,
            'source_url' => 'https://www.cdc.gov/niosh/topics/ergonomics/',
        ],
        [
            'uuid' => '00000000-0000-4000-8000-000000001269',
            'title' => 'Overexertion, repetitive motion, and bodily reaction cases across 2023-2024',
            'value' => '946,290',
            'unit' => 'cases',
            'category' => 'repetitive_high_strain',
            'industry_relevance' => 'Repetitive and high-strain job patterns across sectors.',
            'source_name' => 'U.S. Bureau of Labor Statistics',
            'source_year' => 2024,
            'source_url' => 'https://www.bls.gov/news.release/osh.nr0.htm',
        ],
    ];

    public function run(Connection $db): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $dateAdded = (new DateTimeImmutable())->format('Y-m-d');

        foreach (self::ROWS as $row) {
            $existing = $db->fetchAssociative(
                'SELECT id FROM national_statistics WHERE uuid = ?',
                [$row['uuid']],
            );

            $payload = [
                'title' => $row['title'],
                'value' => $row['value'],
                'unit' => $row['unit'],
                'category' => $row['category'],
                'industry_relevance' => $row['industry_relevance'],
                'source_name' => $row['source_name'],
                'source_year' => $row['source_year'],
                'source_url' => $row['source_url'],
                'is_published' => 1,
                'date_added' => $dateAdded,
                'updated_by_user_id' => null,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if ($existing === false) {
                $db->insert('national_statistics', $payload + [
                    'uuid' => $row['uuid'],
                    'created_by_user_id' => null,
                    'created_at' => $now,
                ]);
                continue;
            }

            $db->update('national_statistics', $payload, ['id' => (int) $existing['id']]);
        }
    }
};
