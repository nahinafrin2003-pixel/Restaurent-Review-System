<?php

namespace App\Services\Crypto;

/**
 * HMAC-SHA256 (RFC 2104) on top of the from-scratch SHA256 class.
 */
class HMAC
{
    private const BLOCK = 64;

    public static function raw(string $message, string $key): string
    {
        if (strlen($key) > self::BLOCK) {
            $key = SHA256::raw($key);
        }
        $key = str_pad($key, self::BLOCK, "\0");

        $inner = SHA256::raw(($key ^ str_repeat("\x36", self::BLOCK)) . $message);

        return SHA256::raw(($key ^ str_repeat("\x5c", self::BLOCK)) . $inner);
    }

    public static function sign(string $message, string $key): string
    {
        return bin2hex(self::raw($message, $key));
    }

    public static function verify(string $message, string $key, string $mac): bool
    {
        return self::equals(self::sign($message, $key), strtolower($mac));
    }

    /** Constant-time string comparison (hand-rolled, no hash_equals). */
    public static function equals(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $diff = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }

        return $diff === 0;
    }
}
