<?php

namespace App\Support;

/** Administrative areas used across reviews, restaurants and complaints. */
class Districts
{
    /** name => [lat, lng] (approximate city centre, used as a map fallback). */
    public const CENTRES = [
        'Dhaka' => [23.8103, 90.4125],
        'Chittagong' => [22.3569, 91.7832],
        'Khulna' => [22.8456, 89.5403],
        'Rajshahi' => [24.3745, 88.6042],
        'Sylhet' => [24.8949, 91.8687],
        'Barisal' => [22.7010, 90.3535],
        'Rangpur' => [25.7439, 89.2752],
        'Mymensingh' => [24.7471, 90.4203],
    ];

    public static function all(): array
    {
        return array_keys(self::CENTRES);
    }

    public static function centre(string $district): array
    {
        return self::CENTRES[$district] ?? self::CENTRES['Dhaka'];
    }
}
