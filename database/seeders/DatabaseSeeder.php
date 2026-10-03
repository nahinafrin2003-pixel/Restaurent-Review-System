<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** php artisan db:seed  → admin account + demo data (officers, customers, restaurants, complaints). */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
