<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Support;

final class CsvSecurity
{
    public static function value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $value = (string) $value;

        return self::isFormulaLike($value) ? "'" . $value : $value;
    }

    private static function isFormulaLike(string $value): bool
    {
        $trimmed = ltrim($value);
        if ($trimmed === '') {
            return false;
        }

        return in_array($trimmed[0], ['=', '+', '-', '@'], true);
    }
}
