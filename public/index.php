<?php

declare(strict_types=1);

use WorkEddy\Platform\Http\ExceptionHandler;
use WorkEddy\Platform\Http\HttpKernel;
use WorkEddy\Platform\Http\Request;

define('APP_START', microtime(true));
define('APP_ROOT', dirname(__DIR__));

$container = require APP_ROOT . '/bootstrap/app.php';

$exceptionHandler = $container->get(ExceptionHandler::class);
$request = null;

try {
    $kernel = $container->get(HttpKernel::class);
    $request = Request::capture();
    $kernel->handle($request)->send();
} catch (Throwable $exception) {
    $exceptionHandler->handle($exception, $request)->send();
}
