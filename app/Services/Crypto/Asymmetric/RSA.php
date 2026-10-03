<?php

namespace App\Services\Crypto\Asymmetric;

use App\Services\Crypto\BigInteger;
use Exception;

class RSA
{
    public static function generateKeys(int $bits = 2048): array
    {
        $half = (int) floor($bits / 2);
        $p = BigInteger::randomPrime($half);
        $q = BigInteger::randomPrime($half);

        while (BigInteger::cmp($p, $q) === 0) {
            $q = BigInteger::randomPrime($half);
        }

        $n = BigInteger::mul($p, $q);
        $phi = BigInteger::mul(BigInteger::sub($p, '1'), BigInteger::sub($q, '1'));
        $e = '65537';
        $d = BigInteger::modInverse($e, $phi);

        return [
            'n' => BigInteger::toHex($n),
            'e' => BigInteger::toHex($e),
            'd' => BigInteger::toHex($d),
            'p' => BigInteger::toHex($p),
            'q' => BigInteger::toHex($q),
        ];
    }

    public static function keyByteSize(array $key): int
    {
        if (! isset($key['n'])) {
            throw new Exception('RSA key is missing modulus.');
        }

        $hex = preg_replace('/^0x/i', '', $key['n']);
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);

        return (int) ceil(strlen($hex) / 2);
    }

    public static function isKeyLargeEnoughForOaep(array $key, int $hLen = 32): bool
    {
        return self::keyByteSize($key) >= (2 * $hLen + 2);
    }

    public static function encrypt(string $plaintext, array $publicKey): string
    {
        $n = BigInteger::fromHex($publicKey['n']);
        $e = BigInteger::fromHex($publicKey['e']);
        $k = self::keyByteSize($publicKey);
        $maxChunkSize = $k - 2 * 32 - 2;

        if ($maxChunkSize <= 0) {
            throw new Exception('RSA key size is too small for OAEP padding.');
        }

        $blocks = [];
        $chunks = str_split($plaintext, $maxChunkSize);

        foreach ($chunks as $chunk) {
            $encoded = self::oaepEncode($chunk, $k);
            $value = BigInteger::fromBinary($encoded);
            $cipher = BigInteger::modPow($value, $e, $n);
            $blocks[] = BigInteger::toHex($cipher);
        }

        return base64_encode(json_encode(['format' => 'rsa-oaep', 'blocks' => $blocks]));
    }

    public static function decrypt(string $ciphertext, array $privateKey): string
    {
        $payload = json_decode(base64_decode($ciphertext), true);

        if (! is_array($payload) || ! isset($payload['blocks'])) {
            throw new Exception('Invalid RSA ciphertext format.');
        }

        $format = $payload['format'] ?? 'rsa-plain';
        $n = BigInteger::fromHex($privateKey['n']);
        $d = BigInteger::fromHex($privateKey['d']);
        $k = self::keyByteSize($privateKey);   // must match the size used by encrypt()
        $plaintext = '';

        foreach ($payload['blocks'] as $blockHex) {
            $cipher = BigInteger::fromHex($blockHex);
            $value = BigInteger::modPow($cipher, $d, $n);
            $encoded = hex2bin(str_pad(BigInteger::toHex($value), $k * 2, '0', STR_PAD_LEFT));

            if ($format === 'rsa-oaep') {
                $plaintext .= self::oaepDecode($encoded, $k);
            } else {
                $plaintext .= BigInteger::toBinary($value);
            }
        }

        return $plaintext;
    }

    protected static function sha256(string $data): string
    {
        return \App\Services\Crypto\SHA256::raw($data);
    }

    protected static function mgf1(string $seed, int $length): string
    {
        $output = '';
        $counter = 0;

        while (strlen($output) < $length) {
            $output .= self::sha256($seed . pack('N', $counter));
            $counter++;
        }

        return substr($output, 0, $length);
    }

    protected static function oaepEncode(string $message, int $k, string $label = ''): string
    {
        $hLen = 32;
        if (strlen($message) > $k - 2 * $hLen - 2) {
            throw new Exception('OAEP: message too long.');
        }

        $lHash = self::sha256($label);
        $ps = str_repeat(chr(0), $k - strlen($message) - 2 * $hLen - 2);
        $db = $lHash . $ps . chr(1) . $message;
        $seed = random_bytes($hLen);
        $dbMask = self::mgf1($seed, $k - $hLen - 1);
        $maskedDb = $db ^ $dbMask;
        $seedMask = self::mgf1($maskedDb, $hLen);
        $maskedSeed = $seed ^ $seedMask;

        return chr(0) . $maskedSeed . $maskedDb;
    }

    protected static function oaepDecode(string $encoded, int $k, string $label = ''): string
    {
        $hLen = 32;

        if (strlen($encoded) !== $k || $k < 2 * $hLen + 2) {
            throw new Exception('OAEP: invalid encoded message length.');
        }

        $y = ord($encoded[0]);
        $maskedSeed = substr($encoded, 1, $hLen);
        $maskedDb = substr($encoded, 1 + $hLen);
        $seedMask = self::mgf1($maskedDb, $hLen);
        $seed = $maskedSeed ^ $seedMask;
        $dbMask = self::mgf1($seed, $k - $hLen - 1);
        $db = $maskedDb ^ $dbMask;
        $lHash = self::sha256($label);

        if (substr($db, 0, $hLen) !== $lHash || $y !== 0) {
            throw new Exception('OAEP: decoding error.');
        }

        $index = strpos($db, chr(1), $hLen);
        if ($index === false) {
            throw new Exception('OAEP: decoding error.');
        }

        return substr($db, $index + 1);
    }

    public static function isLegacyCiphertext(string $ciphertext): bool
    {
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['blocks']);
    }
}
