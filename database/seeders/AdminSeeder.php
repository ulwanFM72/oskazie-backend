<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('oskazie.admin_username');
        $password = config('oskazie.admin_password');

        if (empty($password)) {
            throw new RuntimeException('Isi ADMIN_PASSWORD di .env sebelum menjalankan seeder.');
        }

        DB::table('users')->updateOrInsert(
            ['username' => $username],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
