<?php

namespace Database\Seeders;

use App\Models\AdminLimit;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\Crypto\KeyManager;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public const EMAIL = 'nahin.afrin@g.bracu.ac.bd';
    public const PASSWORD = 'AdminPassword123';

    public function run(): void
    {
        app(KeyManager::class)->ensureKeysExist();

        if (User::findByEmail(self::EMAIL)) {
            $this->command?->info('Admin already exists — skipped.');

            return;
        }

        $admin = User::create([
            'username' => 'admin',
            'email' => self::EMAIL,
            'contact_info' => '01234567890',
            'password' => self::PASSWORD,
            'role' => 'admin',
            'is_active' => true,
        ]);

        AdminLimit::create(['admin_id' => $admin->user_id, 'current_pending_count' => 0, 'max_pending_limit' => 10, 'status' => 'active']);
        UserActivity::create(['user_id' => $admin->user_id, 'remaining_reviews' => 5]);

        $this->command?->info('Admin: ' . self::EMAIL . ' / ' . self::PASSWORD);
    }
}
