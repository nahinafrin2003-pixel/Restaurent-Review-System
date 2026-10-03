<?php

namespace App\Services\Crypto;

/**
 * From-scratch SHA-256 (FIPS 180-4).
 *
 * No calls to hash()/openssl/sodium — every round of the compression
 * function is implemented here with native 64-bit integer arithmetic
 * masked to 32 bits. Supports one-shot hashing and streaming (for files).
 */
class SHA256
{
    private const K = [
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
    ];

    private const H0 = [
        0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19,
    ];

    private array $state;
    private string $buffer = '';
    private int $length = 0;

    public function __construct()
    {
        $this->state = self::H0;
    }

    /** Hex digest of a message. */
    public static function hash(string $message): string
    {
        return bin2hex(self::raw($message));
    }

    /** Raw 32-byte digest of a message. */
    public static function raw(string $message): string
    {
        $ctx = new self();
        $ctx->update($message);

        return $ctx->finalRaw();
    }

    /** Hex digest of a file, streamed in 64 KiB chunks. */
    public static function file(string $path): string
    {
        $ctx = new self();
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open {$path} for hashing.");
        }
        while (! feof($fh)) {
            $chunk = fread($fh, 65536);
            if ($chunk === false) {
                break;
            }
            $ctx->update($chunk);
        }
        fclose($fh);

        return bin2hex($ctx->finalRaw());
    }

    public function update(string $data): void
    {
        $this->length += strlen($data);
        $this->buffer .= $data;
        $blocks = intdiv(strlen($this->buffer), 64);
        for ($i = 0; $i < $blocks; $i++) {
            $this->compress(substr($this->buffer, $i * 64, 64));
        }
        $this->buffer = (string) substr($this->buffer, $blocks * 64);
    }

    public function finalRaw(): string
    {
        $bitLen = $this->length * 8;
        $pad = "\x80" . str_repeat("\x00", (55 - $this->length % 64 + 64) % 64);
        // 64-bit big-endian length
        $lenBytes = '';
        for ($i = 7; $i >= 0; $i--) {
            $lenBytes .= chr(($bitLen >> ($i * 8)) & 0xff);
        }
        $tail = $this->buffer . $pad . $lenBytes;
        for ($i = 0; $i < strlen($tail); $i += 64) {
            $this->compress(substr($tail, $i, 64));
        }

        $out = '';
        foreach ($this->state as $word) {
            $out .= chr(($word >> 24) & 0xff) . chr(($word >> 16) & 0xff) . chr(($word >> 8) & 0xff) . chr($word & 0xff);
        }

        return $out;
    }

    private function compress(string $block): void
    {
        $w = array_values(unpack('N16', $block));
        $k = self::K;

        for ($i = 16; $i < 64; $i++) {
            $x = $w[$i - 15];
            $y = $w[$i - 2];
            $s0 = (self::rotr($x, 7) ^ self::rotr($x, 18) ^ ($x >> 3));
            $s1 = (self::rotr($y, 17) ^ self::rotr($y, 19) ^ ($y >> 10));
            $w[$i] = ($w[$i - 16] + $s0 + $w[$i - 7] + $s1) & 0xffffffff;
        }

        [$a, $b, $c, $d, $e, $f, $g, $h] = $this->state;

        for ($i = 0; $i < 64; $i++) {
            $S1 = self::rotr($e, 6) ^ self::rotr($e, 11) ^ self::rotr($e, 25);
            $ch = ($e & $f) ^ ((~$e & 0xffffffff) & $g);
            $t1 = ($h + $S1 + $ch + $k[$i] + $w[$i]) & 0xffffffff;
            $S0 = self::rotr($a, 2) ^ self::rotr($a, 13) ^ self::rotr($a, 22);
            $maj = ($a & $b) ^ ($a & $c) ^ ($b & $c);
            $t2 = ($S0 + $maj) & 0xffffffff;

            $h = $g;
            $g = $f;
            $f = $e;
            $e = ($d + $t1) & 0xffffffff;
            $d = $c;
            $c = $b;
            $b = $a;
            $a = ($t1 + $t2) & 0xffffffff;
        }

        $this->state[0] = ($this->state[0] + $a) & 0xffffffff;
        $this->state[1] = ($this->state[1] + $b) & 0xffffffff;
        $this->state[2] = ($this->state[2] + $c) & 0xffffffff;
        $this->state[3] = ($this->state[3] + $d) & 0xffffffff;
        $this->state[4] = ($this->state[4] + $e) & 0xffffffff;
        $this->state[5] = ($this->state[5] + $f) & 0xffffffff;
        $this->state[6] = ($this->state[6] + $g) & 0xffffffff;
        $this->state[7] = ($this->state[7] + $h) & 0xffffffff;
    }

    private static function rotr(int $x, int $n): int
    {
        return (($x >> $n) | ($x << (32 - $n))) & 0xffffffff;
    }
}
