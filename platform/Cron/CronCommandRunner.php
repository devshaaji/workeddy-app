<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Cron;

final class CronCommandRunner
{
    /**
     * @param list<string> $command
     */
    public static function runForStatus(array $command): int
    {
        passthru(self::shellCommand($command), $exitCode);

        return $exitCode;
    }

    /**
     * @param list<string> $command
     */
    public static function run(array $command): never
    {
        exit(self::runForStatus($command));
    }

    /**
     * @param list<string> $command
     */
    private static function shellCommand(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }
}
