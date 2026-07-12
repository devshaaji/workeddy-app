<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Services;

final class ImprovementProofService
{
    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $target
     * @param list<array<string, mixed>> $nodes
     * @return array<string, mixed>
     */
    public function build(array $baseline, array $target, array $nodes = []): array
    {
        $before = (float) ($baseline['normalized_score'] ?? 0.0);
        $after = (float) ($target['normalized_score'] ?? 0.0);

        $delta = round($after - $before, 2);
        $riskReductionPoints = round($before - $after, 2);
        $riskReductionPct = $before > 0.0 ? round((($before - $after) / $before) * 100.0, 2) : 0.0;

        $timeSavingsMinPerShift = max(0.0, round($riskReductionPoints * 0.45, 1));
        $avoidedInjuryCostUsdAnnual = max(0.0, round($riskReductionPoints * 420.0, 2));

        $topDrivers = [];
        foreach (array_slice($nodes, 0, 3) as $node) {
            $topDrivers[] = [
                'node' => $node['node'] ?? $node['key'] ?? 'unknown',
                'delta' => isset($node['delta']) ? (float) $node['delta'] : null,
            ];
        }

        return [
            'direction' => $this->direction($delta),
            'risk_reduction_points' => $riskReductionPoints,
            'risk_reduction_percent' => $riskReductionPct,
            'estimated_time_savings_minutes_per_shift' => $timeSavingsMinPerShift,
            'estimated_avoided_injury_cost_usd_annual' => $avoidedInjuryCostUsdAnnual,
            'confidence' => 'estimated_mvp',
            'evidence_chain' => [
                'baseline_assessment_uuid' => $baseline['uuid'] ?? null,
                'follow_up_assessment_uuid' => $target['uuid'] ?? null,
                'baseline_algorithm_version' => (string) ($baseline['algorithm_version'] ?? 'v2'),
                'follow_up_algorithm_version' => (string) ($target['algorithm_version'] ?? 'v2'),
                'baseline_created_at' => $baseline['created_at'] ?? null,
                'follow_up_created_at' => $target['created_at'] ?? null,
                'top_changed_nodes' => $topDrivers,
            ],
        ];
    }

    private function direction(float $delta): string
    {
        if (abs($delta) < 0.0001) {
            return 'unchanged';
        }

        return $delta < 0.0 ? 'improved' : 'worsened';
    }
}
