<?php

return [
    // Pepper untuk HMAC NIS. Simpan di .env, JANGAN diubah setelah data pemilih diimpor
    // (semua hash NIS akan tidak cocok lagi). Cadangkan nilainya.
    // Nama lama OSKAZIE_NIK_PEPPER tetap dibaca agar .env yang sudah ada tidak perlu diubah.
    'nis_pepper' => env('OSKAZIE_NIS_PEPPER', env('OSKAZIE_NIK_PEPPER')),

    // Akun admin awal (dipakai AdminSeeder).
    'admin_username' => env('ADMIN_USERNAME', 'admin'),
    'admin_password' => env('ADMIN_PASSWORD'),
];
