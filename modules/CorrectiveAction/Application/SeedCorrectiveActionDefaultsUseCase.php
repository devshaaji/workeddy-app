<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;

final class SeedCorrectiveActionDefaultsUseCase
{
    public function __construct(private readonly ICorrectiveActionRepository $repository) {}

    /** @return array{library_items:int,recommendation_rules:int} */
    public function execute(): array
    {
        $items = $this->libraryItems();
        foreach ($items as $item) {
            $this->repository->upsertLibraryItem($item);
        }

        $rules = $this->rules();
        foreach ($rules as $rule) {
            $this->repository->upsertRecommendationRule($rule);
        }

        return ['library_items' => count($items), 'recommendation_rules' => count($rules)];
    }

    /** @return list<CorrectiveActionLibraryItem> */
    private function libraryItems(): array
    {
        return [
            new CorrectiveActionLibraryItem(id: null, uuid: '00000000-0000-4000-8000-000000000401', title: 'Install adjustable lift table', description: 'Reduce manual lifting force and awkward trunk flexion.', reason: 'Frequent high-force lifts are driving elevated trunk flexion and overload.', controlType: 'lift_assist', hierarchyLevel: 'engineering', riskFactor: 'manual_handling', taskType: 'lifting', industry: null, priority: 'high', dueDays: 30, evidenceRequired: true, evidenceTypes: ['photo', 'worker_feedback'], followUpDays: 14, isActive: true),
            new CorrectiveActionLibraryItem(id: null, uuid: '00000000-0000-4000-8000-000000000402', title: 'Reposition work within neutral reach zone', description: 'Move materials and controls closer to reduce overreach and shoulder elevation.', reason: 'Excessive reach distance is sustaining awkward shoulder posture.', controlType: 'workstation_redesign', hierarchyLevel: 'engineering', riskFactor: 'awkward_posture', taskType: 'reaching', industry: null, priority: 'high', dueDays: 21, evidenceRequired: true, evidenceTypes: ['photo', 'follow_up_observation'], followUpDays: 14, isActive: true),
            new CorrectiveActionLibraryItem(id: null, uuid: '00000000-0000-4000-8000-000000000403', title: 'Introduce task rotation and micro-break schedule', description: 'Reduce sustained posture and repetitive exposure through planned rotation.', reason: 'Exposure duration is too high for the current repetitive pattern.', controlType: 'process', hierarchyLevel: 'administrative', riskFactor: 'repetition', taskType: 'assembly', industry: null, priority: 'medium', dueDays: 14, evidenceRequired: false, evidenceTypes: ['document', 'worker_feedback'], followUpDays: 21, isActive: true),
            new CorrectiveActionLibraryItem(id: null, uuid: '00000000-0000-4000-8000-000000000404', title: 'Provide manual handling refresher training', description: 'Train workers on load handling, team lift triggers, and early discomfort reporting.', reason: 'Observed handling technique and escalation triggers are inconsistent across the team.', controlType: 'training', hierarchyLevel: 'administrative', riskFactor: 'manual_handling', taskType: 'lifting', industry: null, priority: 'medium', dueDays: 14, evidenceRequired: false, evidenceTypes: ['document', 'worker_feedback'], followUpDays: 21, isActive: true),
            new CorrectiveActionLibraryItem(id: null, uuid: '00000000-0000-4000-8000-000000000405', title: 'Review tool weight and grip design', description: 'Replace or modify tools that drive wrist deviation, high grip force, or shoulder load.', reason: 'Tool design is contributing avoidable force and non-neutral grip posture.', controlType: 'tool_redesign', hierarchyLevel: 'substitution', riskFactor: 'force', taskType: 'tool_use', industry: null, priority: 'high', dueDays: 30, evidenceRequired: true, evidenceTypes: ['photo', 'receipt'], followUpDays: 14, isActive: true),
        ];
    }

    /** @return list<RecommendationRule> */
    private function rules(): array
    {
        return [
            new RecommendationRule(null, '00000000-0000-4000-8000-000000000501', ['riskFactor' => 'manual_handling', 'minScore' => 70], ['libraryItemUuid' => '00000000-0000-4000-8000-000000000401'], 300, true),
            new RecommendationRule(null, '00000000-0000-4000-8000-000000000502', ['riskFactor' => 'awkward_posture', 'minScore' => 60], ['libraryItemUuid' => '00000000-0000-4000-8000-000000000402'], 250, true),
            new RecommendationRule(null, '00000000-0000-4000-8000-000000000503', ['riskFactor' => 'repetition', 'minScore' => 50], ['libraryItemUuid' => '00000000-0000-4000-8000-000000000403'], 200, true),
            new RecommendationRule(null, '00000000-0000-4000-8000-000000000504', ['riskFactor' => 'manual_handling', 'minScore' => 40], ['libraryItemUuid' => '00000000-0000-4000-8000-000000000404'], 150, true),
            new RecommendationRule(null, '00000000-0000-4000-8000-000000000505', ['riskFactor' => 'force', 'minScore' => 60], ['libraryItemUuid' => '00000000-0000-4000-8000-000000000405'], 220, true),
        ];
    }
}
