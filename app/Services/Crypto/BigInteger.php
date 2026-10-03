<?php

namespace App\Services\Crypto;

use Exception;

class BigInteger
{
    public static function normalize(string $value): string
    {
        if ($value === '') {
            return '0';
        }

        $negative = false;

        if ($value[0] === '+') {
            $value = substr($value, 1);
        }

        if ($value[0] === '-') {
            $negative = true;
            $value = substr($value, 1);
        }

        $value = ltrim($value, '0');

        if ($value === '') {
            return '0';
        }

        return $negative ? '-' . $value : $value;
    }

    protected static function hasGmp(): bool
    {
        return extension_loaded('gmp');
    }

    protected static function hasBcmath(): bool
    {
        return extension_loaded('bcmath');
    }

    protected static function pickEngine(): string
    {
        if (self::hasGmp()) {
            return 'gmp';
        }

        if (self::hasBcmath()) {
            return 'bcmath';
        }

        throw new \Exception('BigInteger requires either GMP or BCMath.');
    }

    protected static function gmpInit(string $value)
    {
        return gmp_init(self::normalize($value), 10);
    }

    protected static function gmpToString($value): string
    {
        return gmp_strval($value, 10);
    }

    public static function cmp(string $a, string $b): int
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if (self::hasGmp()) {
            return gmp_cmp(self::gmpInit($a), self::gmpInit($b));
        }

        if (self::hasBcmath()) {
            return (int) bccomp($a, $b, 0);
        }
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === $b) {
            return 0;
        }

        $aNegative = $a[0] === '-';
        $bNegative = $b[0] === '-';

        if ($aNegative && ! $bNegative) {
            return -1;
        }

        if (! $aNegative && $bNegative) {
            return 1;
        }

        if ($aNegative) {
            return -self::compareAbsolute(substr($a, 1), substr($b, 1));
        }

        return self::compareAbsolute($a, $b);
    }

    private static function compareAbsolute(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return strlen($a) > strlen($b) ? 1 : -1;
        }

        return $a === $b ? 0 : ($a > $b ? 1 : -1);
    }

    public static function add(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if (self::hasGmp()) {
            return self::gmpToString(gmp_add(self::gmpInit($a), self::gmpInit($b)));
        }

        if (self::hasBcmath()) {
            return bcadd($a, $b, 0);
        }

        $aNegative = $a[0] === '-';
        $bNegative = $b[0] === '-';

        if ($aNegative === $bNegative) {
            $result = self::addAbsolute(substr($a, $aNegative ? 1 : 0), substr($b, $bNegative ? 1 : 0));
            return $aNegative ? '-' . $result : $result;
        }

        if ($aNegative) {
            return self::sub($b, substr($a, 1));
        }

        return self::sub($a, substr($b, 1));
    }

    public static function sub(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if (self::hasGmp()) {
            return self::gmpToString(gmp_sub(self::gmpInit($a), self::gmpInit($b)));
        }

        if (self::hasBcmath()) {
            return bcsub($a, $b, 0);
        }

        $aNegative = $a[0] === '-';
        $bNegative = $b[0] === '-';

        if ($aNegative !== $bNegative) {
            $result = self::addAbsolute(substr($a, $aNegative ? 1 : 0), substr($b, $bNegative ? 1 : 0));
            return $aNegative ? '-' . $result : $result;
        }

        $aAbs = $aNegative ? substr($a, 1) : $a;
        $bAbs = $bNegative ? substr($b, 1) : $b;

        $comparison = self::compareAbsolute($aAbs, $bAbs);

        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            $result = self::subAbsolute($aAbs, $bAbs);
            return $aNegative ? '-' . $result : $result;
        }

        $result = self::subAbsolute($bAbs, $aAbs);
        return $aNegative ? $result : '-' . $result;
    }

    public static function mul(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '0' || $b === '0') {
            return '0';
        }

        if (self::hasGmp()) {
            return self::gmpToString(gmp_mul(self::gmpInit($a), self::gmpInit($b)));
        }

        if (self::hasBcmath()) {
            return bcmul($a, $b, 0);
        }

        $aNegative = $a[0] === '-';
        $bNegative = $b[0] === '-';

        $aAbs = $aNegative ? substr($a, 1) : $a;
        $bAbs = $bNegative ? substr($b, 1) : $b;

        $result = self::mulAbsolute($aAbs, $bAbs);
        return ($aNegative xor $bNegative) ? '-' . $result : $result;
    }

    public static function div(string $a, string $b): string
    {
        if (self::hasGmp()) {
            [$quotient, ] = gmp_div_qr(self::gmpInit($a), self::gmpInit($b));
            return self::gmpToString($quotient);
        }

        if (self::hasBcmath()) {
            return bcdiv($a, $b, 0);
        }

        return self::divMod($a, $b)[0];
    }

    public static function mod(string $a, string $m): string
    {
        $a = self::normalize($a);
        $m = self::normalize($m);

        if ($m === '0') {
            throw new Exception('Division by zero in mod operation.');
        }

        if (self::hasGmp()) {
            $result = gmp_mod(self::gmpInit($a), self::gmpInit($m));
            if (gmp_sign($result) < 0) {
                $result = gmp_add($result, self::gmpInit($m));
            }
            return self::gmpToString($result);
        }

        if (self::hasBcmath()) {
            $remainder = bcmod($a, $m);
            if (strpos($remainder, '-') === 0) {
                $remainder = bcadd($remainder, $m, 0);
            }
            return self::normalize($remainder);
        }

        $remainder = self::divMod($a, $m)[1];

        if ($remainder[0] === '-') {
            $remainder = self::add($remainder, $m);
        }

        return self::normalize($remainder);
    }

    public static function modPow(string $base, string $exp, string $mod): string
    {
        $base = self::mod($base, $mod);
        $exp = self::normalize($exp);

        if (self::hasGmp()) {
            return self::gmpToString(gmp_powm(self::gmpInit($base), self::gmpInit($exp), self::gmpInit($mod)));
        }

        if (self::hasBcmath()) {
            return bcpowmod($base, $exp, $mod, 0);
        }

        $result = '1';

        while (self::cmp($exp, '0') > 0) {
            if (self::isOdd($exp)) {
                $result = self::mod(self::mul($result, $base), $mod);
            }

            $base = self::mod(self::mul($base, $base), $mod);
            $exp = self::div($exp, '2');
        }

        return self::mod($result, $mod);
    }

    public static function gcd(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if (self::hasGmp()) {
            return self::gmpToString(gmp_gcd(self::gmpInit($a), self::gmpInit($b)));
        }

        while (self::cmp($b, '0') !== 0) {
            $remainder = self::mod($a, $b);
            $a = $b;
            $b = $remainder;
        }

        return self::normalize($a);
    }

    public static function modInverse(string $a, string $m): string
    {
        $a = self::normalize($a);
        $m = self::normalize($m);
        $a = self::mod($a, $m);

        if ($a === '0') {
            throw new Exception('Modular inverse does not exist for zero.');
        }

        if (self::hasGmp()) {
            $inverse = gmp_invert(self::gmpInit($a), self::gmpInit($m));

            if ($inverse === false) {
                throw new Exception('Modular inverse does not exist for the given input.');
            }

            return self::gmpToString($inverse);
        }

        $m0 = $m;
        $x0 = '0';
        $x1 = '1';

        while (self::cmp($a, '1') > 0) {
            [$q, $r] = self::divMod($a, $m);
            $a = $m;
            $m = $r;
            $x2 = $x0;
            $x0 = self::sub($x1, self::mul($q, $x0));
            $x1 = $x2;
        }

        if (self::cmp($x1, '0') < 0) {
            $x1 = self::add($x1, $m0);
        }

        return self::normalize($x1);
    }

    public static function isProbablePrime(string $n, int $rounds = 5): bool
    {
        $n = self::normalize($n);

        if (self::cmp($n, '2') < 0) {
            return false;
        }

        if (self::isEven($n)) {
            return self::cmp($n, '2') === 0;
        }

        $d = self::sub($n, '1');
        $s = 0;

        while (self::isEven($d)) {
            $d = self::div($d, '2');
            $s++;
        }

        $witnesses = ['2', '325', '9375', '28178', '450775', '9780504', '1795265022'];

        foreach ($witnesses as $w) {
            if (self::cmp($w, self::sub($n, '2')) > 0) {
                continue;
            }

            $x = self::modPow($w, $d, $n);

            if (self::cmp($x, '1') === 0 || self::cmp($x, self::sub($n, '1')) === 0) {
                continue;
            }

            $composite = true;

            for ($r = 1; $r < $s; $r++) {
                $x = self::mod(self::mul($x, $x), $n);

                if (self::cmp($x, self::sub($n, '1')) === 0) {
                    $composite = false;
                    break;
                }
            }

            if ($composite) {
                return false;
            }
        }

        return true;
    }

    public static function randomBigInt(int $bits): string
    {
        $bytes = intdiv($bits + 7, 8);
        $random = bin2hex(random_bytes($bytes));
        $value = self::fromHex($random);
        $mask = self::pow2($bits);
        return self::mod($value, self::sub($mask, '1'));
    }

    public static function randomBetween(string $min, string $max): string
    {
        $min = self::normalize($min);
        $max = self::normalize($max);

        if (self::cmp($min, $max) > 0) {
            throw new Exception('Minimum value must be less than or equal to maximum value.');
        }

        $range = self::add(self::sub($max, $min), '1');
        $bits = max(1, strlen(self::toBinary($range)) * 8);

        do {
            $candidate = self::randomBigInt($bits);
        } while (self::cmp($candidate, $range) >= 0);

        return self::add($min, $candidate);
    }

    public static function randomPrime(int $bits): string
    {
        if ($bits < 16) {
            throw new Exception('Prime bit length must be at least 16.');
        }

        // Force the two top bits so p*q always has the full 2*bits length.
        $top = self::add(self::pow2($bits - 1), self::pow2($bits - 2));

        do {
            $candidate = self::add($top, self::mod(self::randomBigInt($bits), self::pow2($bits - 2)));

            if (self::isEven($candidate)) {
                $candidate = self::add($candidate, '1');
            }

            while (self::cmp($candidate, '1') > 0 && self::isEven($candidate)) {
                $candidate = self::add($candidate, '1');
            }
        } while (! self::isProbablePrime($candidate, 7));

        return $candidate;
    }

    public static function fromHex(string $hex): string
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);

        if ($hex === '') {
            return '0';
        }

        if (self::hasGmp()) {
            return self::gmpToString(gmp_init($hex, 16));
        }

        $value = '0';

        for ($i = 0, $length = strlen($hex); $i < $length; $i++) {
            $value = self::mul($value, '16');
            $value = self::add($value, (string) self::hexValue($hex[$i]));
        }

        return $value;
    }

    public static function toHex(string $dec): string
    {
        $dec = self::normalize($dec);

        if ($dec === '0') {
            return '0';
        }

        if (self::hasGmp()) {
            return gmp_strval(self::gmpInit($dec), 16);
        }

        $hex = '';

        while (self::cmp($dec, '0') > 0) {
            [$dec, $digit] = self::divMod($dec, '16');
            $hex = self::hexDigit($digit) . $hex;
        }

        return $hex;
    }

    private static function normalizeHexWord(string $hex, int $length): string
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
        if ($hex === '') {
            $hex = '0';
        }

        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return str_pad($hex, $length, '0', STR_PAD_LEFT);
    }

    public static function bitwiseAnd(string $a, string $b): string
    {
        $aHex = self::normalizeHexWord(self::toHex($a), 8);
        $bHex = self::normalizeHexWord(self::toHex($b), 8);

        $result = hex2bin($aHex) & hex2bin($bHex);
        return self::fromHex(bin2hex($result));
    }

    public static function bitwiseXor(string $a, string $b): string
    {
        $aHex = self::normalizeHexWord(self::toHex($a), 8);
        $bHex = self::normalizeHexWord(self::toHex($b), 8);

        $result = hex2bin($aHex) ^ hex2bin($bHex);
        return self::fromHex(bin2hex($result));
    }

    public static function bitwiseNot(string $a, int $bits = 32): string
    {
        $hex = self::normalizeHexWord(self::toHex($a), (int) ceil($bits / 4));
        $binary = hex2bin($hex);
        $output = '';

        foreach (str_split($binary) as $byte) {
            $output .= chr((~ord($byte)) & 0xFF);
        }

        return self::fromHex(bin2hex($output));
    }

    public static function fromBinary(string $binary): string
    {
        if (self::hasGmp()) {
            return self::gmpToString(gmp_init(bin2hex($binary), 16));
        }

        $value = '0';

        foreach (str_split($binary) as $byte) {
            $value = self::mul($value, '256');
            $value = self::add($value, (string) ord($byte));
        }

        return $value;
    }

    public static function toBinary(string $dec): string
    {
        $dec = self::normalize($dec);

        if ($dec === '0') {
            return '';
        }

        if (self::hasGmp()) {
            $hex = gmp_strval(self::gmpInit($dec), 16);
            $hex = strlen($hex) % 2 === 0 ? $hex : '0' . $hex;
            return hex2bin($hex) ?: '';
        }

        $bytes = [];

        while (self::cmp($dec, '0') > 0) {
            [$dec, $digit] = self::divMod($dec, '256');
            $bytes[] = chr((int) $digit);
        }

        return implode('', array_reverse($bytes));
    }

    public static function isEven(string $value): bool
    {
        $value = self::normalize($value);
        return (int) substr($value, -1) % 2 === 0;
    }

    public static function isOdd(string $value): bool
    {
        return ! self::isEven($value);
    }

    public static function pow2(int $bits): string
    {
        $result = '1';

        while ($bits-- > 0) {
            $result = self::mul($result, '2');
        }

        return $result;
    }

    private static function addAbsolute(string $a, string $b): string
    {
        $aDigits = array_reverse(str_split($a));
        $bDigits = array_reverse(str_split($b));
        $carry = 0;
        $result = [];
        $length = max(count($aDigits), count($bDigits));

        for ($i = 0; $i < $length; $i++) {
            $digitA = $aDigits[$i] ?? 0;
            $digitB = $bDigits[$i] ?? 0;
            $sum = $digitA + $digitB + $carry;
            $carry = intdiv($sum, 10);
            $result[] = $sum % 10;
        }

        if ($carry > 0) {
            $result[] = $carry;
        }

        return self::normalize(implode('', array_reverse($result)));
    }

    private static function subAbsolute(string $a, string $b): string
    {
        $aDigits = array_reverse(str_split($a));
        $bDigits = array_reverse(str_split($b));
        $borrow = 0;
        $result = [];
        $length = count($aDigits);

        for ($i = 0; $i < $length; $i++) {
            $digitA = $aDigits[$i];
            $digitB = $bDigits[$i] ?? 0;
            $difference = $digitA - $digitB - $borrow;

            if ($difference < 0) {
                $difference += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result[] = $difference;
        }

        return self::normalize(implode('', array_reverse($result)));
    }

    private static function mulAbsolute(string $a, string $b): string
    {
        $aDigits = array_reverse(str_split($a));
        $bDigits = array_reverse(str_split($b));
        $result = array_fill(0, count($aDigits) + count($bDigits), 0);

        foreach ($aDigits as $i => $digitA) {
            foreach ($bDigits as $j => $digitB) {
                $result[$i + $j] += $digitA * $digitB;
            }
        }

        $carry = 0;
        for ($i = 0; $i < count($result); $i++) {
            $temp = $result[$i] + $carry;
            $result[$i] = $temp % 10;
            $carry = intdiv($temp, 10);
        }

        while ($carry > 0) {
            $result[] = $carry % 10;
            $carry = intdiv($carry, 10);
        }

        return self::normalize(implode('', array_reverse($result)));
    }

    private static function divMod(string $a, string $b): array
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($b === '0') {
            throw new Exception('Division by zero.');
        }

        $negative = ($a[0] === '-') ^ ($b[0] === '-');
        $aAbs = $a[0] === '-' ? substr($a, 1) : $a;
        $bAbs = $b[0] === '-' ? substr($b, 1) : $b;

        if (self::compareAbsolute($aAbs, $bAbs) < 0) {
            $quotient = '0';
            $remainder = self::normalize($aAbs);

            if ($negative && $remainder !== '0') {
                $remainder = self::sub($bAbs, $remainder);
                $quotient = '1';
            }

            if ($negative && $quotient !== '0') {
                $quotient = '-' . $quotient;
            }

            return [$quotient, self::normalize($remainder)];
        }

        $quotient = '';
        $remainder = '0';

        foreach (str_split($aAbs) as $digit) {
            $remainder = self::add(self::mul($remainder, '10'), $digit);

            if (self::compareAbsolute($remainder, $bAbs) < 0) {
                $quotient .= '0';
                continue;
            }

            $low = 0;
            $high = 9;
            $digitResult = 0;

            while ($low <= $high) {
                $mid = intdiv($low + $high, 2);
                $candidate = self::mul($bAbs, (string) $mid);

                if (self::compareAbsolute($candidate, $remainder) <= 0) {
                    $digitResult = $mid;
                    $low = $mid + 1;
                } else {
                    $high = $mid - 1;
                }
            }

            $quotient .= (string) $digitResult;

            if ($digitResult > 0) {
                $remainder = self::subAbsolute($remainder, self::mul($bAbs, (string) $digitResult));
            }
        }

        $quotient = self::normalize($quotient);
        $remainder = self::normalize($remainder);

        if ($negative && $remainder !== '0') {
            $remainder = self::sub($bAbs, $remainder);
            $quotient = self::add($quotient, '1');
        }

        if ($negative && $quotient !== '0') {
            $quotient = '-' . $quotient;
        }

        return [$quotient, self::normalize($remainder)];
    }

    private static function hexValue(string $char): int
    {
        $char = strtolower($char);

        if ($char >= '0' && $char <= '9') {
            return ord($char) - ord('0');
        }

        return ord($char) - ord('a') + 10;
    }

    private static function hexDigit(string $value): string
    {
        $map = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
        return $map[(int) self::normalize($value)];
    }
}
