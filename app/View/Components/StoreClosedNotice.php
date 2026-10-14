<?php

namespace App\View\Components;

use App\Support\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The closed-store card: why you can't order. Painted by the screens that need it (the
 * home page and the cart), never the layout; whoever mounts it checks
 * Schedule::isClosed() first and makes sure the schedule sheet is on the page. Port of
 * src/components/StoreClosedNotice.astro.
 *
 * Three sentences and a way out: when it comes back, what you can do meanwhile, which
 * day it opens (only when it's not today: the title already reads as today) and the
 * full schedule.
 */
class StoreClosedNotice extends Component
{
    /** Non-breaking space: "13:00 h." must not leave the "h." alone on the next line. */
    private const NBSP = "\u{00A0}";

    public string $title;

    public ?string $opening;

    public bool $hasSchedule;

    public function __construct(public array $schedule)
    {
        $openingTime = $schedule['opensAt']['time'] ?? null;
        $openingDay = Schedule::nextOpeningDay($schedule);

        // Without a next opening no hour is promised.
        $this->title = $openingTime ? "Regresamos a las {$openingTime}".self::NBSP.'h.' : 'Estamos cerrados';

        $this->opening = $openingTime && $openingDay && $openingDay !== 'hoy'
            ? "Abrimos {$openingDay} a las {$openingTime}".self::NBSP.'h.'
            : null;

        // Without days to show, the schedule sheet would open blank: better no button.
        $this->hasSchedule = $schedule['days'] !== [];
    }

    public function render(): View
    {
        return view('components.store-closed-notice');
    }
}
