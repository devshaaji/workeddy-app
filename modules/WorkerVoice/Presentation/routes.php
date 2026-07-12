<?php

declare(strict_types=1);

use WorkEddy\Modules\WorkerVoice\Presentation\WorkerVoiceController;
use WorkEddy\Modules\WorkerVoice\Presentation\WorkerVoicePageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->group('', function (RouteRegistrar $web) use ($uuid): void {
        $web->add('GET', '/worker-voice', [WorkerVoicePageController::class, 'index'], ['auth']);
        $web->add('GET', '/worker-voice/new', [WorkerVoicePageController::class, 'submit'], ['auth']);
        $web->add('GET', '/worker-voice/trends', [WorkerVoicePageController::class, 'trends'], ['auth']);
        $web->add('GET', '/worker-voice/supervisor/new', [WorkerVoicePageController::class, 'supervisorSubmit'], ['auth']);
        $web->add('GET', '/worker-voice/supervisor/trends', [WorkerVoicePageController::class, 'supervisorTrends'], ['auth']);
        $web->add('GET', '/worker-voice/{feedbackId:' . $uuid . '}', [WorkerVoicePageController::class, 'show'], ['auth']);
    });

    $routes->group('/api/v1', function (RouteRegistrar $api) use ($uuid): void {
        $api->add('GET', '/worker-feedback/questions', [WorkerVoiceController::class, 'questions'], ['auth']);
        $api->add('POST', '/worker-feedback', [WorkerVoiceController::class, 'submit'], ['auth']);
        $api->add('GET', '/worker-feedback', [WorkerVoiceController::class, 'list'], ['auth']);
        $api->add('GET', '/worker-feedback/trends', [WorkerVoiceController::class, 'trends'], ['auth']);
        $api->add('GET', '/worker-feedback/{feedbackId:' . $uuid . '}', [WorkerVoiceController::class, 'get'], ['auth']);
        $api->add('POST', '/supervisor-feedback', [WorkerVoiceController::class, 'submitSupervisor'], ['auth']);
        $api->add('GET', '/supervisor-feedback/trends', [WorkerVoiceController::class, 'supervisorTrends'], ['auth']);
    });
};
