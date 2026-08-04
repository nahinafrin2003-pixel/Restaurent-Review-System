<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use App\Services\Crypto\PasswordHasher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'username',
        'email',
        'contact_info',
        'password',
        'password_hash',
        'password_salt',
        'role',
    ];

    protected $hidden = [
        'password_hash',
        'password_salt',
        'email_hash',
        'contact_hash',
    ];

    // Relationships
    /**
     * Defines relationship to user's reviews.
     * One user can have many reviews.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id', 'user_id');
    }

    /**
     * Defines relationship to user's profile.
     * One user has one profile.
     */
    public function userProfile()
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'user_id');
    }

    /**
     * Defines relationship to user's OTP records.
     * One user can have many OTPs.
     */
    public function otps()
    {
        return $this->hasMany(Otp::class, 'user_id', 'user_id');
    }

    /**
     * Defines relationship to user's activity tracking.
     * One user has one activity record.
     */
    public function userActivity()
    {
        return $this->hasOne(UserActivity::class, 'user_id', 'user_id');
    }

    /**
     * Defines relationship to user's sessions.
     * One user can have many sessions.
     */
    public function sessions()
    {
        return $this->hasMany(SessionTable::class, 'user_id', 'user_id');
    }

    // Username Attribute Accessors
    /**
     * Encrypts and hashes the username for secure storage.
     * Stores SHA-256 hash for lookups and encrypts the actual value using ECC.
     */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username_hash'] = hash('sha256', $value);
        $this->attributes['username'] = app(KeyManager::class)->encryptUserField($value);
    }

    /**
     * Decrypts the stored username using ECC for retrieval.
     * Returns plaintext username or empty string on failure.
     */
    public function getUsernameAttribute(): string
    {
        $encrypted = $this->getRawOriginal('username');
        
        if (empty($encrypted)) {
            return '';
        }

        try {
            $plaintext = app(KeyManager::class)->decryptUserField($encrypted);
            return $plaintext ?? '';
        } catch (\Exception $e) {
            \Log::warning('User username decryption failed: ' . $e->getMessage());
            return '';
        }
    }

    // Email Attribute Accessors
    /**
     * Normalizes, hashes, and encrypts the email for secure storage.
     * Stores SHA-256 hash for lookups and encrypts the normalized value using ECC.
     */
    public function setEmailAttribute(string $value): void
    {
        $normalized = strtolower(trim($value));
        $this->attributes['email_hash'] = hash('sha256', $normalized);
        $this->attributes['email'] = app(KeyManager::class)->encryptUserField($normalized);
    }

    /**
     * Decrypts the stored email using ECC for retrieval.
     * Returns plaintext email or empty string on failure.
     */
    public function getEmailAttribute(): string
    {
        $encrypted = $this->getRawOriginal('email');
        
        if (empty($encrypted)) {
            return '';
        }

        try {
            return app(KeyManager::class)->decryptUserField($encrypted);
        } catch (\Exception $e) {
            \Log::warning('User email decryption failed: ' . $e->getMessage());
            return '';
        }
    }

    // Contact Info Attribute Accessors
    /**
     * Normalizes, hashes, and encrypts the contact info for secure storage.
     * Stores SHA-256 hash for lookups and encrypts the value using ECC.
     */
    public function setContactInfoAttribute(string $value): void
    {
        $normalized = self::normalizeContact($value);
        $this->attributes['contact_hash'] = hash('sha256', $normalized);
        $this->attributes['contact_info'] = app(KeyManager::class)->encryptUserField($value);
    }

    /**
     * Decrypts the stored contact info using ECC for retrieval.
     * Returns plaintext contact info or empty string on failure.
     */
    public function getContactInfoAttribute(): string
    {
        $encrypted = $this->getRawOriginal('contact_info');
        
        if (empty($encrypted)) {
            return '';
        }

        try {
            return app(KeyManager::class)->decryptUserField($encrypted);
        } catch (\Exception $e) {
            \Log::warning('User contact decryption failed: ' . $e->getMessage());
            return '';
        }
    }

    // Helper Methods
    /**
     * Normalizes contact info by removing all non-digit characters.
     * Used for consistent hashing and storage of phone numbers.
     */
    private static function normalizeContact(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    /**
     * Finds a user by contact info using the hashed value for privacy.
     * Normalizes input and searches by contact_hash.
     */
    public static function findByContact(string $contact): ?self
    {
        return self::where('contact_hash', hash('sha256', self::normalizeContact($contact)))->first();
    }

    /**
     * Finds a user by email using the hashed value for privacy.
     * Normalizes input and searches by email_hash.
     */
    public static function findByEmail(string $email): ?self
    {
        return self::where('email_hash', hash('sha256', strtolower(trim($email))))->first();
    }

    /**
     * Finds a user by username using the hashed value for privacy.
     * Searches by username_hash.
     */
    public static function findByUsername(string $username): ?self
    {
        return self::where('username_hash', hash('sha256', $username))->first();
    }

    /**
     * Hashes and salts the password for secure storage.
     * Uses custom PasswordHasher service with PBKDF2-like algorithm.
     */
    public function setPasswordAttribute(string $value): void
    {
        $result = PasswordHasher::hash($value);
        $this->attributes['password_salt'] = $result['salt'];
        $this->attributes['password_hash'] = $result['hash'];
    }

    /**
     * Verifies a plaintext password against the stored hash and salt.
     * Uses custom PasswordHasher service for comparison.
     */
    public function verifyPassword(string $password): bool
    {
        return PasswordHasher::verify($password, $this->password_salt, $this->password_hash);
    }

    /**
     * Returns the password hash for Laravel authentication.
     * Required by Authenticatable interface.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Checks if the user has admin role.
     * Returns true if role is 'admin', false otherwise.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
