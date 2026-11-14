<?php

namespace App\Support;

final class DocumentTypes
{
    /** type => [label, allowed extensions, max KB] */
    public const TYPES = [
        'photo' => ['Recent passport-size photograph', ['jpg', 'jpeg', 'png'], 300],
        'signature' => ['Signature', ['jpg', 'jpeg', 'png'], 150],
        'class10' => ['Class 10 marksheet / certificate', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'class12' => ['Class 12 marksheet', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'cuet_scorecard' => ['CUET (UG) 2027 scorecard', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'csas_preference' => ['DU CSAS (UG) preference page', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'baptism' => ['Baptism certificate', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'parent_baptism' => ["Parent's baptism certificate", ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'church_letter' => ['Letter from priest / church membership', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'st_certificate' => ['ST certificate (for CST)', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'category_certificate' => ['Category certificate (OBC-NCL / SC / ST / EWS)', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'pwbd_certificate' => ['Disability certificate (UDID)', ['jpg', 'jpeg', 'png', 'pdf'], 2048],
        'other' => ['Other document', ['jpg', 'jpeg', 'png', 'pdf'], 2048], // query replies only
    ];

    public const MIMES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf',
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type][0] ?? $type;
    }

    public static function exts(string $type): array
    {
        return self::TYPES[$type][1];
    }

    public static function maxKb(string $type): int
    {
        return self::TYPES[$type][2];
    }

    public static function mimes(string $type): array
    {
        return array_values(array_unique(array_map(fn ($e) => self::MIMES[$e], self::exts($type))));
    }
}
