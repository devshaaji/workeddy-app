<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Config\RuntimeEnvironmentValidator;

final class SystemController
{
    public function __construct(private ?RuntimeEnvironmentValidator $validator = null) {}

    public function health(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data' => RuntimeHealth::liveness('WorkEddy'),
            'meta' => ['timestamp' => gmdate(DATE_ATOM), 'version' => '1'],
        ]);
    }

    public function ready(Request $request): Response
    {
        $readiness = RuntimeHealth::readiness($this->validator());

        return Response::json([
            'success' => true,
            'data' => [
                ...RuntimeHealth::liveness('WorkEddy'),
                'status' => $readiness['status'],
                'checks' => $readiness['checks'],
            ],
            'meta' => ['timestamp' => gmdate(DATE_ATOM), 'version' => '1'],
        ], $readiness['status'] === 'ok' ? 200 : 503);
    }

    public function welcome(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data' => ['message' => 'Welcome to the WorkEddy Runtime API'],
            'meta' => ['timestamp' => gmdate(DATE_ATOM), 'version' => '1'],
        ]);
    }

    private function validator(): RuntimeEnvironmentValidator
    {
        if ($this->validator instanceof RuntimeEnvironmentValidator) {
            return $this->validator;
        }

        $root = dirname(__DIR__, 2);
        $this->validator = new RuntimeEnvironmentValidator(new ConfigLoader($root . '/config'), $root);

        return $this->validator;
    }
}
