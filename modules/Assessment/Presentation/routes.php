<?php

declare(strict_types=1);

use WorkEddy\Modules\Assessment\Presentation\AssessmentController;
use WorkEddy\Modules\Assessment\Presentation\AssessmentPageController;
use WorkEddy\Modules\Assessment\Presentation\AssessmentWorkerController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/assessments', [AssessmentPageController::class, 'index'], ['auth']);
        $web->add('GET', '/assessments/video', [AssessmentPageController::class, 'videoCapture'], ['auth']);
        $web->add('GET', '/assessments/new-manual', [AssessmentPageController::class, 'manualForm'], ['auth']);
        $web->add('GET', '/assessments/reviewer-queue', [AssessmentPageController::class, 'reviewerQueue'], ['auth']);
        $web->add('GET', '/assessments/{assessmentId:' . $uuid . '}', [AssessmentPageController::class, 'show'], ['auth']);
        $web->add('GET', '/assessments/{assessmentId:' . $uuid . '}/review', [AssessmentPageController::class, 'review'], ['auth']);
        $web->add('GET', '/assessments/{assessmentId:' . $uuid . '}/validation-reviews', [AssessmentPageController::class, 'validationReviews'], ['auth']);
        $web->add('GET', '/assessments/{assessmentId:' . $uuid . '}/heatmap', [AssessmentPageController::class, 'heatmap'], ['auth']);
        $web->add('GET', '/assessments/{assessmentId:' . $uuid . '}/video-evidence', [AssessmentPageController::class, 'videoEvidence'], ['auth']);
        $web->add('GET', '/assessments/comparisons', [AssessmentPageController::class, 'comparisons'], ['auth']);
        $web->add('GET', '/assessments/comparisons/new', [AssessmentPageController::class, 'comparisonCreate'], ['auth']);
        $web->add('GET', '/assessments/comparisons/{comparisonId:' . $uuid . '}', [AssessmentPageController::class, 'comparisonShow'], ['auth']);
    });

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/organizations/{id:' . $uuid . '}/assessments', [AssessmentController::class, 'list'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/assessments/reviewer-queue', [AssessmentController::class, 'reviewerQueue'], ['auth']);
        $api->add('GET', '/assessments', [AssessmentController::class, 'list'], ['auth']);
        $api->add('GET', '/assessments/reviewer-queue', [AssessmentController::class, 'reviewerQueue'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/assessments/{assessmentId:' . $uuid . '}', [AssessmentController::class, 'get'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/assessments/manual', [AssessmentController::class, 'createManual'], ['auth']);
        $api->add('POST', '/organizations/{id:' . $uuid . '}/assessments/video', [AssessmentController::class, 'createVideo'], ['auth']);
        $api->add('GET', '/assessments/{assessmentId:' . $uuid . '}', [AssessmentController::class, 'get'], ['auth']);
        $api->add('PUT', '/assessments/{assessmentId:' . $uuid . '}', [AssessmentController::class, 'update'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/baseline', [AssessmentController::class, 'markBaseline'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/submit', [AssessmentController::class, 'submit'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/videos', [AssessmentController::class, 'attachVideo'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/videos/upload-and-process', [AssessmentController::class, 'uploadVideoForProcessing'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/review/approve', [AssessmentController::class, 'approve'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/review/flag', [AssessmentController::class, 'flag'], ['auth']);
        $api->add('GET', '/assessments/{assessmentId:' . $uuid . '}/validation-reviews', [AssessmentController::class, 'listValidationReviews'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/validation-reviews', [AssessmentController::class, 'submitValidationReview'], ['auth']);
        $api->add('GET', '/comparison-reports', [AssessmentController::class, 'listComparisons'], ['auth']);
        $api->add('POST', '/comparison-reports', [AssessmentController::class, 'generateComparison'], ['auth']);
        $api->add('GET', '/comparison-reports/{comparisonId:' . $uuid . '}', [AssessmentController::class, 'getComparison'], ['auth']);
        $api->add('POST', '/comparison-reports/{comparisonId:' . $uuid . '}/lock', [AssessmentController::class, 'lockComparison'], ['auth']);
        $api->add('POST', '/internal/assessment-video/jobs/next', [AssessmentWorkerController::class, 'nextJob']);
        $api->add('POST', '/internal/assessment-video/jobs/complete', [AssessmentWorkerController::class, 'complete']);
        $api->add('POST', '/internal/assessment-video/jobs/fail', [AssessmentWorkerController::class, 'fail']);
    });
};
