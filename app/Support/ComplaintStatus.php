<?php

namespace App\Support;

/** Complaint lifecycle: allowed states and transitions. */
class ComplaintStatus
{
    public const SUBMITTED = 'submitted';
    public const UNDER_VERIFICATION = 'under_verification';
    public const EVIDENCE_REQUESTED = 'evidence_requested';
    public const VERIFIED = 'verified';
    public const SENT_TO_INVESTIGATION = 'sent_to_investigation';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';
    public const REJECTED = 'rejected';

    public const LABELS = [
        self::SUBMITTED => 'Submitted',
        self::UNDER_VERIFICATION => 'Under Verification',
        self::EVIDENCE_REQUESTED => 'Evidence Requested',
        self::VERIFIED => 'Verified',
        self::SENT_TO_INVESTIGATION => 'Sent to Investigation',
        self::RESOLVED => 'Resolved',
        self::CLOSED => 'Closed',
        self::REJECTED => 'Rejected',
    ];

    /** Which states an officer may move a complaint to from each state. */
    public const TRANSITIONS = [
        self::SUBMITTED => [self::UNDER_VERIFICATION, self::REJECTED],
        self::UNDER_VERIFICATION => [self::EVIDENCE_REQUESTED, self::VERIFIED, self::REJECTED],
        self::EVIDENCE_REQUESTED => [self::UNDER_VERIFICATION, self::VERIFIED, self::REJECTED, self::CLOSED],
        self::VERIFIED => [self::SENT_TO_INVESTIGATION, self::RESOLVED, self::CLOSED],
        self::SENT_TO_INVESTIGATION => [self::RESOLVED, self::CLOSED],
        self::RESOLVED => [self::CLOSED],
        self::CLOSED => [],
        self::REJECTED => [],
    ];

    /** States in which the complaint counts as "verified" for trust score / rewards / reports. */
    public const VERIFIED_STATES = [self::VERIFIED, self::SENT_TO_INVESTIGATION, self::RESOLVED];

    /** Terminal / finished states. */
    public const FINAL_STATES = [self::RESOLVED, self::CLOSED, self::REJECTED];

    /** Open states shown in the officer queue. */
    public const OPEN_STATES = [self::SUBMITTED, self::UNDER_VERIFICATION, self::EVIDENCE_REQUESTED, self::VERIFIED, self::SENT_TO_INVESTIGATION];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Can the customer still attach evidence? */
    public static function acceptsEvidence(string $status): bool
    {
        return in_array($status, [self::SUBMITTED, self::UNDER_VERIFICATION, self::EVIDENCE_REQUESTED], true);
    }
}
