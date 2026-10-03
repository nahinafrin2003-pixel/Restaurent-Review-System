<?php

namespace App\Services\Crypto\Asymmetric;

use App\Services\Crypto\BigInteger;
use App\Services\Crypto\HMAC;
use App\Services\Crypto\SHA256;
use Exception;

class ECC
{
    protected static ?array $curve = null;

    protected static function initCurve(): void
    {
        if (self::$curve !== null) {
            return;
        }

        $config = config('crypto.ecc_curve');
        self::$curve = [
            'p' => BigInteger::fromHex($config['p']),
            'a' => BigInteger::fromHex($config['a']),
            'b' => BigInteger::fromHex($config['b']),
            'gx' => BigInteger::fromHex($config['gx']),
            'gy' => BigInteger::fromHex($config['gy']),
            'n' => BigInteger::fromHex($config['n']),
        ];
    }

    public static function generateKeyPair(): array
    {
        self::initCurve();

        $d = BigInteger::randomBetween('1', BigInteger::sub(self::$curve['n'], '1'));
        $point = self::scalarMultiply(self::generatorPoint(), $d);

        return [
            'private' => BigInteger::toHex($d),
            'public' => self::pointToArray($point),
        ];
    }

    public static function generatorPoint(): array
    {
        self::initCurve();

        return ['x' => self::$curve['gx'], 'y' => self::$curve['gy']];
    }

    public static function getCurve(): array
    {
        self::initCurve();
        return self::$curve;
    }

    public static function pointToArray(array $point): array
    {
        return [
            'x' => BigInteger::toHex($point['x']),
            'y' => BigInteger::toHex($point['y']),
        ];
    }

    public static function pointFromArray(array $data): array
    {
        if (! isset($data['y']) || $data['y'] === '') {
            $x = BigInteger::fromHex($data['x']);
            $candidates = self::pointCandidatesFromX($x);
            return $candidates[0];
        }

        return [
            'x' => BigInteger::fromHex($data['x']),
            'y' => BigInteger::fromHex($data['y']),
        ];
    }

    protected static function pointCandidatesFromR(array $data): array
    {
        if (! isset($data['y']) || $data['y'] === '') {
            $x = BigInteger::fromHex($data['x']);
            return self::pointCandidatesFromX($x);
        }

        return [self::pointFromArray($data)];
    }

    protected static function pointCandidatesFromX(string $x): array
    {
        $rhs = BigInteger::mod(
            BigInteger::add(
                BigInteger::add(BigInteger::modPow($x, '3', self::$curve['p']), BigInteger::mul(self::$curve['a'], $x)),
                self::$curve['b']
            ),
            self::$curve['p']
        );

        $y = self::sqrtMod($rhs);

        if ($y === null) {
            throw new Exception('Unable to reconstruct point from x coordinate.');
        }

        $y2 = BigInteger::mod(BigInteger::sub(self::$curve['p'], $y), self::$curve['p']);

        return [
            ['x' => $x, 'y' => $y],
            ['x' => $x, 'y' => $y2],
        ];
    }

    protected static function addPoints(array $p, array $q): array
    {
        if (self::isInfinity($p)) {
            return $q;
        }

        if (self::isInfinity($q)) {
            return $p;
        }

        if (self::cmpPoints($p, $q) === 0) {
            return self::doublePoint($p);
        }

        if (self::cmpPoints($p, self::pointNegate($q)) === 0) {
            return self::infinity();
        }

        $lambda = BigInteger::mod(
            BigInteger::mul(
                BigInteger::sub($q['y'], $p['y']),
                BigInteger::modInverse(BigInteger::sub($q['x'], $p['x']), self::$curve['p'])
            ),
            self::$curve['p']
        );

        $x = BigInteger::mod(
            BigInteger::sub(BigInteger::sub(BigInteger::mul($lambda, $lambda), $p['x']), $q['x']),
            self::$curve['p']
        );

        $y = BigInteger::mod(
            BigInteger::sub(BigInteger::mul($lambda, BigInteger::sub($p['x'], $x)), $p['y']),
            self::$curve['p']
        );

        return ['x' => $x, 'y' => $y];
    }

    protected static function doublePoint(array $p): array
    {
        if (self::isInfinity($p)) {
            return self::infinity();
        }

        if (BigInteger::cmp($p['y'], '0') === 0) {
            return self::infinity();
        }

        $lambda = BigInteger::mod(
            BigInteger::mul(
                BigInteger::add(BigInteger::mul('3', BigInteger::mul($p['x'], $p['x'])), self::$curve['a']),
                BigInteger::modInverse(BigInteger::mul('2', $p['y']), self::$curve['p'])
            ),
            self::$curve['p']
        );

        $x = BigInteger::mod(BigInteger::sub(BigInteger::mul($lambda, $lambda), BigInteger::mul('2', $p['x'])), self::$curve['p']);
        $y = BigInteger::mod(BigInteger::sub(BigInteger::mul($lambda, BigInteger::sub($p['x'], $x)), $p['y']), self::$curve['p']);

        return ['x' => $x, 'y' => $y];
    }

    protected static function scalarMultiply(array $point, string $scalar): array
    {
        $result = self::infinity();
        $addend = $point;

        while (BigInteger::cmp($scalar, '0') > 0) {
            if (BigInteger::isOdd($scalar)) {
                $result = self::addPoints($result, $addend);
            }

            $addend = self::doublePoint($addend);
            $scalar = BigInteger::div($scalar, '2');
        }

        return $result;
    }

    public static function scalarMultiplyPublic(array $point, string $scalar): array
    {
        self::initCurve();

        return self::scalarMultiply($point, $scalar);
    }

    protected static function subtractPoints(array $p, array $q): array
    {
        return self::addPoints($p, self::pointNegate($q));
    }

    protected static function pointNegate(array $p): array
    {
        if (self::isInfinity($p)) {
            return self::infinity();
        }

        return ['x' => $p['x'], 'y' => BigInteger::mod(BigInteger::sub(self::$curve['p'], $p['y']), self::$curve['p'])];
    }

    protected static function cmpPoints(array $p, array $q): int
    {
        $x = BigInteger::cmp($p['x'], $q['x']);

        if ($x !== 0) {
            return $x;
        }

        return BigInteger::cmp($p['y'], $q['y']);
    }

    protected static function isInfinity(array $point): bool
    {
        return empty($point['x']) && empty($point['y']);
    }

    protected static function infinity(): array
    {
        return ['x' => '', 'y' => ''];
    }

    protected static function sqrtMod(string $value): ?string
    {
        $p = self::$curve['p'];
        $candidate = BigInteger::modPow($value, BigInteger::div(BigInteger::add($p, '1'), '4'), $p);

        if (BigInteger::mod(BigInteger::mul($candidate, $candidate), $p) === BigInteger::mod($value, $p)) {
            return $candidate;
        }

        return null;
    }

    public static function isLegacyCiphertext(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) && isset($payload['r'], $payload['cipher']);
    }

    public static function legacyDecrypt(string $ciphertext, string $privateKey): string
    {
        self::initCurve();

        $payload = json_decode(base64_decode($ciphertext), true);

        if (! is_array($payload) || ! isset($payload['r'], $payload['cipher'])) {
            throw new Exception('Invalid legacy ECC ciphertext format.');
        }

        $r = self::pointFromArray($payload['r']);
        $d = BigInteger::fromHex($privateKey);
        $shared = self::scalarMultiply($r, $d);
        $secret = self::sharedSecret($shared);
        $cipher = base64_decode($payload['cipher']);

        return self::xorCipher($cipher, $secret);
    }

    protected static function sharedSecret(array $point): string
    {
        $hashInput = BigInteger::toHex($point['x']) . BigInteger::toHex($point['y']);

        return SHA256::raw($hashInput);
    }

    protected static function xorCipher(string $data, string $key): string
    {
        $output = '';
        $keyLength = strlen($key);

        if ($keyLength === 0) {
            throw new Exception('Shared secret generation failed.');
        }

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $output .= chr(ord($data[$i]) ^ ord($key[$i % $keyLength]));
        }

        return $output;
    }

    public static function encrypt(string $plaintext, array $publicKey): string
    {
        self::initCurve();

        // For small messages, convert to BigInteger
        $m = BigInteger::fromBinary($plaintext);
        if (BigInteger::cmp($m, self::$curve['n']) >= 0) {
            throw new Exception('Message too large for ECC encryption.');
        }

        $k = BigInteger::randomBetween('1', BigInteger::sub(self::$curve['n'], '1'));
        $c1 = self::scalarMultiply(self::generatorPoint(), $k);
        $pubPoint = self::pointFromArray($publicKey);
        $kPub = self::scalarMultiply($pubPoint, $k);
        $mG = self::scalarMultiply(self::generatorPoint(), $m);
        $c2 = self::addPoints($kPub, $mG);

        return base64_encode(json_encode([
            'c1' => self::pointToArray($c1),
            'c2' => self::pointToArray($c2)
        ]));
    }

    public static function decrypt(string $ciphertext, string $privateKey): string
    {
        self::initCurve();

        $payload = json_decode(base64_decode($ciphertext), true);
        if (!is_array($payload) || !isset($payload['c1'], $payload['c2'])) {
            throw new Exception('Invalid ECC ciphertext format.');
        }

        $c1 = self::pointFromArray($payload['c1']);
        $c2 = self::pointFromArray($payload['c2']);
        $d = BigInteger::fromHex($privateKey);

        $dC1 = self::scalarMultiply($c1, $d);
        $mG = self::subtractPoints($c2, $dC1);

        // To recover m, we need to solve m such that m*G = mG
        // Since m < n, we can use baby-step giant-step for discrete log
        // But for simplicity, since n is large, we'll implement a basic version
        // Note: This is computationally expensive for large n, but works for small messages

        $m = '0';
        $current = self::infinity();
        $maxSteps = 1000; // Limit for small m
        for ($i = 0; $i < $maxSteps; $i++) {
            if (self::pointsEqual($current, $mG)) {
                break;
            }
            $current = self::addPoints($current, self::generatorPoint());
            $m = BigInteger::add($m, '1');
        }

        if (!self::pointsEqual($current, $mG)) {
            throw new Exception('Failed to decrypt: message too large or invalid.');
        }

        return BigInteger::toBinary($m);
    }

    protected static function pointsEqual(array $p, array $q): bool
    {
        if (self::isInfinity($p) && self::isInfinity($q)) {
            return true;
        }
        if (self::isInfinity($p) || self::isInfinity($q)) {
            return false;
        }
        return BigInteger::cmp($p['x'], $q['x']) === 0 && BigInteger::cmp($p['y'], $q['y']) === 0;
    }

    public static function sign(string $message, string $privateKey): array
    {
        self::initCurve();

        $hash = SHA256::raw($message);
        $e = BigInteger::fromBinary($hash);
        $e = BigInteger::mod($e, self::$curve['n']);

        $d = BigInteger::fromHex($privateKey);

        while (true) {
            $k = BigInteger::randomBetween('1', BigInteger::sub(self::$curve['n'], '1'));
            $rPoint = self::scalarMultiply(self::generatorPoint(), $k);
            $r = BigInteger::mod($rPoint['x'], self::$curve['n']);

            if (BigInteger::cmp($r, '0') === 0) {
                continue;
            }

            $kInv = BigInteger::modInverse($k, self::$curve['n']);
            $s = BigInteger::mod(
                BigInteger::mul($kInv, BigInteger::add($e, BigInteger::mul($d, $r))),
                self::$curve['n']
            );

            if (BigInteger::cmp($s, '0') !== 0) {
                return [
                    'r' => BigInteger::toHex($r),
                    's' => BigInteger::toHex($s),
                ];
            }
        }
    }

    public static function verify(string $message, array $signature, array $publicKey): bool
    {
        self::initCurve();

        $hash = SHA256::raw($message);
        $e = BigInteger::fromBinary($hash);
        $e = BigInteger::mod($e, self::$curve['n']);

        $r = BigInteger::fromHex($signature['r']);
        $s = BigInteger::fromHex($signature['s']);

        if (BigInteger::cmp($r, '1') < 0 || BigInteger::cmp($r, BigInteger::sub(self::$curve['n'], '1')) > 0) {
            return false;
        }

        if (BigInteger::cmp($s, '1') < 0 || BigInteger::cmp($s, BigInteger::sub(self::$curve['n'], '1')) > 0) {
            return false;
        }

        $sInv = BigInteger::modInverse($s, self::$curve['n']);
        $u1 = BigInteger::mod(BigInteger::mul($e, $sInv), self::$curve['n']);
        $u2 = BigInteger::mod(BigInteger::mul($r, $sInv), self::$curve['n']);

        $point1 = self::scalarMultiply(self::generatorPoint(), $u1);
        $point2 = self::scalarMultiply(self::pointFromArray($publicKey), $u2);
        $point = self::addPoints($point1, $point2);

        if (self::isInfinity($point)) {
            return false;
        }

        $rPrime = BigInteger::mod($point['x'], self::$curve['n']);

        return BigInteger::cmp($r, $rPrime) === 0;
    }

    /*
     * ---------------------------------------------------------------
     * EC-ElGamal hybrid encryption (ECIES-style) for arbitrary-length data
     * ---------------------------------------------------------------
     * 1. pick ephemeral k, publish R = kG            (ElGamal "c1")
     * 2. shared point S = kQ; derive keys from SHA-256(S.x || S.y)
     * 3. c2 = m XOR keystream(SHA-256 counter mode)  (ElGamal "c2")
     * 4. tag = HMAC-SHA256(macKey, R || c2)          (authenticity)
     */
    public static function seal(string $plaintext, array $publicKey): string
    {
        self::initCurve();

        $k = BigInteger::randomBetween('1', BigInteger::sub(self::$curve['n'], '1'));
        $r = self::scalarMultiply(self::generatorPoint(), $k);
        $s = self::scalarMultiply(self::pointFromArray($publicKey), $k);
        [$encKey, $macKey] = self::deriveKeys($s);

        $cipher = $plaintext ^ self::keystream($encKey, strlen($plaintext));
        $rArr = self::pointToArray($r);
        $tag = HMAC::sign($rArr['x'] . ':' . $rArr['y'] . ':' . $cipher, $macKey);

        return base64_encode(json_encode([
            'format' => 'ecies-v1',
            'r' => $rArr,
            'c' => base64_encode($cipher),
            't' => $tag,
        ]));
    }

    public static function open(string $ciphertext, string $privateKey): string
    {
        self::initCurve();

        $payload = json_decode((string) base64_decode($ciphertext, true), true);
        if (! is_array($payload) || ($payload['format'] ?? '') !== 'ecies-v1') {
            throw new Exception('Invalid ECIES ciphertext.');
        }

        $r = self::pointFromArray($payload['r']);
        $s = self::scalarMultiply($r, BigInteger::fromHex($privateKey));
        [$encKey, $macKey] = self::deriveKeys($s);
        $cipher = base64_decode($payload['c']);

        if (! HMAC::verify($payload['r']['x'] . ':' . $payload['r']['y'] . ':' . $cipher, $macKey, $payload['t'])) {
            throw new Exception('ECIES authentication tag mismatch (wrong key or tampered data).');
        }

        return $cipher ^ self::keystream($encKey, strlen($cipher));
    }

    public static function isSealed(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);

        return is_array($payload) && ($payload['format'] ?? '') === 'ecies-v1';
    }

    protected static function deriveKeys(array $sharedPoint): array
    {
        $seed = SHA256::raw('ecies|' . BigInteger::toHex($sharedPoint['x']) . '|' . BigInteger::toHex($sharedPoint['y']));

        return [SHA256::raw($seed . "\x01enc"), SHA256::raw($seed . "\x02mac")];
    }

    protected static function keystream(string $key, int $length): string
    {
        $out = '';
        $counter = 0;
        while (strlen($out) < $length) {
            $out .= SHA256::raw($key . pack('N', $counter++));
        }

        return substr($out, 0, $length);
    }
}
