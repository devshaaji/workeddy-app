<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics\Application;

use WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissions;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class ScoreErgonomicAssessmentUseCase
{
    public function __construct(
        private readonly AssessmentEngine $engine,
        private readonly IPermissionService $permissions,
    ) {}

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    public function execute(string $model, string $inputType, array $metrics, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, ErgonomicsPermissions::SCORE);

        $model = strtolower(trim($model));
        $inputType = strtolower(trim($inputType));
        $this->engine->validateCombination($model, $inputType);
        $result = $this->engine->assess($model, $metrics);

        return [
            'model' => $model,
            'inputType' => $inputType,
            'scoreSource' => $inputType === 'video' ? 'ai_estimated' : 'manual',
            'score' => [
                'raw' => $result['raw_score'],
                'normalized' => $result['normalized_score'],
                'riskLevel' => $result['risk_level'],
                'riskCategory' => $result['risk_category'],
                'recommendation' => $result['recommendation'],
            ],
            'details' => $result,
            'algorithmVersion' => $result['algorithm_version'] ?? null,
        ];
    }
}
