<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The store schedule: pure functions over `GET /api/store/schedule`. Port of
 * src/lib/schedule.ts.
 *
 * The endpoint publishes FACTS (`isOpen`, the current shift's closing time, the next
 * opening and the seven days with their shifts); wording them is presentation, and
 * presentation belongs to the store. Everything is resolved in the store's time zone:
 * the visitor's clock is not Cancún's, and "opens at 09:00" being today or tomorrow
 * depends on it.
 *
 * Shapes (arrays, camelCase like the API):
 *
 *   schedule  {isOpen: ?bool, closesAt: ?string, opensAt: ?{dayOfWeek, time, inMinutes},
 *              days: list<day>, legacyLabel?: string}
 *   day       {dayOfWeek?: int, name: string, shifts: list<{start, end, crossesMidnight?}>}
 */
final class Schedule
{
    /** Monday = 1 … Sunday = 7, as ISO-8601. */
    private const WEEKDAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    private const WEEK_MINUTES = 7 * 1440;

    /** Non-breaking space: "23:00 h." must not leave the "h." alone on the next line. */
    private const NBSP = "\u{00A0}";

    /** The year IN THE STORE, for the footer's copyright. The server runs in UTC. */
    public static function storeYear(?CarbonInterface $now = null): int
    {
        return self::local($now)->year;
    }

    /**
     * Normalizes the response to the current contract. The older one (`label` and
     * `hours`, with days already grouped and shifts as "09:00-23:00") is still
     * translated so the schedule doesn't vanish while it's around.
     */
    public static function normalize(array $raw): array
    {
        if (isset($raw['days']) && is_array($raw['days'])) {
            return [
                'isOpen' => is_bool($raw['isOpen'] ?? null) ? $raw['isOpen'] : null,
                'closesAt' => $raw['closesAt'] ?? null,
                'opensAt' => is_array($raw['opensAt'] ?? null) ? $raw['opensAt'] : null,
                'days' => array_values(array_map(fn ($day) => [
                    ...$day,
                    'name' => (string) ($day['name'] ?? ''),
                    'shifts' => array_values($day['shifts'] ?? []),
                ], $raw['days'])),
            ];
        }

        $days = array_map(fn (array $group) => [
            'name' => (string) ($group['days'] ?? ''),
            'shifts' => array_map(function (string $shift) {
                [$start, $end] = array_pad(explode('-', $shift, 2), 2, '');

                return ['start' => $start, 'end' => $end];
            }, $group['shifts'] ?? []),
        ], $raw['hours'] ?? []);

        return [
            'isOpen' => is_bool($raw['isOpen'] ?? null) ? $raw['isOpen'] : null,
            'closesAt' => null,
            'opensAt' => null,
            'days' => array_values($days),
            ...(! empty($raw['label']) ? ['legacyLabel' => $raw['label']] : []),
        ];
    }

    /**
     * The store is closed RIGHT NOW. `null` (no schedule, or one without shifts) is "we
     * don't know", which is not closed.
     */
    public static function isClosed(?array $schedule): bool
    {
        return ($schedule['isOpen'] ?? null) === false;
    }

    /**
     * Open or closed now, computed here from the RANGES, with the closing time, the next
     * opening and the moment it changes. Null when the days can't be read (no
     * `dayOfWeek`: the old API shape).
     *
     * The rule is the panel's and the backend's `ScheduleAvailability`: open inside any
     * range of the day; the start counts and the end doesn't; `end <= start` closes the
     * next day and `end === start` is the 24 h shift; no range all week means `null`.
     *
     * @return array{isOpen: ?bool, closesAt: ?string, opensAt: ?array, nextChangeAt: ?CarbonImmutable}|null
     */
    public static function resolve(array $days, ?CarbonInterface $now = null): ?array
    {
        $ranges = self::weekRanges($days);

        if ($ranges === null) {
            return null;
        }

        if ($ranges === []) {
            return ['isOpen' => null, 'closesAt' => null, 'opensAt' => null, 'nextChangeAt' => null];
        }

        $now = CarbonImmutable::instance($now ?? now());
        $minute = self::minuteOfWeek($now);
        $covering = array_values(array_filter($ranges, fn ($range) => self::covers($range, $minute)));

        $nextChange = min(array_merge(...array_map(fn ($range) => [
            self::minutesUntil($range['start'] % self::WEEK_MINUTES, $minute),
            self::minutesUntil($range['end'] % self::WEEK_MINUTES, $minute),
        ], $ranges)));

        // Anchored to the current minute: borders are on the minute, at second 0.
        $nextChangeAt = CarbonImmutable::createFromTimestamp(
            intdiv($now->getTimestamp(), 60) * 60 + $nextChange * 60,
        );

        if ($covering !== []) {
            // With overlapping ranges the one that closes latest wins; 24 h never closes.
            $closesAt = self::hasContinuous($covering)
                ? null
                : self::latest($covering)['closesAt'];

            return ['isOpen' => true, 'closesAt' => $closesAt, 'opensAt' => null, 'nextChangeAt' => $nextChangeAt];
        }

        $next = array_reduce($ranges, fn ($soonest, $range) => $soonest === null
            || self::minutesUntil($range['start'] % self::WEEK_MINUTES, $minute)
                < self::minutesUntil($soonest['start'] % self::WEEK_MINUTES, $minute)
            ? $range
            : $soonest);

        return [
            'isOpen' => false,
            'closesAt' => null,
            'opensAt' => [
                'dayOfWeek' => $next['dayOfWeek'],
                'time' => $next['startTime'],
                'inMinutes' => self::minutesUntil($next['start'] % self::WEEK_MINUTES, $minute),
            ],
            'nextChangeAt' => $nextChangeAt,
        ];
    }

    /**
     * Where the server's state and the computed one disagree, or null if they agree.
     * `opensAt` is only compared while closed (the backend also sends it while open), and
     * `inMinutes` gets one minute of slack: the two clocks don't count the same second.
     */
    public static function mismatch(array $server, array $resolved): ?string
    {
        $show = fn ($value) => json_encode($value);

        if (($server['isOpen'] ?? null) !== $resolved['isOpen']) {
            return "isOpen: servidor {$show($server['isOpen'] ?? null)}, calculado {$show($resolved['isOpen'])}";
        }

        if (($server['isOpen'] ?? null) === true && ($server['closesAt'] ?? null) !== ($resolved['closesAt'] ?? null)) {
            return "closesAt: servidor {$show($server['closesAt'] ?? null)}, calculado {$show($resolved['closesAt'])}";
        }

        if (($server['isOpen'] ?? null) === false) {
            $mine = $resolved['opensAt'];
            $theirs = $server['opensAt'] ?? null;

            if (! $mine || ! $theirs) {
                return "opensAt: servidor {$show($theirs)}, calculado {$show($mine)}";
            }

            if ($mine['dayOfWeek'] !== ($theirs['dayOfWeek'] ?? null) || $mine['time'] !== ($theirs['time'] ?? null)) {
                return "opensAt: servidor {$theirs['dayOfWeek']} {$theirs['time']}, calculado {$mine['dayOfWeek']} {$mine['time']}";
            }

            if (abs($mine['inMinutes'] - (int) ($theirs['inMinutes'] ?? 0)) > 1) {
                return "opensAt.inMinutes: servidor {$theirs['inMinutes']}, calculado {$mine['inMinutes']}";
            }
        }

        return null;
    }

    /**
     * When it opens again, in words: "hoy", "mañana", "el lunes". Null when there is no
     * next opening. The minutes decide the day, not the name: a shift on the same weekday
     * may be seven days away.
     */
    public static function nextOpeningDay(array $schedule, ?CarbonInterface $now = null): ?string
    {
        $opensAt = $schedule['opensAt'] ?? null;

        if (! $opensAt) {
            return null;
        }

        $local = self::local($now);
        $offset = intdiv($local->hour * 60 + $local->minute + (int) $opensAt['inMinutes'], 1440);

        return match ($offset) {
            0 => 'hoy',
            1 => 'mañana',
            default => 'el '.(self::WEEKDAYS[((int) $opensAt['dayOfWeek']) - 1] ?? 'próximo día'),
        };
    }

    /**
     * The state in one sentence, or null when unknown.
     *
     *   Abierto hasta las 23:00 · Abierto las 24 horas · Cerrado · Abre mañana a las 09:00
     */
    public static function label(array $schedule, ?CarbonInterface $now = null): ?string
    {
        if (! empty($schedule['legacyLabel'])) {
            return $schedule['legacyLabel'];
        }

        if ($schedule['isOpen'] === null) {
            return null;
        }

        if ($schedule['isOpen']) {
            return $schedule['closesAt'] ? "Abierto hasta las {$schedule['closesAt']}" : 'Abierto las 24 horas';
        }

        if (! $schedule['opensAt']) {
            return 'Cerrado';
        }

        return 'Cerrado · Abre '.self::nextOpeningDay($schedule, $now)." a las {$schedule['opensAt']['time']}";
    }

    /**
     * The home banner's status: `open`, `closed-now` (opens again TODAY) or `closed-today`.
     * Null when unknown. "Today" is decided by the next opening, in the store's zone.
     */
    public static function status(array $schedule, ?CarbonInterface $now = null): ?string
    {
        if ($schedule['isOpen'] === null) {
            return null;
        }

        if ($schedule['isOpen']) {
            return 'open';
        }

        return self::nextOpeningDay($schedule, $now) === 'hoy' ? 'closed-now' : 'closed-today';
    }

    /**
     * The current shift in 24 h: the desktop bar's clock and the mobile category sheet.
     * Null when unknown.
     *
     *   12:00 - 23:00 h. · Abierto las 24 horas · Cerrado · Abre mañana a las 09:00 h.
     *
     * The span comes from the RANGES resolved now; with overlapping shifts, the one that
     * closes latest. If the ranges can't be read, only the closing time is known.
     */
    public static function shiftLabel(array $schedule, ?CarbonInterface $now = null): ?string
    {
        if ($schedule['isOpen'] === null) {
            return null;
        }

        if (! $schedule['isOpen']) {
            if (! $schedule['opensAt']) {
                return 'Cerrado';
            }

            return 'Cerrado · Abre '.self::nextOpeningDay($schedule, $now)
                ." a las {$schedule['opensAt']['time']}".self::NBSP.'h.';
        }

        $minute = self::minuteOfWeek(CarbonImmutable::instance($now ?? now()));
        $covering = array_values(array_filter(
            self::weekRanges($schedule['days']) ?? [],
            fn ($range) => self::covers($range, $minute),
        ));

        if (self::hasContinuous($covering)) {
            return 'Abierto las 24 horas';
        }

        if ($covering !== []) {
            $shift = self::latest($covering);

            return "{$shift['startTime']} - {$shift['closesAt']}".self::NBSP.'h.';
        }

        return $schedule['closesAt'] ? "Abierto hasta las {$schedule['closesAt']}".self::NBSP.'h.' : null;
    }

    /**
     * A day's shifts, ready to paint. Empty string when closed: the caller labels it.
     * `+1 dia` flags shifts that cross midnight, which would otherwise read as a typo.
     */
    public static function formatShifts(array $day): string
    {
        return implode(' · ', array_map(function (array $shift) {
            if ($shift['start'] === $shift['end']) {
                return '24 horas';
            }

            return "{$shift['start']} a {$shift['end']}".self::NBSP.'h.'
                .(! empty($shift['crossesMidnight']) ? ' +1 dia' : '');
        }, $day['shifts']));
    }

    /**
     * The seven days' ranges placed on the week, in minutes from Monday 00:00. Null when
     * a day has no valid `dayOfWeek`.
     *
     * @return list<array{start: int, end: int, dayOfWeek: int, startTime: string, closesAt: ?string}>|null
     */
    private static function weekRanges(array $days): ?array
    {
        $ranges = [];

        foreach ($days as $day) {
            $dayOfWeek = $day['dayOfWeek'] ?? null;

            if (! is_int($dayOfWeek) || $dayOfWeek < 1 || $dayOfWeek > 7) {
                return null;
            }

            $base = ($dayOfWeek - 1) * 1440;

            foreach ($day['shifts'] ?? [] as $shift) {
                $start = self::parseTime($shift['start'] ?? null);
                $end = self::parseTime($shift['end'] ?? null);

                if ($start === null || $end === null) {
                    continue;
                }

                $length = $end > $start ? $end - $start : ($end === $start ? 1440 : 1440 - $start + $end);

                $ranges[] = [
                    'start' => $base + $start,
                    'end' => $base + $start + $length,
                    'dayOfWeek' => $dayOfWeek,
                    'startTime' => $shift['start'],
                    'closesAt' => $end === $start ? null : $shift['end'],
                ];
            }
        }

        return $ranges;
    }

    /** "18:30" in minutes, or null if it isn't a time. */
    private static function parseTime(?string $value): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $value), $match)) {
            return null;
        }

        [$hour, $minute] = [(int) $match[1], (int) $match[2]];

        return $hour > 23 || $minute > 59 ? null : $hour * 60 + $minute;
    }

    /** Whether the minute falls inside the range: the start counts, the end doesn't. */
    private static function covers(array $range, int $minute): bool
    {
        return ($minute >= $range['start'] && $minute < $range['end'])
            // The week wraps around: Sunday's range that crosses midnight covers Monday.
            || ($minute + self::WEEK_MINUTES >= $range['start'] && $minute + self::WEEK_MINUTES < $range['end']);
    }

    /** Minutes left until that minute of the week, wrapping around if needed. */
    private static function minutesUntil(int $minute, int $from): int
    {
        $delta = ((($minute - $from) % self::WEEK_MINUTES) + self::WEEK_MINUTES) % self::WEEK_MINUTES;

        return $delta === 0 ? self::WEEK_MINUTES : $delta;
    }

    /** The minute of the week in the store: 0 is Monday 00:00. */
    private static function minuteOfWeek(CarbonInterface $now): int
    {
        $local = self::local($now);

        return ($local->dayOfWeekIso - 1) * 1440 + $local->hour * 60 + $local->minute;
    }

    /** Whether any of the ranges is the continuous 24 h shift, which never closes. */
    private static function hasContinuous(array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($range['closesAt'] === null) {
                return true;
            }
        }

        return false;
    }

    private static function latest(array $ranges): array
    {
        return array_reduce($ranges, fn ($latest, $range) => $latest === null || $range['end'] > $latest['end'] ? $range : $latest);
    }

    private static function local(?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->setTimezone(config('shop.timezone', 'America/Cancun'));
    }
}
