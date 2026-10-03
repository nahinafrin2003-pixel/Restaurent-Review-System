<?php

namespace App\Support;

/** Complaint categories, their keyword dictionaries and severity weights. */
class ComplaintCategory
{
    public const LABELS = [
        'food_poisoning' => 'Food Poisoning',
        'hygiene' => 'Poor Hygiene',
        'overcharging' => 'Overcharging',
        'fake_certification' => 'Fake Certification / License',
        'expired_food' => 'Expired / Stale Food',
        'adulteration' => 'Adulteration / Harmful Additives',
        'misbehavior' => 'Staff Misbehaviour',
        'other' => 'Other',
    ];

    /** Severity weight used by the trust-score penalty (0-1). */
    public const SEVERITY = [
        'food_poisoning' => 1.0,
        'adulteration' => 0.9,
        'fake_certification' => 0.8,
        'expired_food' => 0.7,
        'hygiene' => 0.6,
        'overcharging' => 0.4,
        'misbehavior' => 0.3,
        'other' => 0.3,
    ];

    /** Keyword => weight. Multi-word phrases count more. Includes common Banglish terms. */
    public const KEYWORDS = [
        'food_poisoning' => [
            'food poisoning' => 3, 'poisoning' => 2, 'vomit' => 2, 'vomiting' => 2, 'diarrhea' => 2, 'diarrhoea' => 2,
            'stomach ache' => 2, 'stomach pain' => 2, 'hospital' => 2, 'nausea' => 2, 'sick' => 1, 'ill' => 1,
            'fever' => 1, 'cramps' => 1, 'doctor' => 1, 'pet kharap' => 3, 'loose motion' => 2,
        ],
        'hygiene' => [
            'dirty' => 2, 'unhygienic' => 3, 'hygiene' => 2, 'cockroach' => 3, 'rat' => 2, 'rats' => 2, 'insect' => 2,
            'fly' => 1, 'flies' => 2, 'hair in' => 2, 'filthy' => 2, 'smell' => 1, 'smelly' => 1, 'unclean' => 2,
            'kitchen' => 1, 'mosquito' => 1, 'worm' => 2, 'gloves' => 1, 'sanitation' => 2, 'toilet' => 1,
        ],
        'overcharging' => [
            'overcharge' => 3, 'overcharged' => 3, 'overcharging' => 3, 'extra charge' => 2, 'hidden charge' => 3,
            'price' => 1, 'bill' => 1, 'receipt' => 1, 'mrp' => 2, 'expensive' => 1, 'vat' => 1, 'service charge' => 2,
            'more than menu' => 3, 'wrong bill' => 3, 'refund' => 1, 'taka' => 1, 'tk' => 1,
        ],
        'fake_certification' => [
            'fake certificate' => 3, 'fake license' => 3, 'fake licence' => 3, 'certificate' => 2, 'certification' => 2,
            'license' => 2, 'licence' => 2, 'bsti' => 3, 'halal' => 2, 'trade license' => 3, 'forged' => 3, 'fake logo' => 3,
            'expired license' => 3, 'unregistered' => 2, 'permit' => 1,
        ],
        'expired_food' => [
            'expired' => 3, 'expiry' => 3, 'expiration' => 3, 'stale' => 3, 'rotten' => 3, 'spoiled' => 3, 'spoilt' => 3,
            'old food' => 2, 'mold' => 3, 'mould' => 3, 'fungus' => 3, 'sour' => 1, 'bashi' => 3, 'date over' => 3,
        ],
        'adulteration' => [
            'adulterated' => 3, 'adulteration' => 3, 'formalin' => 3, 'chemical' => 2, 'artificial colour' => 3,
            'artificial color' => 3, 'textile dye' => 3, 'dye' => 2, 'carbide' => 3, 'mixed with' => 2, 'harmful' => 2,
            'preservative' => 2, 'melamine' => 3, 'urea' => 3, 'bhejal' => 3,
        ],
        'misbehavior' => [
            'rude' => 3, 'misbehave' => 3, 'misbehaved' => 3, 'misbehavior' => 3, 'misbehaviour' => 3, 'abusive' => 3,
            'shouted' => 2, 'insulted' => 3, 'threatened' => 3, 'harassed' => 3, 'staff' => 1, 'manager' => 1, 'waiter' => 1,
        ],
    ];

    public static function label(string $category): string
    {
        return self::LABELS[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }

    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }
}
