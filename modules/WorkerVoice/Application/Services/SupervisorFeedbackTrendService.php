<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application\Services;

use WorkEddy\Modules\WorkerVoice\Domain\SupervisorFeedback;

final class SupervisorFeedbackTrendService
{
    /**
     * @param list<SupervisorFeedback> $items
     * @return array<string, mixed>
     */
    public function summarize(array $items): array
    {
        return [
            'summary' => [
                'totalResponses' => count($items),
                'averageSeverity' => $this->avg($items, static fn(SupervisorFeedback $item): int => $item->severityLevel),
                'averageFrequency' => $this->avg($items, static fn(SupervisorFeedback $item): int => $item->frequencyLevel),
            ],
            'byBodyRegion' => $this->groupByBodyRegion($items),
            'byDepartment' => $this->groupByDepartment($items),
            'timeline' => $this->timeline($items),
        ];
    }

    /** @param list<SupervisorFeedback> $items @return list<array<string, mixed>> */
    private function groupByBodyRegion(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->bodyRegion ?? 'unspecified'][] = $item;
        }

        $rows = [];
        foreach ($groups as $bodyRegion => $group) {
            $rows[] = [
                'bodyRegion' => $bodyRegion === 'unspecified' ? null : $bodyRegion,
                'responses' => count($group),
                'averageSeverity' => $this->avg($group, static fn(SupervisorFeedback $item): int => $item->severityLevel),
                'averageFrequency' => $this->avg($group, static fn(SupervisorFeedback $item): int => $item->frequencyLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<SupervisorFeedback> $items @return list<array<string, mixed>> */
    private function groupByDepartment(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->departmentUuid ?? 'unassigned'][] = $item;
        }

        $rows = [];
        foreach ($groups as $departmentUuid => $group) {
            $rows[] = [
                'departmentUuid' => $departmentUuid === 'unassigned' ? null : $departmentUuid,
                'responses' => count($group),
                'averageSeverity' => $this->avg($group, static fn(SupervisorFeedback $item): int => $item->severityLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<SupervisorFeedback> $items @return list<array<string, mixed>> */
    private function timeline(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $date = substr((string) ($item->createdAt ?? ''), 0, 10);
            $groups[$date][] = $item;
        }
        ksort($groups);

        $rows = [];
        foreach ($groups as $date => $group) {
            $rows[] = [
                'date' => $date,
                'responses' => count($group),
                'averageSeverity' => $this->avg($group, static fn(SupervisorFeedback $item): int => $item->severityLevel),
            ];
        }

        return $rows;
    }

    /** @param list<SupervisorFeedback> $items */
    private function avg(array $items, callable $value): float
    {
        if ($items === []) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) $value($item);
        }

        return round($sum / count($items), 2);
    }
}
