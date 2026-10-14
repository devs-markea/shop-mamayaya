<?php

namespace App\View\Components;

use App\Support\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The page footer, mobile and desktop: one markup that CSS lays out. Port of
 * src/components/Footer.astro; the promo phone form is resources/js/components/footer-promo.ts.
 *
 * The three columns' texts are written by the business in the panel (`store.footer`).
 * Each arrives with its text or EMPTY, and empty means "use yours": that's why the usual
 * ones are still written here, next to each `?:`. No text decides whether something is
 * painted: the sheets, the social links and nothing decide, column by column.
 */
class Footer extends Component
{
    public bool $hasAbout;

    public string $aboutTitle;

    public string $locationLabel;

    /** The clock's label: the current shift, the business's fixed text, or closed. */
    public string $shift;

    /** The clock's accessible name, when the label alone doesn't say it opens the schedule. */
    public ?string $clockName;

    public string $followTitle;

    /** @var list<array{network: string, label: string, url: string, text: string, named: bool}> */
    public array $socials;

    public string $promoTitle;

    public string $promoPlaceholder;

    public string $promoButton;

    /** The business's copyright HTML (only <strong>, <em> and <br>, rewritten by the backend). */
    public string $copyright;

    public int $year;

    /**
     * @param  bool  $hasLocation  Whether the page mounted the location sheet: it decides, not the footer.
     * @param  bool  $hasSchedule  The same for the schedule sheet.
     */
    public function __construct(
        public array $store,
        public bool $hasLocation,
        public bool $hasSchedule,
        public ?array $schedule,
    ) {
        $texts = $store['footer'] ?? [];

        // "Conócenos": where the store is and when it opens, the same two sheets as the
        // top bar. Without either, the column falls whole, title included.
        $this->hasAbout = $hasLocation || $hasSchedule;
        $this->aboutTitle = ($texts['about']['title'] ?? '') ?: 'Conócenos';
        $this->locationLabel = ($texts['about']['locationLabel'] ?? '') ?: 'Ubicación';

        // Open, the label is a decision of the panel: `shift` (the current one, computed
        // here) or `custom` (a fixed text). Closed or unknown, `closedLabel` or "Cerrado.".
        $openLabel = ($texts['about']['schedule']['mode'] ?? null) === 'custom' ? $texts['about']['schedule']['label'] : null;

        $this->shift = ($schedule['isOpen'] ?? null)
            ? ($openLabel ?? Schedule::shiftLabel($schedule) ?? 'Abierto.')
            : (($texts['about']['closedLabel'] ?? '') ?: 'Cerrado.');

        // A fixed text that already says it ("Horario") stays as is: "Horario: Horario"
        // explains nothing to anyone.
        $this->clockName = preg_match('/horario/i', $this->shift) ? null : "Horario: {$this->shift}";

        $this->followTitle = ($texts['follow']['title'] ?? '') ?: 'Síguenos';

        // The list as it comes (the order is data). The read text may be the business's
        // own label; when it doesn't name the network, the name is added out of sight,
        // since the glyph is aria-hidden.
        $this->socials = array_map(function (array $link) use ($texts) {
            $text = ($texts['follow']['labels'][$link['network']] ?? '') ?: $link['label'];

            return [...$link, 'text' => $text, 'named' => str_contains(mb_strtolower($text), mb_strtolower($link['label']))];
        }, $store['socialLinks'] ?? []);

        $this->promoTitle = ($texts['promo']['title'] ?? '') ?: '¿Quieres conocer todas nuestras promociones?';
        $this->promoPlaceholder = ($texts['promo']['placeholder'] ?? '') ?: 'Tu número de WhatsApp';
        $this->promoButton = ($texts['promo']['buttonLabel'] ?? '') ?: 'Registrarme';

        $this->copyright = $texts['copyright'] ?? '';
        $this->year = Schedule::storeYear();
    }

    public function render(): View
    {
        return view('components.footer');
    }
}
