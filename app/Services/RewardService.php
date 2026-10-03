<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\RewardEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\ComplaintStatus;

/** Points and badges for customers whose complaints are verified. */
class RewardService
{
    public const BADGES = [
        'food_safety_contributor' => ['label' => 'Food Safety Contributor', 'icon' => '🥗', 'rule' => '1 verified complaint'],
        'trusted_reporter' => ['label' => 'Trusted Reporter', 'icon' => '🛡️', 'rule' => '3 verified complaints'],
        'community_guardian' => ['label' => 'Community Guardian', 'icon' => '🏅', 'rule' => '10 verified complaints or 500 points'],
    ];

    public const POINTS_VERIFIED = 50;
    public const POINTS_RESOLVED = 20;
    public const POINTS_EVIDENCE_BONUS = 10;

    public static function onStatusChange(Complaint $complaint, string $to): void
    {
        $user = $complaint->user;
        if (! $user) {
            return;
        }

        if ($to === ComplaintStatus::VERIFIED) {
            $bonus = $complaint->evidence()->count() > 0 ? self::POINTS_EVIDENCE_BONUS : 0;
            self::award($user, self::POINTS_VERIFIED + $bonus, 'Complaint ' . $complaint->reference . ' verified', $complaint->id);
        } elseif ($to === ComplaintStatus::RESOLVED) {
            self::award($user, self::POINTS_RESOLVED, 'Complaint ' . $complaint->reference . ' resolved', $complaint->id);
        }

        self::evaluateBadges($user);
    }

    public static function award(User $user, int $points, string $reason, ?int $complaintId = null): void
    {
        $already = RewardEvent::where('user_id', $user->user_id)->where('reason', $reason)->exists();
        if ($already) {
            return;   // idempotent: never double-award for the same event
        }

        RewardEvent::create(['user_id' => $user->user_id, 'points' => $points, 'reason' => $reason, 'complaint_id' => $complaintId, 'created_at' => now()]);
        $user->increment('points', $points);
    }

    public static function evaluateBadges(User $user): array
    {
        $verified = Complaint::where('user_id', $user->user_id)->whereNotNull('verified_at')->where('status', '!=', 'rejected')->count();
        $points = (int) $user->fresh()->points;

        $earned = [];
        if ($verified >= 1) {
            $earned[] = 'food_safety_contributor';
        }
        if ($verified >= 3) {
            $earned[] = 'trusted_reporter';
        }
        if ($verified >= 10 || $points >= 500) {
            $earned[] = 'community_guardian';
        }

        foreach ($earned as $badge) {
            UserBadge::firstOrCreate(['user_id' => $user->user_id, 'badge' => $badge], ['awarded_at' => now()]);
        }

        return $earned;
    }
}
