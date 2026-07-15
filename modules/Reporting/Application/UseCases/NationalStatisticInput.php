<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\Reporting\Domain\NationalStatisticCategory;
use WorkEddy\Shared\Exceptions\ValidationException;

/**
 * Shared validation for Create/Update. Kept as a static helper (rather than
 * duplicated inline) since both use cases must enforce identical rules,
 * most importantly: a statistic without a source citation must never save.
 */
final class NationalStatisticInput
{
    /**
     * @param array<string, mixed> $input
     * @return array{title: string, value: string, unit: ?string, category: string,
     *     industryRelevance: ?string, sourceName: string, sourceYear: int,
     *     sourceUrl: string, isPublished: bool}
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Statistic title is required.';
        }

        $value = trim((string) ($input['value'] ?? ''));
        if ($value === '') {
            $errors['value'] = 'Statistic value is required.';
        }

        $unit = isset($input['unit']) ? trim((string) $input['unit']) : null;
        $unit = $unit === '' ? null : $unit;

        $category = trim((string) ($input['category'] ?? ''));
        if ($category === '') {
            $errors['category'] = 'Industry topic area is required.';
        } elseif (!NationalStatisticCategory::isValid($category)) {
            $errors['category'] = 'Unrecognized topic area.';
        }

        $industryRelevance = isset($input['industryRelevance']) ? trim((string) $input['industryRelevance']) : null;
        $industryRelevance = $industryRelevance === '' ? null : $industryRelevance;

        $sourceName = trim((string) ($input['sourceName'] ?? ''));
        if ($sourceName === '') {
            $errors['sourceName'] = 'Source name is required — every statistic must be attributable.';
        }

        $sourceYear = (int) ($input['sourceYear'] ?? 0);
        $currentYear = (int) date('Y');
        if ($sourceYear < 1990 || $sourceYear > $currentYear + 1) {
            $errors['sourceYear'] = 'Enter a valid source year.';
        }

        $sourceUrl = trim((string) ($input['sourceUrl'] ?? ''));
        if ($sourceUrl === '') {
            $errors['sourceUrl'] = 'Source link is required — every statistic must be traceable to its origin.';
        } elseif (filter_var($sourceUrl, FILTER_VALIDATE_URL) === false) {
            $errors['sourceUrl'] = 'Source link must be a valid URL.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => $title,
            'value' => $value,
            'unit' => $unit,
            'category' => $category,
            'industryRelevance' => $industryRelevance,
            'sourceName' => $sourceName,
            'sourceYear' => $sourceYear,
            'sourceUrl' => $sourceUrl,
            'isPublished' => (bool) ($input['isPublished'] ?? true),
        ];
    }
}
