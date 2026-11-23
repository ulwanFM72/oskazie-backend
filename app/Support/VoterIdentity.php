<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class VoterIdentity
{
    /**
     * HMAC-SHA256 dari NIS (hanya digit) dengan pepper dari .env.
     * NIS asli tidak disimpan, hanya hash-nya.
     */
    public static function hashNis(string $nis): string
    {
        $pepper = config('oskazie.nis_pepper');
        if (empty($pepper)) {
            throw new RuntimeException('OSKAZIE_NIS_PEPPER (atau OSKAZIE_NIK_PEPPER) belum diatur di .env');
        }

        $digits = preg_replace('/\D+/', '', $nis);
        if ($digits === '' || $digits === null) {
            throw new InvalidArgumentException('NIS kosong atau tidak valid.');
        }

        return hash_hmac('sha256', $digits, $pepper);
    }

    /**
     * Normalisasi nama lengkap untuk login guru:
     * huruf kecil, tanda baca jadi spasi, spasi berlebih dirapikan.
     * "Budi Santoso, S.Pd." => "budi santoso s pd"
     */
    public static function normalizeName(string $name): string
    {
        $s = mb_strtolower($name, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }
}
