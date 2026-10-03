<?php

namespace App\Services\Crypto;

class PasswordHasher
{
    public static function hash(string $password, ?string $salt = null): array
    {
        $salt ??= bin2hex(random_bytes(16));
        $hash = self::derive($password, $salt);

        return ['salt' => $salt, 'hash' => $hash];
    }

    public static function verify(string $password, string $salt, string $hash): bool
    {
        return HMAC::equals($hash, self::derive($password, $salt));
    }

    protected static function derive(string $password, string $salt): string
    {
        $result = SHA256::raw($salt . $password);

        for ($i = 0; $i < 12000; $i++) {
            $result = SHA256::raw($result . $salt . $password);
        }

        return bin2hex($result);
    }
}
