<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\SeatOffer;
use App\Notifications\SeatOffered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seat allotment against each course's seats (by category), respecting DU preferences.
 *
 * - Only courses whose interview marks are finalised take part.
 * - Within a course, General (UR) seats are filled first by overall merit, then each reserved category
 *   (and PWBD) from candidates of that category, in the order the seats are listed for the course.
 * - A candidate who is in the merit list of several courses gets the seat in the course they put first
 *   in their DU preferences, among those where their merit is good enough (student-proposing deferred acceptance).
 *   They are shown in the other courses as "Already selected – first preference".
 * - Seats held by earlier offers (offered or accepted) are not offered again. Candidates who already
 *   have an offer, or declined one, or let one lapse, are not considered again.
 */
class AllotmentService
{
    public function __construct(private MeritService $merit) {}

    /** Seats per category for a course, General first. */
    public static function seats(Course $course): array
    {
        $seats = array_filter(array_map('intval', $course->seats ?? []), fn ($n) => $n > 0);
        uksort($seats, fn ($a, $b) => ($b === 'UR') <=> ($a === 'UR'));

        return $seats;
    }

    /**
     * Work out the next round without saving anything.
     *
     * @return array{courses: Collection, assigned: array<int, array{course_id:int, bucket:string, row:array}>, lists: array<int, Collection>, vacant: array<int, array<string,int>>}
     */
    public function preview(): array
    {
        $courses = Course::whereIn('id', InterviewService::lockedCourses())->orderBy('sort')->get()->keyBy('id');

        $previous = SeatOffer::get(['application_id', 'course_id', 'seat_category', 'status']);
        $excluded = $previous->pluck('application_id')->unique()->flip();
        $held = $previous->whereIn('status', SeatOffer::ACTIVE)->groupBy('course_id')
            ->map(fn ($g) => $g->countBy('seat_category')->all());

        $lists = [];
        $vacant = [];
        $prefs = [];
        $rows = [];
        foreach ($courses as $course) {
            $lists[$course->id] = $this->merit->forCourse($course)['ranked'];
            foreach (self::seats($course) as $cat => $n) {
                $vacant[$course->id][$cat] = max(0, $n - ($held[$course->id][$cat] ?? 0));
            }
            foreach ($lists[$course->id] as $r) {
                $appId = $r['app']->id;
                if (! isset($excluded[$appId])) {
                    $prefs[$appId][$course->id] = $r['choice']->du_preference;
                    $rows[$course->id][$appId] = $r;
                }
            }
        }

        // Student-proposing deferred acceptance.
        $order = [];
        foreach ($prefs as $appId => $p) {
            asort($p);
            $order[$appId] = array_keys($p);
        }
        $next = array_fill_keys(array_keys($order), 0);
        $holding = [];
        $free = array_keys($order);
        while ($free) {
            $appId = array_pop($free);
            if ($next[$appId] >= count($order[$appId])) {
                continue;
            }
            $courseId = $order[$appId][$next[$appId]++];
            $holding[$courseId][] = $appId;
            [$keep, $reject] = $this->fill($rows[$courseId], $holding[$courseId], $vacant[$courseId] ?? []);
            $holding[$courseId] = array_keys($keep);
            array_push($free, ...$reject);
        }

        $assigned = [];
        foreach ($holding as $courseId => $ids) {
            [$keep] = $this->fill($rows[$courseId], $ids, $vacant[$courseId] ?? []);
            foreach ($keep as $appId => $bucket) {
                $assigned[$appId] = ['course_id' => $courseId, 'bucket' => $bucket, 'row' => $rows[$courseId][$appId]];
            }
        }

        $remaining = $vacant;
        foreach ($assigned as $a) {
            $remaining[$a['course_id']][$a['bucket']]--;
        }

        return ['courses' => $courses, 'assigned' => $assigned, 'lists' => $lists, 'vacant' => $remaining];
    }

    /**
     * Pick who keeps a seat among $ids: General seats by overall rank, then each category bucket.
     *
     * @return array{0: array<int,string>, 1: int[]} [appId => bucket], rejected appIds
     */
    private function fill(array $rows, array $ids, array $capacity): array
    {
        usort($ids, fn ($a, $b) => $rows[$a]['rank'] <=> $rows[$b]['rank']);
        $keep = [];
        foreach ($capacity as $bucket => $cap) {
            foreach ($ids as $id) {
                if ($cap <= 0) {
                    break;
                }
                if (isset($keep[$id]) || ! self::fits($rows[$id], $bucket)) {
                    continue;
                }
                $keep[$id] = $bucket;
                $cap--;
            }
        }

        return [$keep, array_values(array_diff($ids, array_keys($keep)))];
    }

    public static function fits(array $row, string $bucket): bool
    {
        return match ($bucket) {
            'UR' => true,
            'PWBD' => $row['pwbd'],
            default => $row['category'] === $bucket,
        };
    }

    /** Save the previewed round as offers and email the candidates. */
    public function publish(Carbon $deadline, Admin $by): Collection
    {
        if ($deadline->isPast()) {
            throw ValidationException::withMessages(['deadline' => 'The acceptance deadline must be in the future.']);
        }
        $preview = $this->preview();
        if (! $preview['assigned']) {
            throw ValidationException::withMessages(['deadline' => 'There is nobody to offer a seat to in this round.']);
        }

        $offers = DB::transaction(function () use ($preview, $deadline, $by) {
            $round = (int) SeatOffer::max('round') + 1;

            return collect($preview['assigned'])->map(fn ($a) => SeatOffer::create([
                'round' => $round, 'application_id' => $a['row']['app']->id, 'application_course_id' => $a['row']['choice']->id,
                'course_id' => $a['course_id'], 'seat_category' => $a['bucket'], 'merit_rank' => $a['row']['rank'],
                'cuet_part' => $a['row']['cuet'], 'interview_part' => $a['row']['interview'], 'total' => $a['row']['total'],
                'status' => SeatOffer::OFFERED, 'deadline_at' => $deadline, 'created_by' => $by->id,
            ]))->values();
        });

        AuditLog::record('allotment.publish', null, ['round' => $offers->first()->round, 'offers' => $offers->count(), 'deadline' => $deadline->toIso8601String()]);
        foreach ($offers as $o) {
            $o->application->user->notify(new SeatOffered($o));
            $o->forceFill(['emailed_at' => now()])->saveQuietly();
        }

        return $offers;
    }

    public function respond(SeatOffer $offer, bool $accept, ?string $ip): void
    {
        if (! $offer->canAccept()) {
            throw ValidationException::withMessages(['offer' => 'This offer can no longer be answered.']);
        }
        $offer->forceFill(['status' => $accept ? SeatOffer::ACCEPTED : SeatOffer::DECLINED, 'responded_at' => now(), 'responded_ip' => $ip])->save();
    }

    /** Mark offers not accepted by their deadline as lapsed. Returns how many lapsed. */
    public function lapse(): int
    {
        return SeatOffer::where('status', SeatOffer::OFFERED)->where('deadline_at', '<=', now())->update(['status' => SeatOffer::LAPSED, 'updated_at' => now()]);
    }
}
