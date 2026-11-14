<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Checks for the applicant's photograph and signature: size, shape and a white / light background.
 * The background test samples pixels with GD; it is a guide, and scrutiny staff still look at the photo.
 */
final class PhotoRules
{
    public const RULES = [
        'photo' => ['min_kb' => 20, 'min_w' => 200, 'min_h' => 230, 'ratio' => [1.1, 1.5]],      // height / width, passport 3.5 x 4.5 cm
        'signature' => ['min_kb' => 4, 'min_w' => 200, 'min_h' => 60, 'ratio' => [0.2, 0.6]],    // height / width, landscape
    ];

    /** Shown on the upload step and the Documents Required page. */
    public const INSTRUCTIONS = [
        'photo' => [
            'Recent colour photograph taken within the <strong>last 3 months</strong>.',
            '<strong>Plain white background</strong>, no shadows, no patterns, no other people or objects.',
            'Face looking straight at the camera, eyes open, full face from the top of the hair to the shoulders, face taking up about 70% of the photo.',
            'No caps, hats or dark glasses (religious headwear is allowed if the face is fully visible). Normal glasses only if there is no glare.',
            'Passport size, portrait (taller than wide), about 3.5 cm × 4.5 cm; at least 200 × 230 pixels.',
            'JPG or PNG only, between 20 KB and 300 KB. Do not upload a selfie, a group photo cropped down, or a scanned old photo.',
        ],
        'signature' => [
            'Sign in <strong>black or blue ink</strong> on <strong>plain white paper</strong>; your own full signature, not in capital letters.',
            'Crop closely around the signature, landscape (wider than tall), about 3.5 cm × 1.5 cm; at least 200 × 60 pixels.',
            'No lines, shadows or other writing in the picture.',
            'JPG or PNG only, between 4 KB and 150 KB.',
        ],
    ];

    /** Returns an error message, or null if the image is acceptable. */
    public static function check(string $type, UploadedFile $file): ?string
    {
        $r = self::RULES[$type] ?? null;
        if (! $r) {
            return null;
        }
        $label = $type === 'photo' ? 'Photograph' : 'Signature';
        if ($file->getSize() < $r['min_kb'] * 1024) {
            return "{$label}: the file is too small (minimum {$r['min_kb']} KB). Please upload a clearer image.";
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info) {
            return "{$label}: the image could not be read.";
        }
        [$w, $h] = $info;
        if ($w < $r['min_w'] || $h < $r['min_h']) {
            return "{$label}: the image is too small ({$w} × {$h} pixels). It must be at least {$r['min_w']} × {$r['min_h']} pixels.";
        }
        $ratio = $h / $w;
        if ($ratio < $r['ratio'][0] || $ratio > $r['ratio'][1]) {
            return $type === 'photo'
                ? 'Photograph: use a passport-size portrait photo (taller than wide, about 3.5 × 4.5 cm).'
                : 'Signature: crop closely around the signature so that it is wider than tall (about 3.5 × 1.5 cm).';
        }

        $img = self::load($file->getRealPath(), $info[2]);
        if (! $img) {
            return null; // format checks already passed; skip the background test if GD cannot open it
        }
        try {
            return $type === 'photo' ? self::photoBackground($img, $w, $h) : self::signatureBackground($img, $w, $h);
        } finally {
            imagedestroy($img);
        }
    }

    private static function load(string $path, int $kind): \GdImage|false
    {
        return match ($kind) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => false,
        };
    }

    /** [brightness 0-255, saturation-ish spread 0-255] of a pixel; transparent pixels count as white. */
    private static function px(\GdImage $img, int $x, int $y): array
    {
        $c = imagecolorat($img, $x, $y);
        if (imageistruecolor($img)) {
            $a = ($c >> 24) & 0x7F;
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;
        } else {
            $k = imagecolorsforindex($img, $c);
            [$r, $g, $b, $a] = [$k['red'], $k['green'], $k['blue'], $k['alpha']];
        }
        if ($a > 100) {
            return [255, 0];
        }

        return [($r * 299 + $g * 587 + $b * 114) / 1000, max($r, $g, $b) - min($r, $g, $b)];
    }

    private static function light(array $p): bool
    {
        return $p[0] >= 190 && $p[1] <= 45;
    }

    /** The top strip and the upper parts of both sides (around the head) should be white / very light. */
    private static function photoBackground(\GdImage $img, int $w, int $h): ?string
    {
        $bx = max(2, (int) ($w * 0.08));
        $by = max(2, (int) ($h * 0.06));
        $total = $ok = 0;
        $step = max(1, (int) (min($w, $h) / 60));
        for ($y = 0; $y < $by; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $total++;
                $ok += self::light(self::px($img, $x, $y)) ? 1 : 0;
            }
        }
        for ($y = $by; $y < (int) ($h * 0.6); $y += $step) {
            for ($x = 0; $x < $bx; $x += $step) {
                $total += 2;
                $ok += self::light(self::px($img, $x, $y)) ? 1 : 0;
                $ok += self::light(self::px($img, $w - 1 - $x, $y)) ? 1 : 0;
            }
        }

        return $total && $ok / $total < 0.8
            ? 'Photograph: the background must be plain white. Please retake the photo against a white wall or sheet.'
            : null;
    }

    /** Mostly white paper with some dark ink. */
    private static function signatureBackground(\GdImage $img, int $w, int $h): ?string
    {
        $total = $light = $ink = 0;
        $step = max(1, (int) (min($w, $h) / 40));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $p = self::px($img, $x, $y);
                $total++;
                $light += self::light($p) ? 1 : 0;
                $ink += $p[0] < 130 ? 1 : 0;
            }
        }
        if ($total && $light / $total < 0.75) {
            return 'Signature: sign on plain white paper and crop closely; the background must be white.';
        }
        if ($total && $ink / $total < 0.005) {
            return 'Signature: the signature is too faint. Sign with a dark black or blue pen.';
        }

        return null;
    }
}
