<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Tests;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentPage;
use WorkEddy\Modules\Content\Application\DTOs\PublishedContentSection;
use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Content\Support\NationalImportancePageDefinition;
use WorkEddy\Modules\Reporting\Application\Services\PlatformAggregateMetricsService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Domain\Contracts\IPlatformAggregateMetricRepository;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatistic;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageData;
use WorkEddy\Platform\Clock\FrozenClock;
use WorkEddy\Platform\Session\UserContext;

final class ReportingPageDataTest extends TestCase
{
    public function testNationalImportanceSeparatesDynamicMetricsStatisticsAndManagedContext(): void
    {
        $snapshotService = new ReportingSnapshotService($this->createMock(\Doctrine\DBAL\Connection::class));

        $platformMetrics = new PlatformAggregateMetricsService(
            $this->createMock(\Doctrine\DBAL\Connection::class),
            new class implements IPlatformAggregateMetricRepository {
                public function store(string $metricKey, string $metricName, mixed $value, ?string $industry, ?string $dateRangeStart, ?string $dateRangeEnd, string $generatedAt): void {}
                public function latest(string $metricKey, ?string $industry = null): ?array { return null; }
                public function latestAll(): array
                {
                    return [
                        'industries_represented' => ['metricKey' => 'industries_represented', 'value' => 4, 'generatedAt' => '2026-07-15 10:00:00'],
                        'worksites_assessed' => ['metricKey' => 'worksites_assessed', 'value' => 9, 'generatedAt' => '2026-07-15 10:00:00'],
                        'high_risk_tasks_identified' => ['metricKey' => 'high_risk_tasks_identified', 'value' => 12, 'generatedAt' => '2026-07-15 10:00:00'],
                        'common_high_strain_tasks' => ['metricKey' => 'common_high_strain_tasks', 'value' => [], 'generatedAt' => '2026-07-15 10:00:00'],
                        'body_region_burden' => ['metricKey' => 'body_region_burden', 'value' => [], 'generatedAt' => '2026-07-15 10:00:00'],
                        'common_corrective_actions' => ['metricKey' => 'common_corrective_actions', 'value' => [], 'generatedAt' => '2026-07-15 10:00:00'],
                        'average_risk_reduction_after_correction' => ['metricKey' => 'average_risk_reduction_after_correction', 'value' => 22.4, 'generatedAt' => '2026-07-15 10:00:00'],
                        'worker_discomfort_trend' => ['metricKey' => 'worker_discomfort_trend', 'value' => [], 'generatedAt' => '2026-07-15 10:00:00'],
                    ];
                }
            },
            new FrozenClock(new \DateTimeImmutable('2026-07-15 10:00:00')),
        );

        $statistics = $this->createMock(INationalStatisticRepository::class);
        $statistics->method('listAll')->with(true)->willReturn([
            new NationalStatistic(
                id: 1,
                uuid: '00000000-0000-4000-8000-000000009001',
                title: 'Sprains and strains',
                value: '568,150',
                unit: 'cases',
                category: 'musculoskeletal_strain',
                industryRelevance: 'Cross-sector',
                sourceName: 'BLS',
                sourceYear: 2024,
                sourceUrl: 'https://www.bls.gov/iif/latest-numbers.htm',
                isPublished: true,
                dateAdded: '2026-07-15',
                createdByUserId: null,
                updatedByUserId: null,
                createdAt: '2026-07-15 10:00:00',
                updatedAt: '2026-07-15 10:00:00',
            ),
        ]);

        $content = $this->createMock(ContentPageReader::class);
        $content->method('findPublishedByKey')->willReturnCallback(static function (string $key): ?PublishedContentPage {
            return match ($key) {
                NationalImportancePageDefinition::PAGE_KEY => new PublishedContentPage(
                    key: NationalImportancePageDefinition::PAGE_KEY,
                    title: 'National Importance',
                    audience: 'platform',
                    templateKey: 'default',
                    sections: [
                        new PublishedContentSection('national_problem_summary', 'Why Workforce Health Matters', [], [], 1, 'Managed problem summary.'),
                        new PublishedContentSection('future_research_agenda', 'Future Research Directions', [], [], 2, 'Managed future research.'),
                    ],
                    references: [],
                    images: [],
                    revisionUuid: '00000000-0000-4000-8000-000000009002',
                    publishedAt: new \DateTimeImmutable('2026-07-15 10:00:00'),
                    snapshotHash: 'hash-national',
                ),
                MethodologyPageDefinition::PAGE_KEY => new PublishedContentPage(
                    key: MethodologyPageDefinition::PAGE_KEY,
                    title: 'Methodology and Limitations',
                    audience: 'platform',
                    templateKey: 'default',
                    sections: [
                        new PublishedContentSection('what_workeddy_measures', 'What WorkEddy Measures', [], [], 1, 'Managed methodology note.'),
                        new PublishedContentSection('what_workeddy_does_not_claim', 'What WorkEddy Does Not Claim', [], [], 2, 'Managed limitation note.'),
                        new PublishedContentSection('how_privacy_is_protected', 'How Privacy Is Protected', [], [], 3, 'Managed privacy note.'),
                    ],
                    references: [],
                    images: [],
                    revisionUuid: '00000000-0000-4000-8000-000000009003',
                    publishedAt: new \DateTimeImmutable('2026-07-15 10:00:00'),
                    snapshotHash: 'hash-methodology',
                ),
                default => null,
            };
        });

        $pageData = new ReportingPageData($snapshotService, $platformMetrics, $statistics, $content);
        $payload = $pageData->nationalImportance(new UserContext(userId: 42, organizationUuid: 'org-uuid'));

        self::assertSame(4, $payload['dynamic']['industriesRepresented']);
        self::assertSame('Managed problem summary.', $payload['context']['problemSummary']);
        self::assertSame('Managed future research.', $payload['context']['futureResearch']);
        self::assertSame('Managed methodology note.', $payload['notes']['methodology']);
        self::assertSame('Managed limitation note.', $payload['notes']['limitations']);
        self::assertSame('Managed privacy note.', $payload['notes']['privacy']);
        self::assertCount(1, $payload['statisticsByCategory']['musculoskeletal_strain']);
        self::assertSame('Sprains and strains', $payload['statisticsByCategory']['musculoskeletal_strain'][0]['title']);
    }
}
