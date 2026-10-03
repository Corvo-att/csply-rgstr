<?php

namespace App\Support;

/** Reads the limits PHP itself puts on uploads, which no form setting can exceed. */
class ServerLimits
{
    /** The largest single request body (in MB) the server will accept. */
    public static function maxUploadMb(): float
    {
        $limits = array_filter([
            self::toBytes(ini_get('upload_max_filesize')),
            self::toBytes(ini_get('post_max_size')),
        ]);

        return $limits ? round(min($limits) / 1048576, 1) : 0;
    }

    private static function toBytes(string|false $value): int
    {
        if ($value === false || $value === '') {
            return 0;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
