<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Presentation;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Platform\Session\UserContext;

final class AssessmentPageData
{
    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx, string $title): array
    {
        $orgUuid = $ctx->organizationUuid;
        $apiBase = $orgUuid !== null && $orgUuid !== ''
            ? '/api/v1/organizations/' . $orgUuid . '/assessments'
            : '/api/v1/assessments';

        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Assessment workflow',
            'organizationUuid' => $orgUuid,
            'apiBase' => $apiBase,
            'can' => [
                'createAssessment' => in_array(AssessmentPermissions::CREATE, $ctx->privileges, true),
                'updateAssessment' => in_array(AssessmentPermissions::UPDATE, $ctx->privileges, true),
                'reviewAssessment' => in_array(AssessmentPermissions::REVIEW, $ctx->privileges, true),
                'uploadVideo' => in_array(AssessmentPermissions::VIDEO_UPLOAD, $ctx->privileges, true),
            ],
        ];
    }
}
