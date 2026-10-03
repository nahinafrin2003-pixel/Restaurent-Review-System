<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintDuplicate;

/**
 * Heuristic duplicate detection.
 *
 * Candidates: complaints about the same restaurant in the last 120 days.
 * score = 0.60 * cosine(text TF vectors)
 *       + 0.25 * (same category)
 *       + 0.15 * date proximity (1 at same day, 0 at >= 30 days)
 * Anything >= 0.45 is surfaced to officers for linking.
 */
class DuplicateDetector
{
    public const THRESHOLD = 0.45;

    private const STOPWORDS = ['the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'at', 'for', 'is', 'was', 'were', 'it',
        'i', 'we', 'my', 'our', 'me', 'they', 'their', 'this', 'that', 'with', 'from', 'be', 'been', 'have', 'had', 'has',
        'there', 'but', 'not', 'very', 'so', 'as', 'by', 'are', 'after', 'when', 'restaurant', 'food', 'they', 'he', 'she'];

    public static function detect(Complaint $complaint): array
    {
        $candidates = Complaint::where('restaurant_id', $complaint->restaurant_id)
            ->where('id', '!=', $complaint->id)
            ->where('created_at', '>=', now()->subDays(120))
            ->where('status', '!=', 'rejected')
            ->latest()
            ->limit(40)
            ->get();

        $base = self::vector($complaint->title . ' ' . $complaint->description);
        $found = [];

        foreach ($candidates as $other) {
            $text = self::cosine($base, self::vector($other->title . ' ' . $other->description));
            $category = $other->category === $complaint->category ? 1.0 : 0.0;
            $days = ($complaint->incident_date && $other->incident_date)
                ? abs($complaint->incident_date->diffInDays($other->incident_date))
                : abs($complaint->created_at->diffInDays($other->created_at));
            $proximity = max(0, 1 - $days / 30);

            $score = round(0.60 * $text + 0.25 * $category + 0.15 * $proximity, 2);

            if ($score >= self::THRESHOLD) {
                ComplaintDuplicate::updateOrCreate(
                    ['complaint_id' => $complaint->id, 'candidate_id' => $other->id],
                    ['score' => $score]
                );
                $found[] = ['id' => $other->id, 'score' => $score];
            }
        }

        return $found;
    }

    public static function vector(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $vector = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            // crude stemming so "vomited"/"vomiting" match
            $word = preg_replace('/(ing|ed|es|s)$/', '', $word);
            $vector[$word] = ($vector[$word] ?? 0) + 1;
        }

        return $vector;
    }

    public static function cosine(array $a, array $b): float
    {
        if (! $a || ! $b) {
            return 0.0;
        }
        $dot = 0;
        foreach ($a as $term => $weight) {
            $dot += $weight * ($b[$term] ?? 0);
        }
        $normA = sqrt(array_sum(array_map(fn ($v) => $v * $v, $a)));
        $normB = sqrt(array_sum(array_map(fn ($v) => $v * $v, $b)));

        return ($normA && $normB) ? $dot / ($normA * $normB) : 0.0;
    }
}
