<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya akun admin. Data uji dijalankan terpisah: php artisan db:seed --class=DemoDataSeeder
        $this->call(AdminSeeder::class);
    }
}
