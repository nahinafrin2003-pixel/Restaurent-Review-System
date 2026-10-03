<?php

namespace App\Services\Crypto\Mac;

/** Backwards-compatible alias of the custom HMAC implementation. */
class Hmac
{
    public static function sign(string $message, string $key): string
    {
        return \App\Services\Crypto\HMAC::sign($message, $key);
    }

    public static function verify(string $message, string $key, string $mac): bool
    {
        return \App\Services\Crypto\HMAC::verify($message, $key, $mac);
    }
}
