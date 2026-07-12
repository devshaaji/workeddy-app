<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Config;

final class RuntimeDiagnosticResult
{
    /**
     * @param list<array{name:string,status:string,message:string,severity:string,context:array<string, mixed>}> $checks
     */
    public function __construct(public readonly array $checks) {}

    public function passed(bool $strict = false): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }

            if ($strict && $check['status'] === 'warn') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $strict = false): array
    {
        return [
            'success' => $this->passed($strict),
            'strict' => $strict,
            'summary' => [
                'passed' => count(array_filter($this->checks, static fn(array $check): bool => $check['status'] === 'pass')),
                'warnings' => count(array_filter($this->checks, static fn(array $check): bool => $check['status'] === 'warn')),
                'failures' => count(array_filter($this->checks, static fn(array $check): bool => $check['status'] === 'fail')),
            ],
            'checks' => $this->checks,
        ];
    }
}
