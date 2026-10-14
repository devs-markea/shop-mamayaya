<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogService;
use App\Services\ShopApi\ShopApiException;
use App\Services\Store\StoreContext;
use App\Support\ErrorCodes;
use App\Support\Schedule;
use App\Support\Throttle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The product page, `/mamayaya/{slug}`. Port of src/pages/mamayaya/[slug].astro.
 *
 * It is also THE STORE'S 404: the route is a one-segment wildcard, so anything under
 * /mamayaya that isn't a screen of its own lands here and leaves through the "not found"
 * notice (404, or 503 when the API couldn't be asked). The slug goes to the API as is:
 * it's `menus.slug` on both sides.
 *
 * One markup for both screens: on mobile, photo bleeding with the back control on top,
 * information, options and the fixed action bar; on desktop, two columns (photo, then
 * information and the buy row with the unit counter). CSS moves the same elements.
 */
class ProductController extends Controller
{
    private const UNAVAILABLE_NOTICE = [
        'no_price' => 'Este platillo todavia no tiene precio, asi que no se puede pedir.',
        'out_of_schedule' => 'Fuera del horario. Vuelve más tarde.',
    ];

    public function __construct(
        private readonly StoreContext $context,
        private readonly CatalogService $catalog,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $context = $this->context->forRequest($request);

        $product = null;
        $unreachable = false;

        // The notice's title and body change together across the three cases: per-minute
        // cap, breakdown and dish that doesn't exist. A 404 is reported too ("they sent me
        // a link and it doesn't open"), so it has its code as well.
        $noticeTitle = 'Platillo no encontrado';
        $noticeBody = 'Puede que ya no este en el menu.';
        $noticeRetry = 'none';
        $noticeCode = 'SF-'.ErrorCodes::DETAIL.'404';

        try {
            $product = $this->catalog->product($slug);
        } catch (Throwable $e) {
            $noticeCode = ErrorCodes::fromException(ErrorCodes::DETAIL, $e);
            $unreachable = true;

            Log::error("[detalle] {$noticeCode} fallo la ficha de \"{$slug}\"", ['error' => $e->getMessage()]);

            // A 429 is the API's shared per-minute cap: waiting is enough, and reloading
            // on its own would spend another token of the same bucket.
            $throttled = $e instanceof ShopApiException && $e->status === 429;

            $noticeRetry = $throttled ? 'manual' : 'auto';

            // ONE call failed; the bar, the footer and the schedule may have loaded fine on
            // this same page. What it can say without being wrong is whose problem it is.
            $noticeTitle = $throttled ? 'El menu esta muy solicitado' : 'No pudimos cargar el platillo';
            $noticeBody = $throttled
                ? Throttle::busyMessage($e->retryAfter)
                : 'El problema es nuestro, no de tu conexion.';
        }

        $variant = $product ? CatalogService::defaultVariant($product) : null;

        $view = view('pages.product', [
            ...$context,
            'product' => $product,
            'groups' => $product ? $this->catalog->optionGroups($product) : [],
            // The photo, resolved ONCE: it decides the two desktop columns, whether the
            // photo block is mounted and whether the mobile back header is needed.
            'image' => $product ? $this->catalog->productImage($product) : null,
            // The total of ONE unit: what the page opens with; order-form.ts takes over.
            'total' => $product ? CatalogService::initialTotal($product) : 0,
            'basePrice' => $product ? (CatalogService::variantPrice($variant) ?? $product['basePrice']) : 0,
            'variantId' => $product && empty($product['hasVariants']) ? ($variant['id'] ?? null) : null,
            // Only the label: the struck-through and final prices go PER VARIANT in the
            // selector, and the amount to pay is in the add button.
            'promo' => $product ? CatalogService::promotionView($product) : null,
            'unavailableNotice' => $product && ! $product['available']
                ? (self::UNAVAILABLE_NOTICE[$product['unavailableReason'] ?? ''] ?? 'No se puede pedir ahora mismo.')
                : null,
            'noticeTitle' => $noticeTitle,
            'noticeBody' => $noticeBody,
            'noticeRetry' => $noticeRetry,
            'noticeCode' => $noticeCode,
            'shift' => $context['schedule'] ? Schedule::shiftLabel($context['schedule']) : null,
        ]);

        // 404 also for what is unpublished, uncategorized or renamed: from outside it must
        // not be possible to tell that it exists but isn't in the store.
        return response($view, $product ? 200 : ($unreachable ? 503 : 404));
    }
}
