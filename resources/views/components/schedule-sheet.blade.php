{{--
    The store schedule, behind the clock icon. The API sends the seven days apart, the
    closed ones with an empty list, and they're painted as they come: grouping them
    ("Lunes a domingo") is an interpretation you can't undo. Port of
    src/components/ScheduleSheet.astro.
--}}
@props(['schedule'])

<x-sheet id="sheet-schedule" title="Horario">
    <dl class="schedule">
        @foreach ($schedule['days'] as $day)
            <div class="schedule__row">
                <dt class="schedule__day">{{ $day['name'] }}</dt>

                <dd @class(['schedule__shifts', 'schedule__shifts--closed' => $day['shifts'] === []])>{{ $day['shifts'] !== [] ? \App\Support\Schedule::formatShifts($day) : 'Cerrado' }}</dd>
            </div>
        @endforeach
    </dl>
</x-sheet>
