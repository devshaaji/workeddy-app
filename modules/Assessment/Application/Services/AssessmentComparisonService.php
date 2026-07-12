<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Services;

use RuntimeException;
use WorkEddy\Modules\Assessment\Domain\Assessment;

final class AssessmentComparisonService
{
    public function __construct(private readonly ImprovementProofService $improvementProofs) {}

    /** @return array<string, mixed> */
    public function compare(Assessment $baseline, Assessment $followUp): array
    {
        if ($baseline->getUuid() === $followUp->getUuid()) {
            throw new RuntimeException('Baseline and follow-up must be different assessments.');
        }
        if ($baseline->getOrganizationId() !== $followUp->getOrganizationId()) {
            throw new RuntimeException('Assessments must belong to same organization.');
        }
        if (strtolower($baseline->getModel()) !== strtolower($followUp->getModel())) {
            throw new RuntimeException('Assessments must use same scoring model.');
        }
        if (!$baseline->isBaseline() || $baseline->getStatus() !== 'locked') {
            throw new RuntimeException('Baseline assessment must be marked and locked before comparison.');
        }
        if (!in_array($followUp->getStatus(), ['reviewed', 'locked'], true)) {
            throw new RuntimeException('Follow-up assessment must be reviewed before comparison.');
        }

        $baselineScore = $this->scoreSummary($baseline);
        $followUpScore = $this->scoreSummary($followUp);
        $scoreDiff = [
            'raw' => $this->roundOrNull(($followUpScore['raw'] ?? null) !== null && ($baselineScore['raw'] ?? null) !== null ? (float) $followUpScore['raw'] - (float) $baselineScore['raw'] : null),
            'normalized' => $this->roundOrNull(($followUpScore['normalized'] ?? null) !== null && ($baselineScore['normalized'] ?? null) !== null ? (float) $followUpScore['normalized'] - (float) $baselineScore['normalized'] : null),
        ];

        $bodyRegionDelta = $this->compareBodyRegions($baseline, $followUp);
        $nodes = $this->compareNodes($baseline, $followUp);
        $improvement = $this->improvementProofs->build(
            $this->improvementInput($baseline),
            $this->improvementInput($followUp),
            $nodes,
        );

        return [
            'model' => strtolower($baseline->getModel()),
            'summary' => [
                'baseline' => $baseline->toView(),
                'followUp' => $followUp->toView(),
                'direction' => $improvement['direction'],
                'compatible' => true,
            ],
            'baselineScore' => $baselineScore,
            'followUpScore' => $followUpScore,
            'scoreDiff' => $scoreDiff,
            'bodyRegionsImproved' => $bodyRegionDelta['improved'],
            'bodyRegionsWorsened' => $bodyRegionDelta['worsened'],
            'bodyRegionsUnchanged' => $bodyRegionDelta['unchanged'],
            'nodes' => $nodes,
            'improvementProof' => $improvement,
        ];
    }

    /** @return array<string, mixed> */
    private function scoreSummary(Assessment $assessment): array
    {
        $score = $assessment->getFinalScoreData() ?? $assessment->getInitialScoreData();

        return [
            'raw' => isset($score['raw_score']) ? (float) $score['raw_score'] : (isset($score['score']) ? (float) $score['score'] : null),
            'normalized' => isset($score['normalized_score']) ? (float) $score['normalized_score'] : null,
            'riskLevel' => $score['risk_level'] ?? null,
            'riskCategory' => $score['risk_category'] ?? null,
            'algorithmVersion' => strtolower((string) ($score['algorithm_version'] ?? 'v2')),
        ];
    }

    /** @return array<string, mixed> */
    private function improvementInput(Assessment $assessment): array
    {
        $score = $this->scoreSummary($assessment);

        return [
            'uuid' => $assessment->getUuid(),
            'normalized_score' => $score['normalized'],
            'algorithm_version' => $score['algorithmVersion'],
            'created_at' => $assessment->getCreatedAt(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function compareNodes(Assessment $baseline, Assessment $followUp): array
    {
        $base = $baseline->getMetrics();
        $next = $followUp->getMetrics();
        $keys = array_values(array_intersect(array_keys($base), array_keys($next)));
        $nodes = [];
        foreach ($keys as $key) {
            if (!is_numeric($base[$key] ?? null) || !is_numeric($next[$key] ?? null)) {
                continue;
            }
            $nodes[] = [
                'node' => str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $key))),
                'key' => (string) $key,
                'baseline' => (float) $base[$key],
                'followUp' => (float) $next[$key],
                'delta' => round((float) $next[$key] - (float) $base[$key], 2),
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => abs((float) $b['delta']) <=> abs((float) $a['delta']));

        return $nodes;
    }

    /** @return array{improved:list<array<string, mixed>>, worsened:list<array<string, mixed>>, unchanged:list<array<string, mixed>>} */
    private function compareBodyRegions(Assessment $baseline, Assessment $followUp): array
    {
        $base = $this->bodyRegionMap($baseline->getBodyRegions());
        $next = $this->bodyRegionMap($followUp->getBodyRegions());
        $keys = array_values(array_unique([...array_keys($base), ...array_keys($next)]));
        $result = ['improved' => [], 'worsened' => [], 'unchanged' => []];

        foreach ($keys as $key) {
            $before = $base[$key] ?? ['region' => $key, 'side' => 'front', 'intensity' => 0];
            $after = $next[$key] ?? ['region' => $before['region'], 'side' => $before['side'], 'intensity' => 0];
            $delta = (int) $after['intensity'] - (int) $before['intensity'];
            $row = [
                'region' => $before['region'],
                'side' => $before['side'],
                'baselineIntensity' => (int) $before['intensity'],
                'followUpIntensity' => (int) $after['intensity'],
                'delta' => $delta,
            ];
            if ($delta < 0) {
                $result['improved'][] = $row;
            } elseif ($delta > 0) {
                $result['worsened'][] = $row;
            } else {
                $result['unchanged'][] = $row;
            }
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $regions @return array<string, array<string, mixed>> */
    private function bodyRegionMap(array $regions): array
    {
        $map = [];
        foreach ($regions as $region) {
            $key = strtolower((string) ($region['side'] ?? 'front')) . ':' . strtolower((string) ($region['region'] ?? 'unknown'));
            $map[$key] = [
                'region' => (string) ($region['region'] ?? 'unknown'),
                'side' => (string) ($region['side'] ?? 'front'),
                'intensity' => (int) ($region['intensity'] ?? 0),
            ];
        }

        return $map;
    }

    private function roundOrNull(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}
