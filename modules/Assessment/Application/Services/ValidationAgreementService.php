<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application\Services;

use WorkEddy\Modules\Assessment\Domain\ValidationReview;

final class ValidationAgreementService
{
    /**
     * @param list<ValidationReview> $reviews
     * @return array<string, mixed>
     */
    public function summarize(array $reviews): array
    {
        $grouped = [];
        foreach ($reviews as $review) {
            $grouped[$review->assessmentUuid][] = $review;
        }

        $totalPairs = 0;
        $riskMatches = 0;
        $scoreMatches = 0;
        $bodyRegionMatches = 0;
        $riskFactorMatches = 0;

        foreach ($grouped as $assessmentReviews) {
            $count = count($assessmentReviews);
            if ($count < 2) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $left = $assessmentReviews[$i];
                    $right = $assessmentReviews[$j];
                    $totalPairs++;

                    if (strcasecmp($left->riskLevel, $right->riskLevel) === 0) {
                        $riskMatches++;
                    }
                    if ($this->canonicalJson($left->score) === $this->canonicalJson($right->score)) {
                        $scoreMatches++;
                    }
                    if ($this->canonicalList($left->bodyRegions) === $this->canonicalList($right->bodyRegions)) {
                        $bodyRegionMatches++;
                    }
                    if ($this->canonicalList($left->riskFactors) === $this->canonicalList($right->riskFactors)) {
                        $riskFactorMatches++;
                    }
                }
            }
        }

        return [
            'assessmentsReviewed' => count($grouped),
            'pairCount' => $totalPairs,
            'overallAgreementRate' => $this->percent($totalPairs, min($riskMatches, $scoreMatches, $bodyRegionMatches)),
            'riskLevelAgreementRate' => $this->percent($totalPairs, $riskMatches),
            'scoreAgreementRate' => $this->percent($totalPairs, $scoreMatches),
            'bodyRegionAgreementRate' => $this->percent($totalPairs, $bodyRegionMatches),
            'riskFactorAgreementRate' => $this->percent($totalPairs, $riskFactorMatches),
        ];
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        ksort($value);

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $value */
    private function canonicalList(array $value): string
    {
        $copy = array_values(array_map('strval', $value));
        sort($copy);

        return json_encode($copy, JSON_THROW_ON_ERROR);
    }

    private function percent(int $total, int $matches): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($matches / $total) * 100, 2);
    }
}
