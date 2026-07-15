<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Presentation;

use WorkEddy\Modules\Content\Domain\Contracts\ContentPageReader;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Modules\Content\Support\NationalImportancePageDefinition;
use WorkEddy\Modules\Reporting\Application\Services\PlatformAggregateMetricsService;
use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatisticCategory;
use WorkEddy\Platform\Session\UserContext;

final class ReportingPageData
{
    public function __construct(
        private readonly ReportingSnapshotService $snapshots,
        private readonly ?PlatformAggregateMetricsService $platformMetrics = null,
        private readonly ?INationalStatisticRepository $statistics = null,
        private readonly ?ContentPageReader $contentPages = null,
    ) {}

    public function dashboard(UserContext $ctx): array
    {
        return array_replace(
            $this->snapshots->dashboard(),
            ['user' => (string) $ctx->userId],
        );
    }

    public function finance(UserContext $ctx): array
    {
        return array_replace($this->snapshots->finance(), ['user' => (string) $ctx->userId]);
    }

    public function operations(UserContext $ctx): array
    {
        return array_replace($this->snapshots->operations(), ['user' => (string) $ctx->userId]);
    }

    public function pilotSummary(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->pilotSummary($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function impactTracker(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->impactTracker($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function dashboardOverview(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->dashboardOverview($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function assessment(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->assessmentReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function correctiveAction(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->correctiveActionReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function comparison(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->comparisonReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function auditTrail(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->auditTrailReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function nationalImportance(UserContext $ctx): array
    {
        $dynamic = $this->platformMetrics?->latestSnapshot() ?? [
            'industriesRepresented' => 0,
            'worksitesAssessed' => 0,
            'highRiskTasksIdentified' => 0,
            'commonHighStrainTasks' => [],
            'bodyRegionBurden' => [],
            'commonCorrectiveActions' => [],
            'averageRiskReductionAfterCorrection' => 0.0,
            'workerDiscomfortTrend' => [],
            'generatedAt' => null,
        ];

        $allStatistics = $this->statistics?->listAll(publishedOnly: true) ?? [];
        $byCategory = [];
        foreach (NationalStatisticCategory::keys() as $key) {
            $byCategory[$key] = [];
        }
        foreach ($allStatistics as $statistic) {
            $byCategory[$statistic->category][] = $statistic->toView();
        }

        return [
            'user' => (string) $ctx->userId,
            'dynamic' => $dynamic,
            'categoryLabels' => NationalStatisticCategory::labels(),
            'statisticsByCategory' => $byCategory,
            'context' => $this->nationalImportanceContext(),
            'notes' => $this->nationalImportanceNotes(),
        ];
    }

    /** @return array{problemSummary: string, futureResearch: string} */
    private function nationalImportanceContext(): array
    {
        $fallback = [
            'problemSummary' => 'Musculoskeletal strain remains one of the most common and costly sources of workplace injury across warehouse work, health care support work, manual material handling, long-term care, food service, manufacturing, delivery work, and other repetitive or high-strain jobs.',
            'futureResearch' => 'Planned areas of continued study include longitudinal injury-outcome tracking, sector-specific benchmarking, and independent validation of platform risk-reduction estimates.',
        ];

        $page = $this->contentPages?->findPublishedByKey(NationalImportancePageDefinition::PAGE_KEY);
        if ($page === null) {
            return $fallback;
        }

        $sections = [];
        foreach ($page->sections as $section) {
            $sections[$section->sectionKey] = trim($section->plainText);
        }

        return [
            'problemSummary' => $sections[NationalImportancePageDefinition::SECTION_PROBLEM_SUMMARY] ?? $fallback['problemSummary'],
            'futureResearch' => $sections[NationalImportancePageDefinition::SECTION_FUTURE_RESEARCH] ?? $fallback['futureResearch'],
        ];
    }

    /** @return array{methodology: string, limitations: string, privacy: string} */
    private function nationalImportanceNotes(): array
    {
        $fallback = [
            'methodology' => 'WorkEddy measures task-level ergonomic risk factors including posture, force, repetition, reach, bending, twisting, manual handling, and reported discomfort.',
            'limitations' => 'WorkEddy does not provide medical diagnosis, legal compliance certification, or a guarantee of injury prevention, and it does not replace professional ergonomic judgment.',
            'privacy' => 'WorkEddy supports consent capture, role-based access, secure storage, audit logging, de-identified exports, and privacy-focused handling of worker data.',
        ];

        $page = $this->contentPages?->findPublishedByKey(MethodologyPageDefinition::PAGE_KEY);
        if ($page === null) {
            return $fallback;
        }

        $sections = [];
        foreach ($page->sections as $section) {
            $sections[$section->sectionKey] = trim($section->plainText);
        }

        return [
            'methodology' => $sections['what_workeddy_measures'] ?? $fallback['methodology'],
            'limitations' => $sections['what_workeddy_does_not_claim'] ?? $fallback['limitations'],
            'privacy' => $sections['how_privacy_is_protected'] ?? $fallback['privacy'],
        ];
    }
}
