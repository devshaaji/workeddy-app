<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

final class MethodologyPageDefinition
{
    public const PAGE_KEY = 'methodology-and-limitations';

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return [
            'what_workeddy_measures',
            'scoring_systems',
            'how_ai_scoring_works',
            'why_reviewer_validation',
            'what_workeddy_does_not_claim',
            'how_privacy_is_protected',
            'how_data_supports_prevention',
            'how_pilot_evidence_is_collected',
        ];
    }

    /** @return array<string, mixed> */
    public static function seedSnapshot(): array
    {
        return [
            'sections' => [
                self::section('what_workeddy_measures', 'What WorkEddy measures', 'Task level ergonomic risk factors including posture, force, repetition, reach, bending, twisting, manual handling, and reported discomfort.'),
                self::section('scoring_systems', 'Scoring systems that inform the platform', 'REBA informed whole body risk, RULA informed upper limb risk, NIOSH lifting principles, manual material handling risk factors, worker discomfort feedback, and reviewer validation.'),
                self::section('how_ai_scoring_works', 'How AI assisted scoring works', 'AI may estimate posture or movement risk. AI outputs are not final by default. AI scores require human review. Confidence levels and model version should be stored.'),
                self::section('why_reviewer_validation', 'Why reviewer validation is included', 'Reviewer validation improves credibility, prevents unsupported AI conclusions, and documents professional judgment.'),
                self::section('what_workeddy_does_not_claim', 'What WorkEddy does not claim', 'No guarantee of injury prevention, no medical diagnosis, no legal compliance certification, and no replacement for professional ergonomic judgment.'),
                self::section('how_privacy_is_protected', 'How privacy is protected', 'Consent capture, face blurring, role based access, secure storage, audit logs, de identified exports, no disciplinary use statement, and no productivity monitoring statement.'),
                self::section('how_data_supports_prevention', 'How data supports prevention planning', 'Task scores identify high risk activities; body region heat maps show strain patterns; corrective actions create accountability; follow up assessments document improvement.'),
                self::section('how_pilot_evidence_is_collected', 'How pilot evidence will be collected', 'Baseline assessments, corrective actions, follow up assessments, before and after comparisons, worker feedback, reviewer agreement, risk reduction estimates, and de identified exports.'),
            ],
            'references' => [
                [
                    'sectionKey' => 'scoring_systems',
                    'title' => 'REBA and RULA evidence base',
                    'author' => 'WorkEddy specification',
                    'year' => '2025',
                    'url' => null,
                    'citation' => 'Developer specification baseline for methodology wording.',
                    'displayOrder' => 1,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function seedSnapshotWithOverride(string $sectionKey, string $paragraph): array
    {
        $snapshot = self::seedSnapshot();
        foreach ($snapshot['sections'] as &$section) {
            if (($section['sectionKey'] ?? '') === $sectionKey) {
                $section['blocks'][0]['text'] = $paragraph;
            }
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private static function section(string $key, string $heading, string $body): array
    {
        return [
            'sectionKey' => $key,
            'heading' => $heading,
            'displayOrder' => count(self::sectionKeys()) ? (array_search($key, self::sectionKeys(), true) + 1) : 1,
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'text' => $body,
                ],
            ],
        ];
    }
}
