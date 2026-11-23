<?php

namespace Database\Seeders;

use App\Support\VoterIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DATA UJI SAJA. Hanya jalan di APP_ENV=local/testing, dan jalankan sekali pada database kosong
 * (php artisan migrate:fresh --seed lalu db:seed --class=DemoDataSeeder).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoDataSeeder hanya boleh dijalankan di lingkungan local/testing.');
        }

        $adminId = DB::table('users')->where('username', config('oskazie.admin_username'))->value('id');
        if (! $adminId) {
            throw new RuntimeException('Jalankan AdminSeeder terlebih dahulu.');
        }

        $now = now();

        // Pemilihan uji: sedang berlangsung selama 8 jam ke depan.
        $electionId = DB::table('elections')->insertGetId([
            'name' => 'Pemilihan Ketua dan Wakil Ketua OSIS (DATA UJI)',
            'start_at' => $now->copy()->subHour(),
            'end_at' => $now->copy()->addHours(8),
            'created_by' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Paslon uji
        foreach ([1, 2, 3] as $n) {
            DB::table('candidates')->insert([
                'election_id' => $electionId,
                'number_order' => $n,
                'chairman_name' => "Calon Ketua Uji {$n}",
                'chairman_class' => 'XI TKJ 1',
                'vice_name' => "Calon Wakil Uji {$n}",
                'vice_class' => 'X TKJ 1',
                'vision' => 'Visi contoh (data uji).',
                'mission' => 'Misi contoh (data uji).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Pemilih uji: 12 siswa (NIS uji) dan 4 guru/staf
        $classes = ['X TKJ 1', 'XI TKJ 1', 'XII TKJ 1'];
        for ($i = 1; $i <= 12; $i++) {
            $nis = '2627100' . str_pad((string) $i, 2, '0', STR_PAD_LEFT); // 262710001 dst.
            DB::table('voters')->insert([
                'type' => 'siswa',
                'name' => 'Siswa Uji ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'nis_hash' => VoterIdentity::hashNis($nis),
                'class_name' => $classes[($i - 1) % 3],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $gurus = [
            ['Guru Uji Satu, S.Pd.', 'Guru'],
            ['Guru Uji Dua, M.Pd.', 'Guru'],
            ['Kepala Sekolah Uji, M.Si.', 'Kepala Sekolah'],
            ['Staf TU Uji', 'TU'],
        ];
        foreach ($gurus as [$name, $unit]) {
            DB::table('voters')->insert([
                'type' => 'guru',
                'name' => $name,
                'login_key' => VoterIdentity::normalizeName($name),
                'unit' => $unit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Snapshot DPT
        $rows = DB::table('voters')->where('is_active', true)->get()->map(fn ($v) => [
            'election_id' => $electionId,
            'voter_id' => $v->id,
            'voter_type' => $v->type,
            'class_name' => $v->class_name,
            'unit' => $v->unit,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        DB::table('election_voters')->insert($rows);

        $this->command?->info('Data uji dibuat. Contoh login siswa: NIS 262710001');
        $this->command?->info('Contoh login guru: Guru Uji Satu, S.Pd.');
    }
}
