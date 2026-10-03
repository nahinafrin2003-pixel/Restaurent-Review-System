<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Restaurant;
use App\Models\Review;
use App\Support\ComplaintCategory;

/**
 * Restaurant trust score (0-5 stars).
 *
 *   base    = average approved review score (1-10) / 2, or 5.0 with no reviews
 *   penalty = Σ severity(category) × recency × 0.8   over verified complaints
 *             recency = 1.0 (≤90 days), 0.6 (≤365 days), 0.3 (older)
 *   trust   = clamp(base − penalty, 0, 5)
 */
class TrustScoreService
{
    public static function recalculate(Restaurant $restaurant): float
    {
        $avgReview = Review::where('restaurant_id', $restaurant->id)->where('status', 'approved')->avg('review_score');
        $base = $avgReview ? min(5, $avgReview / 2) : 5.0;

        $verified = Complaint::where('restaurant_id', $restaurant->id)->whereNotNull('verified_at')->where('status', '!=', 'rejected')->get(['category', 'verified_at']);

        $penalty = 0.0;
        foreach ($verified as $complaint) {
            $age = $complaint->verified_at->diffInDays(now());
            $recency = $age <= 90 ? 1.0 : ($age <= 365 ? 0.6 : 0.3);
            $penalty += (ComplaintCategory::SEVERITY[$complaint->category] ?? 0.3) * $recency * 0.8;
        }

        $score = round(max(0, min(5, $base - $penalty)), 2);

        $restaurant->update(['trust_score' => $score, 'verified_complaints' => $verified->count()]);

        return $score;
    }
}
