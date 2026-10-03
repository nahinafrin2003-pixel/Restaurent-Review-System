<?php

namespace App\Services\Crypto;

use App\Models\KeyPair;
use App\Services\Crypto\Asymmetric\ECC;
use App\Services\Crypto\Asymmetric\RSA;
use Exception;


class KeyManager
{
    protected static $keysEnsured = false;

    public function __construct()
    {
        if (!self::$keysEnsured) {
            $this->ensureKeysExist();
            self::$keysEnsured = true;
        }
    }

    public function ensureKeysExist(): void
    {
        if (!KeyPair::where('status', 'active')->exists()) {
            ini_set('max_execution_time', 0);
            set_time_limit(0);
            \Log::warning('No active encryption keys found. Generating initial key material.');
            $this->rotateKeys();
        }
    }

    public function rotateKeys(): void
    {
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        KeyPair::query()->update(['status' => 'rotated']);

        $rsaKeyPair = RSA::generateKeys(config('crypto.rsa_key_bits'));
        $eccKeyPair = ECC::generateKeyPair();

        KeyPair::create([
            'key_type' => 'rsa',
            'public_key' => json_encode(['n' => $rsaKeyPair['n'], 'e' => $rsaKeyPair['e']]),
            'private_key' => json_encode(['n' => $rsaKeyPair['n'], 'd' => $rsaKeyPair['d']]),
            'status' => 'active',
        ]);

        KeyPair::create([
            'key_type' => 'ecc',
            'public_key' => json_encode($eccKeyPair['public']),
            'private_key' => json_encode($eccKeyPair['private']),
            'status' => 'active',
        ]);
    }

    public function encryptUserField(string $plaintext): string
    {
        return RSA::encrypt($plaintext, $this->getActiveRsaPublic());
    }

    public function decryptUserField(string $ciphertext): string
    {
        $activePrivate = $this->getActiveRsaPrivate();

        try {
            $plaintext = RSA::decrypt($ciphertext, $activePrivate);

            if ($this->isLikelyPlaintext($plaintext)) {
                return $plaintext;
            }

            throw new \Exception('Active RSA key produced invalid plaintext.');
        } catch (\Exception $e) {
            \Log::warning('User field decryption failed with active RSA key. Trying inactive keys: ' . $e->getMessage());

            $inactiveKeys = KeyPair::where('key_type', 'rsa')
                ->where('status', 'rotated')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($inactiveKeys as $keyRecord) {
                try {
                    $privateKey = json_decode($keyRecord->private_key, true);

                    if (! is_array($privateKey)) {
                        continue;
                    }

                    $result = RSA::decrypt($ciphertext, $privateKey);

                    if ($this->isLikelyPlaintext($result)) {
                        \Log::warning('Successfully decrypted user field using inactive key ID ' . $keyRecord->id);
                        return $result;
                    }
                } catch (\Exception $keyError) {
                    continue;
                }
            }

            throw new \Exception('Could not decrypt user field with any available RSA key: ' . $e->getMessage());
        }
    }

    protected function isLikelyPlaintext(string $plaintext): bool
    {
        if ($plaintext === '') {
            return false;
        }

        if (! mb_check_encoding($plaintext, 'UTF-8')) {
            return false;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plaintext)) {
            return false;
        }

        $length = mb_strlen($plaintext, 'UTF-8');
        if ($length === 0) {
            return false;
        }

        preg_match_all('/[\p{L}\p{N}@._\-\s]/u', $plaintext, $matches);
        $allowedCount = count($matches[0]);

        return ($allowedCount / $length) >= 0.65;
    }

    public function encryptPostPayload(string $plaintext): string
    {
        return RSA::encrypt($plaintext, $this->getActiveRsaPublic());
    }

    public function decryptPostPayload(string $ciphertext): string
    {
        ini_set('max_execution_time', 300); // Increase timeout for decryption

        try {
            return RSA::decrypt($ciphertext, $this->getActiveRsaPrivate());
        } catch (\Exception $e) {
            \Log::warning('Post payload decryption failed with active RSA key. Trying inactive keys: ' . $e->getMessage());
            
            // Try inactive keys
            $inactiveKeys = KeyPair::where('key_type', 'rsa')
                ->where('status', 'rotated')
                ->orderBy('created_at', 'desc') // Try latest first
                ->limit(3) // Limit to 3 tries
                ->get();
            
            foreach ($inactiveKeys as $keyRecord) {
                try {
                    $decoded = json_decode($keyRecord->private_key, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $result = RSA::decrypt($ciphertext, $decoded);
                    \Log::warning('Successfully decrypted post payload using inactive key ID ' . $keyRecord->id);
                    return $result;
                } catch (\Exception $keyError) {
                    continue;
                }
            }
            
            // All keys failed
            throw new \Exception('Could not decrypt post payload with any available RSA key: ' . $e->getMessage());
        }
    }

    public function getTwoFactorEmail(): string
    {
        return config('crypto.two_factor_email');
    }

    protected function getActiveRsaPublic(): array
    {
        $decoded = json_decode($this->getActiveKeyPair('rsa')->public_key, true);

        if (! is_array($decoded)) {
            throw new Exception('Active RSA public key record is malformed.');
        }

        if (! RSA::isKeyLargeEnoughForOaep($decoded)) {
            \Log::warning('Active RSA public key is too small for OAEP padding. Rotating keys.');
            $this->rotateKeys();
            return $this->getActiveRsaPublic();
        }

        return $decoded;
    }

    protected function getActiveRsaPrivate(): array
    {
        $record = $this->getActiveKeyPair('rsa');
        
        if (!$record || empty($record->private_key)) {
            \Log::error('Active RSA private key record is empty. Rotating keys.');
            $this->rotateKeys();
            $record = $this->getActiveKeyPair('rsa');
        }

        $decoded = json_decode($record->private_key, true);

        if (! is_array($decoded) || ! isset($decoded['n'], $decoded['d'])) {
            \Log::error('Active RSA private key decoding failed. Record content: ' . substr($record->private_key, 0, 100) . '. Rotating keys.');
            $this->rotateKeys();

            $record = $this->getActiveKeyPair('rsa');
            $decoded = json_decode($record->private_key, true);

            if (! is_array($decoded) || ! isset($decoded['n'], $decoded['d'])) {
                throw new Exception('Unable to generate/retrieve valid RSA private key after key rotation.');
            }
        }

        if (! RSA::isKeyLargeEnoughForOaep($decoded)) {
            \Log::warning('Active RSA private key is too small for OAEP padding. Rotating keys.');
            $this->rotateKeys();
            return $this->getActiveRsaPrivate();
        }

        return $decoded;
    }

    public function getActiveEccPublic(): array
    {
        $decoded = json_decode($this->getActiveKeyPair('ecc')->public_key, true);

        if (! is_array($decoded)) {
            throw new Exception('Active ECC public key record is malformed.');
        }

        return $decoded;
    }

    public function getActiveEccPrivate(): string
    {
        $record = $this->getActiveKeyPair('ecc');
        
        if (!$record || empty($record->private_key)) {
            \Log::error('Active ECC private key record is empty. Rotating keys.');
            $this->rotateKeys();
            $record = $this->getActiveKeyPair('ecc');
        }

        $decoded = json_decode($record->private_key, true);

        if (! is_string($decoded) || ! preg_match('/^[0-9a-fA-F]+$/', $decoded)) {
            \Log::error('Active ECC private key decoding failed. Record content: ' . substr($record->private_key, 0, 100) . '. Rotating keys.');
            $this->rotateKeys();

            $record = $this->getActiveKeyPair('ecc');
            $decoded = json_decode($record->private_key, true);

            if (! is_string($decoded) || ! preg_match('/^[0-9a-fA-F]+$/', $decoded)) {
                throw new Exception('Unable to generate/retrieve valid ECC private key after key rotation.');
            }
        }

        return $decoded;
    }

    protected function getActiveKeyPair(string $keyType): KeyPair
    {
        $record = KeyPair::where('key_type', $keyType)
            ->where('status', 'active')
            ->first();

        if (! $record) {
            \Log::warning("No active {$keyType} key pair found. Generating new keys.");
            $this->rotateKeys();

            $record = KeyPair::where('key_type', $keyType)
                ->where('status', 'active')
                ->first();

            if (! $record) {
                throw new Exception("Failed to generate active {$keyType} key pair.");
            }
        }

        return $record;
    }

    /* ------------------------------------------------------------------
     * Generic helpers used by the complaint module (and new code paths)
     * ------------------------------------------------------------------ */

    /** Per-request memo so the same ciphertext is never decrypted twice. */
    protected static array $memo = [];

    /** SHA-256 lookup hash for encrypted columns (custom implementation). */
    public static function lookupHash(string $value): string
    {
        return SHA256::hash($value);
    }

    /** RSA-OAEP encrypt with the active key. */
    public function encrypt(string $plaintext): string
    {
        return RSA::encrypt($plaintext, $this->getActiveRsaPublic());
    }

    /** RSA-OAEP decrypt, falling back to rotated keys (OAEP integrity check tells us which key fits). */
    public function decrypt(?string $ciphertext): string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return '';
        }
        if (isset(self::$memo[$ciphertext])) {
            return self::$memo[$ciphertext];
        }

        $candidates = [$this->getActiveRsaPrivate()];
        foreach (KeyPair::where('key_type', 'rsa')->where('status', 'rotated')->orderByDesc('key_id')->get() as $record) {
            $decoded = json_decode($record->private_key, true);
            if (is_array($decoded)) {
                $candidates[] = $decoded;
            }
        }

        foreach ($candidates as $key) {
            try {
                return self::$memo[$ciphertext] = RSA::decrypt($ciphertext, $key);
            } catch (\Throwable $e) {
                continue;
            }
        }

        throw new Exception('Unable to decrypt RSA field with any known key.');
    }

    /** EC-ElGamal (ECIES-style) hybrid encryption with the active secp256k1 key. */
    public function seal(string $plaintext): string
    {
        return ECC::seal($plaintext, $this->getActiveEccPublic());
    }

    public function open(?string $ciphertext): string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return '';
        }
        if (isset(self::$memo[$ciphertext])) {
            return self::$memo[$ciphertext];
        }

        $keys = [$this->getActiveEccPrivate()];
        foreach (KeyPair::where('key_type', 'ecc')->where('status', 'rotated')->orderByDesc('key_id')->get() as $record) {
            $decoded = json_decode($record->private_key, true);
            if (is_string($decoded)) {
                $keys[] = $decoded;
            }
        }

        foreach ($keys as $key) {
            try {
                return self::$memo[$ciphertext] = ECC::open($ciphertext, $key);
            } catch (\Throwable $e) {
                continue;
            }
        }

        throw new Exception('Unable to open ECIES field with any known key.');
    }

    /** ECDSA-style signature; records which key signed so rotation does not break verification. */
    public function sign(string $message): array
    {
        $record = $this->getActiveKeyPair('ecc');
        $signature = ECC::sign($message, $this->getActiveEccPrivate());
        $signature['kid'] = $record->key_id;

        return $signature;
    }

    public function verify(string $message, $signature): bool
    {
        if (is_string($signature)) {
            $signature = json_decode($signature, true);
        }
        if (! is_array($signature) || ! isset($signature['r'], $signature['s'])) {
            return false;
        }

        $query = KeyPair::where('key_type', 'ecc');
        if (! empty($signature['kid'])) {
            $query->where('key_id', $signature['kid']);
        } else {
            $query->orderByRaw("case when status = 'active' then 0 else 1 end")->orderByDesc('key_id');
        }

        foreach ($query->get() as $record) {
            $public = json_decode($record->public_key, true);
            if (is_array($public) && ECC::verify($message, $signature, $public)) {
                return true;
            }
        }

        return false;
    }

    public function mac(string $message): string
    {
        return HMAC::sign($message, (string) config('crypto.root_secret'));
    }

    public function verifyMac(string $message, ?string $mac): bool
    {
        return $mac !== null && $mac !== '' && HMAC::verify($message, (string) config('crypto.root_secret'), $mac);
    }

    public function activeKeySummary(): array
    {
        return KeyPair::orderByDesc('key_id')->get()->map(fn ($k) => [
            'id' => $k->key_id,
            'type' => strtoupper($k->key_type),
            'status' => $k->status,
            'created_at' => $k->created_at,
            'fingerprint' => substr(SHA256::hash($k->public_key), 0, 32),
        ])->all();
    }
}
