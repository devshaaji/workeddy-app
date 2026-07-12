<?php

declare(strict_types=1);

use WorkEddy\Modules\Website\Presentation\Controllers\PageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $routes->module('website', static function (RouteRegistrar $routes): void {
        // Public Pages (mapped to root)
        $routes->add('GET', '/', [PageController::class, 'home']);
        $routes->add('GET', '/about-us', [PageController::class, 'about']);
        $routes->add('GET', '/founder-message', [PageController::class, 'founderMessage']);
        $routes->add('GET', '/why-us', [PageController::class, 'whyUs']);
        $routes->add('GET', '/contact-us', [PageController::class, 'contactUs']);
        $routes->add('POST', '/contact-us/submit', [PageController::class, 'submitContactForm']);
        $routes->add('GET', '/plans', [PageController::class, 'plans']);
        $routes->add('GET', '/privacy-policy', [PageController::class, 'privacyPolicy']);
        $routes->add('GET', '/terms-of-service', [PageController::class, 'termsOfService']);
    });
};
