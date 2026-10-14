<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogService;
use App\Services\ShopApi\ShopApiException;
use App\Services\Store\StoreContext;
use App\Support\ErrorCodes;
use App\Support\Price;
use App\Support\Schedule;
use App\Support\Throttle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The home page: the menu, in sections. Port of src/pages/mamayaya/index.astro.
 *
 * Rendered on every request, never cached as a page: `available` depends on the
 * schedule resolved now, and the order bar reads the session's delivery mode.
 */
class MenuController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CatalogService $catalog,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->context->forRequest($request);
        ['store' => $store, 'schedule' => $schedule] = $context;

        // Without a catalog the page stays up (header and order bar) with a notice in
        // place of the grid: a blank home page is worse than one that says what happened.
        $products = [];
        $catalogNotice = null;
        $catalogCode = null;
        $catalogRetry = 'auto';

        try {
            $products = $this->catalog->products();
        } catch (Throwable $e) {
            // The same code goes to the log and under the notice: a buyer's screenshot
            // with "SF-M502" is searched literally. A failure that reaches here already
            // failed up to three times in the client.
            $catalogCode = ErrorCodes::fromException(ErrorCodes::MENU, $e);

            Log::error("[catalogo] {$catalogCode} fallo GET /api/products", ['error' => $e->getMessage()]);

            // A 429 isn't a breakdown but a per-minute cap: it says so, and it doesn't
            // reload on its own (that would spend another token of the same bucket).
            $throttled = $e instanceof ShopApiException && $e->status === 429;

            $catalogRetry = $throttled ? 'manual' : 'auto';
            $catalogNotice = $throttled
                ? Throttle::busyMessage($e->retryAfter)
                : 'No pudimos cargar el menu en este momento.';
        }

        return view('pages.menu', [
            ...$context,
            'sections' => $this->catalog->menuSections($products, $store['categories'], $store['home']['featured']),
            'catalogNotice' => $catalogNotice,
            'catalogCode' => $catalogCode,
            'catalogRetry' => $catalogRetry,
            'shippingLabel' => $this->shippingLabel($store['delivery']['freeShipping'] ?? null),
            'banner' => $store['home']['banner'],
            'sidebarHeading' => $store['home']['panel']['presentation'] ?: $store['name'],
            'shift' => $schedule ? Schedule::shiftLabel($schedule) : null,
            'status' => $schedule ? Schedule::status($schedule) : null,
        ]);
    }

    /**
     * The shipping label is the store's to word, from the mode. With `none` it isn't
     * painted: promising free shipping the business turned off is worse than nothing.
     */
    private function shippingLabel(?array $freeShipping): ?string
    {
        return match ($freeShipping['mode'] ?? null) {
            'always' => 'Envio gratis',
            'threshold' => ! empty($freeShipping['threshold']) ? 'Envio gratis desde '.Price::format($freeShipping['threshold']) : null,
            default => null,
        };
    }
}
