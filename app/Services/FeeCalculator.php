<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Setting;

class FeeCalculator
{
    /** Current fees: values saved by the super admin override config/payment.php. */
    public static function fees(): array
    {
        return array_map('intval', array_merge(config('payment.fees'), (array) Setting::get('fees', [])));
    }

    /** @return array{lines: array<int, array{label: string, amount: int}>, total: int} */
    public function forApplication(Application $app): array
    {
        $f = self::fees();
        $courses = $app->courses()->with('course')->where('added_after_payment', false)->get();
        $lines = [];

        foreach ($courses->values() as $i => $c) {
            $lines[] = ['label' => $c->course->name, 'amount' => $i === 0 ? $f['first_course'] : $f['additional_course']];
        }
        if ($app->residence) {
            $lines[] = ['label' => 'Residence', 'amount' => $f['residence']];
        }

        return ['lines' => $lines, 'total' => array_sum(array_column($lines, 'amount'))];
    }

    public function forAddedCourses(int $count): int
    {
        return $count * self::fees()['course_after_payment'];
    }

    public function forAddedResidence(): int
    {
        return self::fees()['residence_after_payment'];
    }
}
