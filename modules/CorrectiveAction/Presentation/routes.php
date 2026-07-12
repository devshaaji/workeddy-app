<?php

declare(strict_types=1);

use WorkEddy\Modules\CorrectiveAction\Presentation\CorrectiveActionController;
use WorkEddy\Modules\CorrectiveAction\Presentation\CorrectiveActionPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/corrective-actions', [CorrectiveActionPageController::class, 'actions'], ['auth']);
        $web->add('GET', '/corrective-actions/controls', [CorrectiveActionPageController::class, 'controls'], ['auth']);
        $web->add('GET', '/corrective-actions/recommendations', [CorrectiveActionPageController::class, 'recommendations'], ['auth']);
        $web->add('GET', '/corrective-actions/{actionId:' . $uuid . '}', [CorrectiveActionPageController::class, 'show'], ['auth']);
        $web->add('GET', '/corrective-actions/{actionId:' . $uuid . '}/evidence', [CorrectiveActionPageController::class, 'evidence'], ['auth']);
    });

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/corrective-actions', [CorrectiveActionController::class, 'listActions'], ['auth']);
        $api->add('GET', '/corrective-actions/{actionId:' . $uuid . '}', [CorrectiveActionController::class, 'getAction'], ['auth']);
        $api->add('GET', '/assessments/{assessmentId:' . $uuid . '}/corrective-action-recommendations', [CorrectiveActionController::class, 'listRecommendations'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/corrective-action-recommendations', [CorrectiveActionController::class, 'generateRecommendations'], ['auth']);
        $api->add('POST', '/corrective-action-recommendations/{recommendationId:' . $uuid . '}/accept', [CorrectiveActionController::class, 'acceptRecommendation'], ['auth']);
        $api->add('POST', '/corrective-action-recommendations/{recommendationId:' . $uuid . '}/reject', [CorrectiveActionController::class, 'rejectRecommendation'], ['auth']);
        $api->add('POST', '/corrective-action-recommendations/{recommendationId:' . $uuid . '}/assign', [CorrectiveActionController::class, 'assign'], ['auth']);
        $api->add('POST', '/corrective-actions/{actionId:' . $uuid . '}/status', [CorrectiveActionController::class, 'updateStatus'], ['auth']);
        $api->add('POST', '/corrective-actions/{actionId:' . $uuid . '}/evidence', [CorrectiveActionController::class, 'uploadEvidence'], ['auth']);
        $api->add('POST', '/corrective-actions/{actionId:' . $uuid . '}/verify', [CorrectiveActionController::class, 'verify'], ['auth']);
        $api->add('POST', '/corrective-actions/{actionId:' . $uuid . '}/follow-up', [CorrectiveActionController::class, 'scheduleFollowUp'], ['auth']);
        $api->add('GET', '/corrective-action-library', [CorrectiveActionController::class, 'listLibrary'], ['auth']);
        $api->add('POST', '/corrective-action-library', [CorrectiveActionController::class, 'upsertLibraryItem'], ['auth']);
        $api->add('GET', '/recommendation-rules', [CorrectiveActionController::class, 'listRules'], ['auth']);
        $api->add('POST', '/recommendation-rules', [CorrectiveActionController::class, 'upsertRule'], ['auth']);
    });
};
