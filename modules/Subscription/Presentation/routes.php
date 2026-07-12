<?php

declare(strict_types=1);

use WorkEddy\Modules\Subscription\Authorization\SubscriptionPermissions;
use WorkEddy\Modules\Subscription\Presentation\SubscriptionApiController;
use WorkEddy\Modules\Subscription\Presentation\SubscriptionPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuid = '[0-9a-fA-F-]{36}';

    $routes->module('Subscription', static function (RouteRegistrar $module) use ($uuid): void {
        $module->group('/subscriptions', static function (RouteRegistrar $web) use ($uuid): void {
            $web->add('GET', '', [SubscriptionPageController::class, 'index'], ['permission:' . SubscriptionPermissions::VIEW]);
            $web->add('GET', '/settings', [SubscriptionPageController::class, 'settings'], ['permission:' . SubscriptionPermissions::MANAGE_PLANS]);
            $web->add('GET', '/{uuid:' . $uuid . '}', [SubscriptionPageController::class, 'show'], ['permission:' . SubscriptionPermissions::VIEW]);
        }, ['auth']);

        $module->group('/api/v1/subscriptions', static function (RouteRegistrar $api) use ($uuid): void {
            $api->add('GET', '/plans', [SubscriptionApiController::class, 'listPlans'], ['permission:' . SubscriptionPermissions::VIEW]);
            $api->add('POST', '/plans', [SubscriptionApiController::class, 'createPlan'], ['permission:' . SubscriptionPermissions::MANAGE_PLANS]);
            $api->add('PATCH', '/plans/{code}', [SubscriptionApiController::class, 'updatePlan'], ['permission:' . SubscriptionPermissions::MANAGE_PLANS]);
            $api->add('GET', '', [SubscriptionApiController::class, 'listSubscriptions'], ['permission:' . SubscriptionPermissions::VIEW]);
            $api->add('POST', '', [SubscriptionApiController::class, 'activate'], ['permission:' . SubscriptionPermissions::ACTIVATE]);
            $api->add('GET', '/{uuid:' . $uuid . '}', [SubscriptionApiController::class, 'view'], ['permission:' . SubscriptionPermissions::VIEW]);
            $api->add('GET', '/{uuid:' . $uuid . '}/usage', [SubscriptionApiController::class, 'usage'], ['permission:' . SubscriptionPermissions::VIEW_USAGE]);
            $api->add('POST', '/{uuid:' . $uuid . '}/suspend', [SubscriptionApiController::class, 'suspend'], ['permission:' . SubscriptionPermissions::SUSPEND]);
            $api->add('POST', '/{uuid:' . $uuid . '}/reactivate', [SubscriptionApiController::class, 'reactivate'], ['permission:' . SubscriptionPermissions::REACTIVATE]);
            $api->add('POST', '/{uuid:' . $uuid . '}/expire', [SubscriptionApiController::class, 'expire'], ['permission:' . SubscriptionPermissions::EXPIRE]);
            $api->add('POST', '/{uuid:' . $uuid . '}/cancel', [SubscriptionApiController::class, 'cancel'], ['permission:' . SubscriptionPermissions::CANCEL]);
            $api->add('POST', '/{uuid:' . $uuid . '}/change-plan', [SubscriptionApiController::class, 'changePlan'], ['permission:' . SubscriptionPermissions::CHANGE_PLAN]);
        }, ['auth']);
    });
};
