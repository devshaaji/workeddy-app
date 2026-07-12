<?php

declare(strict_types=1);

use WorkEddy\Modules\Payment\Authorization\PaymentPermissions;
use WorkEddy\Modules\Payment\Presentation\PaymentApiController;
use WorkEddy\Modules\Payment\Presentation\PaymentPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $routes->group('/payment', function (RouteRegistrar $page): void {
        $page->add('GET', '', [PaymentPageController::class, 'index'], ['permission:' . PaymentPermissions::VIEW_PAYMENTS]);
    });

    $routes->group('/api/v1/payment', function (RouteRegistrar $api): void {
        $api->add('GET', '/records', [PaymentApiController::class, 'list'], ['permission:' . PaymentPermissions::VIEW_PAYMENTS]);
        $api->add('POST', '/manual', [PaymentApiController::class, 'recordManual'], ['permission:' . PaymentPermissions::RECORD_PAYMENT]);
        $api->add('POST', '/checkout', [PaymentApiController::class, 'checkout'], ['permission:' . PaymentPermissions::RECORD_PAYMENT]);
        $api->add('POST', '/webhook', [PaymentApiController::class, 'webhook']);
        $api->add('POST', '/webhook/{gateway}', [PaymentApiController::class, 'gatewayWebhook']);
    });

    $routes->group('/admin/payment', function (RouteRegistrar $page): void {
        $page->add('GET', '/', [PaymentPageController::class, 'index'], ['permission:' . PaymentPermissions::VIEW_PAYMENTS]);
    });

    $routes->group('/api/v1/admin/payment', function (RouteRegistrar $api): void {
        $api->add('GET', '/records', [PaymentApiController::class, 'list'], ['permission:' . PaymentPermissions::VIEW_PAYMENTS]);
        $api->add('POST', '/manual', [PaymentApiController::class, 'recordManual'], ['permission:' . PaymentPermissions::RECORD_PAYMENT]);
        $api->add('POST', '/checkout', [PaymentApiController::class, 'checkout'], ['permission:' . PaymentPermissions::RECORD_PAYMENT]);
        // Webhooks don't use standard session auth permission middleware usually
        $api->add('POST', '/webhook', [PaymentApiController::class, 'webhook']);
        $api->add('POST', '/webhook/{gateway}', [PaymentApiController::class, 'gatewayWebhook']);
    });
};
