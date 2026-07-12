<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Support;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Clock\SystemClock;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * IMPORTANT: The relative-time bug you saw ("1 hour ago" instead of
 * "Just now") is a timezone problem, not a math problem.
 *
 * `new DateTimeImmutable($string)` uses PHP's default timezone
 * (date.timezone ini setting) whenever $string has no explicit UTC
 * offset. If your database writes naive timestamps (e.g. MySQL NOW())
 * in one zone, but SystemClock::now() returns a DateTimeImmutable in
 * a different zone, the two instants you diff are not actually
 * comparable — even though both "look" correct in isolation.
 *
 * Fix: store everything in UTC (industry standard), and always parse
 * naive strings explicitly as UTC (or whatever single zone your app
 * guarantees for storage). Do NOT rely on php.ini's date.timezone.
 *
 * CONFIRMED CAUSE OF YOUR BUG: SystemClock defaults to Africa/Lagos
 * (UTC+1), but naive date strings from your DB were being parsed as
 * UTC. That 1-hour gap between "how now() is computed" and "how
 * stored dates are interpreted" is exactly what produced "1 hour ago"
 * for events that had literally just happened.
 *
 * This version defaults naiveTimezone to the same APP_TIMEZONE env
 * var SystemClock uses, so the two can no longer silently disagree.
 *
 * Longer term, the industry-standard fix is to store all timestamps
 * in UTC (DB columns, NOW() calls, etc.) and only convert to
 * Africa/Lagos at the display layer. That removes this entire class
 * of bug permanently, since "now" and "stored date" are always in the
 * same zone at the point of comparison. Matching the app timezone
 * here is the safe, no-migration-needed fix for today.
 */
final class DateFormatter
{
    private static ?IClock $clock = null;

    /** Timezone assumed for date strings that carry no offset/zone info. */
    private static ?string $naiveTimezone = null;

    private function __construct() {}

    public static function useClock(IClock $clock): void
    {
        self::$clock = $clock;
    }

    public static function resetClock(): void
    {
        self::$clock = null;
    }

    /**
     * Sets the timezone assumed when parsing a date string that has no
     * explicit offset (e.g. "2026-07-03 14:32:10"). Defaults to the
     * same APP_TIMEZONE SystemClock uses (falling back to
     * Africa/Lagos), so "now" and "stored date" are always compared
     * in the same zone. Only override this if you can guarantee every
     * naive string your app parses truly originates in a different,
     * specific zone.
     */
    public static function useNaiveTimezone(string $timezone): void
    {
        self::$naiveTimezone = $timezone;
    }

    public static function resetNaiveTimezone(): void
    {
        self::$naiveTimezone = 'Africa/Lagos';
    }

    private static function resolvedNaiveTimezone(): string
    {
        return self::$naiveTimezone ?? ($_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos');
    }

    /**
     * Parse a naive database timestamp (e.g. "2026-07-03 14:32:10") into a
     * DateTimeImmutable using the application's configured timezone. This
     * removes the hidden dependency on PHP's default date.timezone ini
     * setting and ensures stored timestamps are interpreted consistently.
     */
    public static function fromNaiveDbString(?string $date): ?DateTimeImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($date, new DateTimeZone(self::resolvedNaiveTimezone()));
        } catch (Throwable) {
            return null;
        }
    }

    public static function relative(DateTimeInterface|string|null $date): string
    {
        $dt = self::parse($date);
        if ($dt === null) {
            return '';
        }

        $now  = self::now();
        $diff = $now->getTimestamp() - $dt->getTimestamp();

        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = intdiv($diff, 60);
            return $mins === 1 ? '1 min ago' : "{$mins} mins ago";
        }
        if ($diff < 86400) {
            $hours = intdiv($diff, 3600);
            return $hours === 1 ? '1 hour ago' : "{$hours} hours ago";
        }
        if ($diff < 172800) {
            return 'Yesterday';
        }
        if ($diff < 604800) {
            $days = intdiv($diff, 86400);
            return "{$days} days ago";
        }
        if ($diff < 2592000) {
            $weeks = intdiv($diff, 604800);
            return $weeks === 1 ? '1 week ago' : "{$weeks} weeks ago";
        }
        if ($diff < 31536000) {
            $months = intdiv($diff, 2592000);
            return $months === 1 ? '1 month ago' : "{$months} months ago";
        }

        $years = intdiv($diff, 31536000);
        return $years === 1 ? '1 year ago' : "{$years} years ago";
    }

    public static function readable(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'M d, Y');
    }

    public static function readableDateTime(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'M d, Y h:i A');
    }

    public static function shortDate(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'd M Y');
    }

    public static function time(DateTimeInterface|string|null $date): string
    {
        return self::format($date, 'h:i A');
    }

    public static function iso(DateTimeInterface|string|null $date): string
    {
        return self::format($date, DateTimeInterface::ATOM);
    }

    /** @return array{date: string, time: string, relative: string, timezone: string, datetime: string, iso: string} */
    public static function timeline(DateTimeInterface|string|null $date): array
    {
        $dt = self::parse($date);

        return [
            'date'     => $dt !== null ? self::readable($dt) : '',
            'time'     => $dt !== null ? self::time($dt) : '',
            'relative' => $dt !== null ? self::relative($dt) : '',
            'timezone' => $dt !== null ? $dt->format('P') : '',
            'datetime' => $dt !== null ? self::readableDateTime($dt) : '',
            'iso'      => $dt !== null ? self::iso($dt) : '',
        ];
    }

    private static function format(DateTimeInterface|string|null $date, string $format): string
    {
        $dt = self::parse($date);
        return $dt !== null ? $dt->format($format) : '';
    }

    private static function parse(DateTimeInterface|string|null $date): ?DateTimeImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date);
        }

        try {
            return new DateTimeImmutable($date, new DateTimeZone(self::resolvedNaiveTimezone()));
        } catch (Throwable) {
            return null;
        }
    }

    private static function now(): DateTimeImmutable
    {
        return (self::$clock ?? new SystemClock())->now();
    }
}
