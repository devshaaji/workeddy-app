<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application\Services;

use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;

final class WorkerFeedbackTrendService
{
    public function __construct(private readonly ITaskRepository $tasks) {}

    /**
     * @param list<WorkerFeedback> $items
     * @return array<string, mixed>
     */
    public function summarize(array $items): array
    {
        $total = count($items);
        $anonymous = count(array_filter($items, static fn(WorkerFeedback $item): bool => $item->anonymousStatus));

        return [
            'summary' => [
                'totalResponses' => $total,
                'anonymousResponses' => $anonymous,
                'anonymousRate' => $total > 0 ? round(($anonymous / $total) * 100, 2) : 0.0,
                'averageDiscomfort' => $this->avg($items, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
                'averagePain7Day' => $this->avg($items, static fn(WorkerFeedback $item): int => $item->pain7DayLevel),
                'averagePain30Day' => $this->avg($items, static fn(WorkerFeedback $item): int => $item->pain30DayLevel),
            ],
            'byBodyRegion' => $this->groupByBodyRegion($items),
            'byTask' => $this->groupByTask($items),
            'byTaskType' => $this->groupByTaskType($items),
            'byDepartment' => $this->groupByDepartment($items),
            'timeline' => $this->timeline($items),
        ];
    }

    /** @param list<WorkerFeedback> $items @return list<array<string, mixed>> */
    private function groupByBodyRegion(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item->bodyRegion][] = $item;
        }

        $rows = [];
        foreach ($groups as $bodyRegion => $group) {
            $rows[] = [
                'bodyRegion' => $bodyRegion,
                'responses' => count($group),
                'averageDiscomfort' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
                'averagePain7Day' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->pain7DayLevel),
                'averagePain30Day' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->pain30DayLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<WorkerFeedback> $items @return list<array<string, mixed>> */
    private function groupByTask(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item->taskUuid ?? 'unlinked';
            $groups[$key][] = $item;
        }

        $rows = [];
        foreach ($groups as $taskUuid => $group) {
            $taskName = 'Unlinked feedback';
            if ($taskUuid !== 'unlinked') {
                $task = $this->tasks->findByUuid($taskUuid);
                $taskName = $task?->getName() ?? 'Task ' . $taskUuid;
            }
            $rows[] = [
                'taskUuid' => $taskUuid === 'unlinked' ? null : $taskUuid,
                'taskName' => $taskName,
                'responses' => count($group),
                'averageDiscomfort' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<WorkerFeedback> $items @return list<array<string, mixed>> */
    private function groupByTaskType(array $items): array
    {
        $groups = [];
        $labels = [];
        foreach ($items as $item) {
            $key = 'unlinked';
            $label = 'Unlinked feedback';
            if ($item->taskUuid !== null) {
                $task = $this->tasks->findByUuid($item->taskUuid);
                $key = $task?->getTaskCode() ?: $item->taskUuid;
                $label = $task?->getTaskCode() ?: ($task?->getName() ?? 'Task ' . $item->taskUuid);
            }
            $groups[$key][] = $item;
            $labels[$key] = $label;
        }

        $rows = [];
        foreach ($groups as $taskType => $group) {
            $rows[] = [
                'taskType' => $taskType === 'unlinked' ? null : $taskType,
                'label' => $labels[$taskType] ?? $taskType,
                'responses' => count($group),
                'averageDiscomfort' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
                'averagePain30Day' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->pain30DayLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<WorkerFeedback> $items @return list<array<string, mixed>> */
    private function groupByDepartment(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item->departmentUuid ?? 'unassigned';
            $groups[$key][] = $item;
        }

        $rows = [];
        foreach ($groups as $departmentUuid => $group) {
            $rows[] = [
                'departmentUuid' => $departmentUuid === 'unassigned' ? null : $departmentUuid,
                'responses' => count($group),
                'averageDiscomfort' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
            ];
        }

        usort($rows, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);

        return $rows;
    }

    /** @param list<WorkerFeedback> $items @return list<array<string, mixed>> */
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
                'averageDiscomfort' => $this->avg($group, static fn(WorkerFeedback $item): int => $item->discomfortLevel),
            ];
        }

        return $rows;
    }

    /** @param list<WorkerFeedback> $items */
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
