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
 *   from candidates of that category, then CST (Christian Scheduled Tribe) from CST applicants, in the order
 *   the seats are listed for the course, then PwBD supernumerary seats.
 * - A candidate who is in the merit list of several courses gets the seat in the course they put first
 *   in their DU preferences, among those where their merit is good enough (student-proposing deferred acceptance).
 *   They are shown in the other courses as "Already selected – first preference".
 * - A candidate holding an offer (offered or accepted) is considered again in later rounds, but only for
 *   courses they ranked higher in their DU preferences. If they move up, the old seat is released for others;
 *   if the old seat was already accepted, the new one is confirmed without a new acceptance.
 * - Candidates who declined an offer or let one lapse are not considered again.
 */
class AllotmentService
{
    public function __construct(private MeritService $merit) {}

    /** Default PwBD supernumerary seats as a percentage of a course's intake (used when no number is set for the course). */
    public static function pwbdPercent(): float
    {
        return (float) \App\Models\Setting::get('seats.pwbd_percent', 5);
    }

    /** PwBD supernumerary seats for an intake: the number set for the course, or the default percentage rounded up. */
    public static function pwbdSeats(Course $course): int
    {
        $set = $course->seats['PWBD'] ?? null;
        if ($set !== null && $set !== '') {
            return max(0, (int) $set);
        }
        $intake = array_sum(array_map('intval', array_diff_key($course->seats ?? [], ['PWBD' => 1])));

        return (int) ceil($intake * self::pwbdPercent() / 100);
    }

    /**
     * Seats per category for a course: General first, then the categories in order, then PwBD.
     * PwBD seats are supernumerary (extra, on top of the intake) and go only to PwBD candidates not already selected.
     */
    public static function seats(Course $course): array
    {
        $seats = array_filter(array_map('intval', array_diff_key($course->seats ?? [], ['PWBD' => 1])), fn ($n) => $n > 0);
        uksort($seats, fn ($a, $b) => ($b === 'UR') <=> ($a === 'UR'));
        if ($seats && ($pwbd = self::pwbdSeats($course)) > 0) {
            $seats['PWBD'] = $pwbd;
        }

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

        $previous = SeatOffer::with('choice')->get();
        $active = $previous->whereIn('status', SeatOffer::ACTIVE)->keyBy('application_id');
        // Declined or lapsed (and holding nothing now): out of later rounds.
        $excluded = $previous->pluck('application_id')->unique()->reject(fn ($id) => $active->has($id))->flip();

        $lists = [];
        foreach ($courses as $course) {
            $lists[$course->id] = $this->merit->forCourse($course)['ranked'];
        }

        // Offer holders take part only if their course is in this round; they can move up, never down.
        $holders = $active->filter(fn ($o) => isset($lists[$o->course_id]) && $lists[$o->course_id]->contains(fn ($r) => $r['app']->id === $o->application_id));
        $fixed = $active->diffKeys($holders)->groupBy('course_id')->map(fn ($g) => $g->countBy('seat_category')->all());

        $capacity = [];
        $prefs = [];
        $rows = [];
        foreach ($courses as $course) {
            foreach (self::seats($course) as $cat => $n) {
                $capacity[$course->id][$cat] = max(0, $n - ($fixed[$course->id][$cat] ?? 0));
            }
            foreach ($lists[$course->id] as $r) {
                $appId = $r['app']->id;
                $pref = $r['choice']->du_preference;
                if (isset($excluded[$appId]) || ($active->has($appId) && ! $holders->has($appId))) {
                    continue;
                }
                if (($h = $holders[$appId] ?? null) && $pref > $h->choice->du_preference) {
                    continue; // lower than the seat they already hold
                }
                $prefs[$appId][$course->id] = $pref;
                $rows[$course->id][$appId] = $r;
            }
        }
        $guaranteed = $holders->groupBy('course_id')->map(fn ($g) => $g->mapWithKeys(fn ($o) => [$o->application_id => $o->seat_category])->all())->all();

        // Student-proposing deferred acceptance. A holder always keeps their current seat if nothing better comes up.
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
            [$keep, $reject] = $this->fill($rows[$courseId], $holding[$courseId], $capacity[$courseId] ?? [], $guaranteed[$courseId] ?? []);
            $holding[$courseId] = array_keys($keep);
            array_push($free, ...$reject);
        }

        $assigned = [];
        $remaining = $capacity;
        foreach ($holding as $courseId => $ids) {
            [$keep] = $this->fill($rows[$courseId], $ids, $capacity[$courseId] ?? [], $guaranteed[$courseId] ?? []);
            foreach ($keep as $appId => $bucket) {
                $remaining[$courseId][$bucket] = ($remaining[$courseId][$bucket] ?? 0) - 1;
                $from = $holders[$appId] ?? null;
                if ($from && $from->course_id === $courseId) {
                    continue; // stays where they are
                }
                $assigned[$appId] = ['course_id' => $courseId, 'bucket' => $bucket, 'row' => $rows[$courseId][$appId], 'from' => $from];
            }
        }
        $remaining = array_map(fn ($c) => array_map(fn ($n) => max(0, $n), $c), $remaining);

        return ['courses' => $courses, 'assigned' => $assigned, 'lists' => $lists, 'vacant' => $remaining];
    }

    /**
     * Pick who keeps a seat among $ids: candidates already holding a seat in this course keep it,
     * then General seats by overall rank, then each category bucket.
     *
     * @return array{0: array<int,string>, 1: int[]} [appId => bucket], rejected appIds
     */
    private function fill(array $rows, array $ids, array $capacity, array $guaranteed = []): array
    {
        usort($ids, fn ($a, $b) => $rows[$a]['rank'] <=> $rows[$b]['rank']);
        $keep = [];
        foreach ($ids as $id) {
            if (isset($guaranteed[$id])) {
                $keep[$id] = $guaranteed[$id];
                $capacity[$guaranteed[$id]] = ($capacity[$guaranteed[$id]] ?? 0) - 1;
            }
        }
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
            'CST' => $row['cst'],
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

            return collect($preview['assigned'])->map(function ($a) use ($round, $deadline, $by) {
                $from = $a['from'];
                // An upgrade replaces the old seat. If the old seat was already accepted, the new one is confirmed straight away.
                $confirmed = $from?->status === SeatOffer::ACCEPTED;
                $offer = SeatOffer::create([
                    'round' => $round, 'application_id' => $a['row']['app']->id, 'application_course_id' => $a['row']['choice']->id,
                    'course_id' => $a['course_id'], 'seat_category' => $a['bucket'], 'merit_rank' => $a['row']['rank'],
                    'cuet_part' => $a['row']['cuet'], 'interview_part' => $a['row']['interview'], 'total' => $a['row']['total'],
                    'status' => $confirmed ? SeatOffer::ACCEPTED : SeatOffer::OFFERED, 'deadline_at' => $deadline, 'created_by' => $by->id,
                ]);
                if ($from) {
                    $offer->forceFill(['upgraded_from_id' => $from->id, 'responded_at' => $confirmed ? now() : null])->save();
                    $from->forceFill(['status' => SeatOffer::UPGRADED])->save();
                }

                return $offer;
            })->values();
        });

        AuditLog::record('allotment.publish', null, ['round' => $offers->first()->round, 'offers' => $offers->count(), 'upgrades' => $offers->whereNotNull('upgraded_from_id')->count(), 'deadline' => $deadline->toIso8601String()]);
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
