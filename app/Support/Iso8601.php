<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Iso8601
{
    /** ISO 8601 wajib disertai zona waktu (Z atau +07:00). */
    public const REGEX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/';

    public static function toUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->utc();
    }
}
