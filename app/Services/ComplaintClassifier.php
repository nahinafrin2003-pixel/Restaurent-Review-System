<?php

namespace App\Services;

use App\Support\ComplaintCategory;

/**
 * Keyword-based automatic categorisation. Each category has a weighted
 * dictionary; the text is normalised and scored, and the best category wins.
 * Confidence = best score / total score (0-1).
 */
class ComplaintClassifier
{
    public static function classify(string $text): array
    {
        $normalised = ' ' . preg_replace('/[^a-z0-9\s]+/', ' ', mb_strtolower($text)) . ' ';
        $normalised = preg_replace('/\s+/', ' ', $normalised);

        $scores = [];
        $hits = [];
        foreach (ComplaintCategory::KEYWORDS as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword => $weight) {
                $count = substr_count($normalised, ' ' . $keyword . ' ');
                if ($count > 0) {
                    $score += $weight * min($count, 3);
                    $hits[$category][] = $keyword;
                }
            }
            $scores[$category] = $score;
        }

        arsort($scores);
        $best = array_key_first($scores);
        $total = array_sum($scores);

        if ($total === 0 || $scores[$best] < 2) {
            return ['category' => 'other', 'confidence' => 0.0, 'scores' => $scores, 'matched' => []];
        }

        return [
            'category' => $best,
            'confidence' => round($scores[$best] / $total, 2),
            'scores' => $scores,
            'matched' => $hits[$best] ?? [],
        ];
    }
}
