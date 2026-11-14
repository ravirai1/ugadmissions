<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The admissions prospectus PDF. One current file, kept on the documents disk (S3 in production).
 */
class Prospectus
{
    public const KEY = 'prospectus';

    public const MAX_KB = 30720; // 30 MB

    /** @return array{path:string,title:string,name:string,size:int,uploaded_at:string}|null */
    public static function current(): ?array
    {
        $p = Setting::get(self::KEY);

        return is_array($p) && ! empty($p['path']) ? $p : null;
    }

    public static function exists(): bool
    {
        return self::current() !== null;
    }
}
