<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Http;

use WorkEddy\Platform\Config\RuntimeEnvironmentValidator;

final class RuntimeHealth
{
    /**
     * @return array<string, mixed>
     */
    public static function liveness(string $runtime): array
    {
        return [
            'app' => 'WorkEddy',
            'tagline' => 'Connect. Discover. Grow.',
            'runtime' => $runtime,
            'status' => 'ok',
            'timestamp' => gmdate(DATE_ATOM),
        ];
    }

    /**
     * @return array{status:string,checks:list<array<string,mixed>>}
     */
    public static function readiness(RuntimeEnvironmentValidator $validator): array
    {
        $result = $validator->diagnose(strict: false);
        $status = $result->passed(strict: false) ? 'ok' : 'degraded';

        return [
            'status' => $status,
            'checks' => $result->checks,
        ];
    }
}
