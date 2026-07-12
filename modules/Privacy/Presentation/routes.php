<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->add('GET', '/privacy', [\WorkEddy\Modules\Privacy\Presentation\PrivacyPageController::class, 'index'], ['auth']);
    $routes->add('GET', '/privacy/consent', [\WorkEddy\Modules\Privacy\Presentation\PrivacyPageController::class, 'consent'], ['auth']);
    $routes->add('GET', '/privacy/retention', [\WorkEddy\Modules\Privacy\Presentation\PrivacyPageController::class, 'retention'], ['auth']);
    $routes->add('GET', '/privacy/video-access-log', [\WorkEddy\Modules\Privacy\Presentation\PrivacyPageController::class, 'videoAccessLog'], ['auth']);

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('POST', '/privacy/video-consents', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'recordConsent'], ['auth']);
        $api->add('GET', '/privacy/video-consents', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'listVideoConsents'], ['auth']);
        $api->add('POST', '/privacy/video-access-logs', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'logVideoAccess'], ['auth']);
        $api->add('GET', '/privacy/video-access-logs', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'listVideoAccessLogs'], ['auth']);
        $api->add('POST', '/privacy/signed-video-access', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'issueSignedVideoAccess'], ['auth']);
        $api->add('GET', '/privacy/signed-video-access/{token}', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'readSignedVideoAccess']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/assessments/{assessmentId:' . $uuid . '}/video-assets/{storageFileUuid:' . $uuid . '}/audit', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'listVideoAssetActivity'], ['auth']);
        $api->add('GET', '/organizations/{id:' . $uuid . '}/privacy/retention-policy', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'getRetentionPolicy'], ['auth']);
        $api->add('PUT', '/organizations/{id:' . $uuid . '}/privacy/retention-policy', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'updateRetentionPolicy'], ['auth']);
        $api->add('POST', '/assessments/{assessmentId:' . $uuid . '}/privacy/enforce-retention', [\WorkEddy\Modules\Privacy\Presentation\PrivacyController::class, 'enforceVideoRetention'], ['auth']);
    });
};
