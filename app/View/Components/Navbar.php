<?php

namespace App\View\Components;

use App\Support\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The listing header: TWO bars, not one that adapts. On mobile `.navbar` (logo and
 * icons); from md up `.navbar-desktop` takes over. It also mounts the schedule and
 * location sheets, once, for both bars and for the order bar's "Ver mapa". Port of
 * src/components/Navbar.astro.
 *
 * The icons only exist when they have something to show: an icon that opens an empty
 * sheet is worse than no icon. The data decides, not the screen.
 */
class Navbar extends Component
{
    public bool $hasLocation;

    public bool $hasSchedule;

    public ?string $shift;

    /**
     * @param  'delivery'|'pickup'  $selected  The chosen delivery mode: which estimate the desktop bar reads.
     */
    public function __construct(
        public array $store,
        public ?array $schedule,
        public string $selected = 'delivery',
        public float|int $cartTotal = 0,
        public int $cartCount = 0,
    ) {
        // Address or link: either one makes the sheet worth opening. The short address
        // and the message don't count: they aren't what the sheet is for.
        $this->hasLocation = ($store['location']['address'] ?? '') !== '' || ($store['location']['mapsUrl'] ?? '') !== '';

        // Without shifts the API answers `isOpen: null` and empty days: nothing to show.
        $this->hasSchedule = $schedule !== null && $schedule['days'] !== [];

        $this->shift = $schedule ? Schedule::shiftLabel($schedule) : null;
    }

    public function render(): View
    {
        return view('components.navbar');
    }
}
