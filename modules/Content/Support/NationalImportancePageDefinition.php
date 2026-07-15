<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

/**
 * The two admin-editable prose sections on the National Importance dashboard
 * ("National problem summary" and "Future research"). Reuses the same
 * generic Content module (managed pages, drafts, publish workflow, revision
 * history) already used for the Methodology page, rather than a bespoke
 * text editor \u2014 admins edit this content at /content/national-importance-context.
 */
final class NationalImportancePageDefinition
{
    public const PAGE_KEY = 'national-importance-context';

    public const SECTION_PROBLEM_SUMMARY = 'national_problem_summary';
    public const SECTION_FUTURE_RESEARCH = 'future_research_agenda';

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return [
            self::SECTION_PROBLEM_SUMMARY,
            self::SECTION_FUTURE_RESEARCH,
        ];
    }

    /** @return array<string, mixed> */
    public static function seedSnapshot(): array
    {
        return [
            'sections' => [
                self::section(
                    self::SECTION_PROBLEM_SUMMARY,
                    'Why Workforce Health Matters',
                    'Musculoskeletal strain remains one of the most common and costly sources of workplace injury across warehouse work, health care support work, manual material handling, long-term care, food service, manufacturing, delivery work, and other repetitive or high-strain jobs. WorkEddy was built to give employers and workers a practical, evidence-based way to identify high-risk tasks early, act on them, and track whether corrective action is actually reducing risk over time.',
                ),
                self::section(
                    self::SECTION_FUTURE_RESEARCH,
                    'Future Research Directions',
                    'Planned areas of continued study include longitudinal tracking of injury outcomes beyond self-reported discomfort, sector-specific benchmarking across warehouse, health care, and food service settings, and independent validation of platform risk-reduction estimates against workers\u2019 compensation claims data.',
                ),
            ],
            'references' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function section(string $key, string $heading, string $body): array
    {
        return [
            'sectionKey' => $key,
            'heading' => $heading,
            'displayOrder' => array_search($key, self::sectionKeys(), true) + 1,
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'text' => $body,
                ],
            ],
        ];
    }
}
