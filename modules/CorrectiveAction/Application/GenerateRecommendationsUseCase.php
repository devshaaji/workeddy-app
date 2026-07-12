<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlRecommendationService;
use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionLibraryItem;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveActionRecommendation;
use WorkEddy\Modules\CorrectiveAction\Domain\RecommendationRule;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class GenerateRecommendationsUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly ICorrectiveActionRepository $correctiveActions,
        private readonly ControlRecommendationService $engine,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function execute(string $assessmentUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::GENERATE_RECOMMENDATIONS);
        $assessment = $this->assessments->findByUuid(UuidSupport::requireValid($assessmentUuid, 'assessmentUuid'));
        if ($assessment === null || ($actor->organizationId !== null && $actor->organizationId !== $assessment->getOrganizationId())) {
            throw new NotFoundException('Assessment not found.');
        }
        if (!in_array($assessment->getStatus(), ['reviewed', 'locked'], true)) {
            throw new ValidationException(['assessment' => 'Recommendations require a reviewed or locked assessment.']);
        }

        $score = $assessment->getFinalScoreData() ?? $assessment->getInitialScoreData();
        $rows = $this->matchedLibraryRows(
            $assessment->getModel(),
            $assessment->getMetrics(),
            $score,
            $assessment->getRiskFactors(),
        );
        $rows = $this->mergeRows($rows, $this->engine->recommend($assessment->getModel(), $assessment->getMetrics(), $score));
        $recommendations = [];
        $rankOrder = 1;
        foreach ($rows as $row) {
            $recommendations[] = new CorrectiveActionRecommendation(
                id: null,
                uuid: UuidSupport::generate(),
                organizationId: $assessment->getOrganizationId(),
                organizationUuid: $assessment->getOrganizationUuid(),
                assessmentUuid: $assessment->getUuid(),
                libraryItemUuid: isset($row['library_item_uuid']) ? (string) $row['library_item_uuid'] : null,
                controlCode: (string) ($row['control_code'] ?? ''),
                title: (string) ($row['title'] ?? 'Corrective action'),
                description: (string) ($row['rationale'] ?? ''),
                reason: isset($row['reason']) ? (string) $row['reason'] : null,
                hierarchyLevel: (string) ($row['hierarchy_level'] ?? 'administrative'),
                controlType: (string) ($row['control_type'] ?? 'permanent'),
                priority: $this->rowPriority($row),
                rankOrder: $rankOrder++,
                expectedRiskReductionPct: (float) ($row['expected_risk_reduction_pct'] ?? 0),
                dueDays: (int) ($row['time_to_deploy_days'] ?? 30),
                followUpDays: isset($row['follow_up_days']) ? (int) $row['follow_up_days'] : null,
                status: 'generated',
                evidence: is_array($row['evidence'] ?? null) ? $row['evidence'] : [],
            );
        }

        $this->correctiveActions->replaceRecommendationsForAssessment($assessment->getUuid(), $recommendations);
        $views = array_map(static fn(CorrectiveActionRecommendation $recommendation): array => $recommendation->toView(), $recommendations);
        $this->audit->record('corrective_action.recommendations_generated', 'assessment', $assessment->getUuid(), afterState: ['recommendations' => $views], actorId: (string) $actor->userId, actorType: 'user');

        return $views;
    }

    /**
     * @param array<string,mixed> $metrics
     * @param array<string,mixed> $score
     * @param list<string> $riskFactors
     * @return list<array<string,mixed>>
     */
    private function matchedLibraryRows(string $model, array $metrics, array $score, array $riskFactors): array
    {
        $matched = [];
        foreach ($this->correctiveActions->listRecommendationRules(['status' => 'active']) as $rule) {
            if (!$rule->isActive || !$this->ruleMatches($rule, $model, $metrics, $score, $riskFactors)) {
                continue;
            }

            $itemUuid = (string) ($rule->action['libraryItemUuid'] ?? '');
            if ($itemUuid === '') {
                continue;
            }

            $item = $this->correctiveActions->findLibraryItemByUuid($itemUuid);
            if (!$item instanceof CorrectiveActionLibraryItem || !$item->isActive) {
                continue;
            }

            $matched[] = $this->libraryItemToRow($item, $rule, $score);
        }

        usort($matched, static function (array $a, array $b): int {
            $hierarchy = ['elimination' => 0, 'substitution' => 1, 'engineering' => 2, 'administrative' => 3, 'ppe' => 4];
            $aRank = $hierarchy[(string) ($a['hierarchy_level'] ?? 'administrative')] ?? 99;
            $bRank = $hierarchy[(string) ($b['hierarchy_level'] ?? 'administrative')] ?? 99;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return ((int) ($b['rule_weight'] ?? 0)) <=> ((int) ($a['rule_weight'] ?? 0));
        });

        return $matched;
    }

    /**
     * @param array<string,mixed> $metrics
     * @param array<string,mixed> $score
     * @param list<string> $riskFactors
     */
    private function ruleMatches(RecommendationRule $rule, string $model, array $metrics, array $score, array $riskFactors): bool
    {
        $condition = $rule->condition;
        $assessmentType = strtolower(trim((string) ($condition['assessmentType'] ?? '')));
        if ($assessmentType !== '' && $assessmentType !== 'all' && $assessmentType !== strtolower($model)) {
            return false;
        }

        $riskFactor = trim((string) ($condition['riskFactor'] ?? ''));
        if ($riskFactor !== '' && !in_array($riskFactor, $riskFactors, true)) {
            return false;
        }

        if (isset($condition['minScore']) && (float) ($score['normalized_score'] ?? $score['raw_score'] ?? 0) < (float) $condition['minScore']) {
            return false;
        }

        if (isset($condition['maxScore']) && (float) ($score['normalized_score'] ?? $score['raw_score'] ?? 0) > (float) $condition['maxScore']) {
            return false;
        }

        foreach (['taskType' => 'task_type', 'industry' => 'industry'] as $conditionKey => $metricKey) {
            $expected = trim((string) ($condition[$conditionKey] ?? ''));
            if ($expected !== '' && strtolower((string) ($metrics[$metricKey] ?? '')) !== strtolower($expected)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private function libraryItemToRow(CorrectiveActionLibraryItem $item, RecommendationRule $rule, array $score): array
    {
        $priorityReduction = ['low' => 8.0, 'medium' => 14.0, 'high' => 24.0, 'critical' => 32.0];
        $reduction = $priorityReduction[$item->priority] ?? 14.0;

        return [
            'library_item_uuid' => $item->uuid,
            'control_code' => 'LIB_' . strtoupper(str_replace('-', '', substr($item->uuid, 0, 8))),
            'title' => $item->title,
            'rationale' => $item->description ?? 'Matched corrective action library rule.',
            'reason' => $item->reason ?? $item->description ?? 'Matched corrective action library rule.',
            'hierarchy_level' => $item->hierarchyLevel,
            'control_type' => $item->controlType,
            'expected_risk_reduction_pct' => $reduction,
            'time_to_deploy_days' => $item->dueDays,
            'follow_up_days' => $item->followUpDays,
            'priority' => $item->priority,
            'rule_weight' => $rule->weight,
            'evidence' => [
                'source' => 'corrective_action_library',
                'rule_uuid' => $rule->uuid,
                'risk_factor' => $item->riskFactor,
                'task_type' => $item->taskType,
                'industry' => $item->industry,
                'normalized_score' => $score['normalized_score'] ?? null,
                'evidence_required' => $item->evidenceRequired,
                'evidence_types' => $item->evidenceTypes,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $primary
     * @param list<array<string,mixed>> $fallback
     * @return list<array<string,mixed>>
     */
    private function mergeRows(array $primary, array $fallback): array
    {
        $rows = [];
        $seen = [];
        foreach (array_merge($primary, $fallback) as $row) {
            $key = (string) ($row['library_item_uuid'] ?? $row['control_code'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            $hierarchy = ['elimination' => 0, 'substitution' => 1, 'engineering' => 2, 'administrative' => 3, 'ppe' => 4];
            $aRank = $hierarchy[(string) ($a['hierarchy_level'] ?? 'administrative')] ?? 99;
            $bRank = $hierarchy[(string) ($b['hierarchy_level'] ?? 'administrative')] ?? 99;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            $aWeight = (int) ($a['rule_weight'] ?? 0);
            $bWeight = (int) ($b['rule_weight'] ?? 0);
            if ($aWeight !== $bWeight) {
                return $bWeight <=> $aWeight;
            }

            return ((float) ($b['expected_risk_reduction_pct'] ?? 0)) <=> ((float) ($a['expected_risk_reduction_pct'] ?? 0));
        });

        return array_slice($rows, 0, 5);
    }

    /** @param array<string,mixed> $row */
    private function rowPriority(array $row): string
    {
        $priority = strtolower((string) ($row['priority'] ?? ''));
        if ($priority === 'critical') {
            return 'high';
        }
        if (in_array($priority, ['low', 'medium', 'high'], true)) {
            return $priority;
        }

        return $this->priority((float) ($row['expected_risk_reduction_pct'] ?? 0));
    }

    private function priority(float $reduction): string
    {
        return $reduction >= 25.0 ? 'high' : ($reduction >= 12.0 ? 'medium' : 'low');
    }
}
