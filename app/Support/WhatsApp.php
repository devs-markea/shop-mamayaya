<?php

namespace App\Support;

/**
 * WhatsApp: the chat links and the closing screens' message. Port of src/lib/whatsapp.ts.
 *
 * The business writes the template in its panel and the store fills it with the order it
 * just reread; the template travels in GET /api/store. THE REFERENCE IS THE ERP: the panel
 * writes the same order from `App\Support\WhatsappMessage`, and buyer and business read
 * both texts in the same conversation. That's why some literals here carry accents
 * ("envío") while the rest of the store writes without them.
 */
final class WhatsApp
{
    /**
     * The twenty-three markers. The list decides what a KNOWN marker is: what isn't here
     * is left literal (rule 1), and what is here but has no value takes its line (rule 2).
     */
    private const MARKERS = [
        // The order
        'folio', 'estado', 'notas', 'platillos',
        // Who placed it
        'nombre', 'celular',
        // Where it goes
        'entrega', 'calle', 'numero', 'colonia', 'entrecalles', 'referencias', 'ubicacion', 'direccion',
        // Where to pick it up: the only two that DON'T come from the order
        'sucursal', 'mapa',
        // What it adds up to
        'productos', 'descuento', 'envio', 'propina', 'total', 'metodo',
        // Where to check it: the store's too
        'enlace',
    ];

    /**
     * The bullet of the option lines: `·` U+00B7. Not a style choice: `‣` reaches the chat
     * as an empty box on Android, and `-`/`*` at the start of a line become a list.
     */
    private const BULLET = '·';

    /** What's written where a delivery order's unquoted shipping should go. Shouted on purpose. */
    private const SHIPPING_PENDING = "\u{1F6A8} Por definir \u{1F6A8}";

    /** JavaScript's `\s`: ASCII whitespace plus the Unicode spaces (the regexes here must agree). */
    private const JS_SPACE = '\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    /**
     * A wa.me chat link, with the message already written when given one. Null when there
     * is no phone: callers leave their button inert instead of opening an empty chat.
     *
     * encodeURIComponent() and never a form encoding: templates start with `#{folio}` and
     * carry `&`, and the message must reach WhatsApp whole. It also matches what the
     * browser writes for the same message.
     */
    public static function url(?string $phone, string $message = ''): ?string
    {
        $digits = self::normalizePhone((string) $phone);

        if ($digits === '') {
            return null;
        }

        $chat = "https://wa.me/{$digits}";

        return $message !== '' ? "{$chat}?text=".Js::encodeURIComponent($message) : $chat;
    }

    /**
     * Digits only, E.164 without the `+`. Drops the `1` of old Mexican mobile dialing
     * (`5219987566999` -> `529987566999`) so the fallback and the API links match.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return preg_match('/^521\d{10}$/', $digits) ? '52'.substr($digits, 3) : $digits;
    }

    /**
     * An order's template: its delivery mode and its payment method. `pickup` only carries
     * what changes (today, cash), so the read falls back to `delivery`.
     */
    public static function pickTemplate(?array $templates, string $deliveryType, string $paymentMethod): ?string
    {
        return $templates[$deliveryType][$paymentMethod] ?? $templates['delivery'][$paymentMethod] ?? null;
    }

    /**
     * Fills the template, line by line, with the four rules:
     *
     *   1  UNKNOWN marker            left literal, so someone notices and fixes the panel
     *   2  known marker WITHOUT value the whole line goes... unless another marker of the
     *                                same line did bring a value
     *   3  marker in CAPITALS        the value comes out in capitals
     *   4  clean-up of leftovers     on the line that is sent
     *
     * @param  array<string, string>  $values
     */
    public static function fillMessage(string $template, array $values): string
    {
        $lines = [];

        foreach (explode("\n", $template) as $line) {
            $filled = self::fillLine($line, $values);

            if ($filled !== null) {
                $lines[] = $filled;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The amount as the ERP writes it: `$547`, `$1,190`, `$119.50`. Not Price::format(),
     * which always writes cents: here they only show up when they exist.
     */
    public static function formatMessagePrice(mixed $value): string
    {
        if (is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value)) {
            $formatted = '$'.number_format(abs((float) $value), 0, '.', ',');

            return $value < 0 ? "-{$formatted}" : $formatted;
        }

        return Price::format((float) $value);
    }

    /**
     * The twenty-three values of a message. The whole field-by-field mapping lives here so
     * every closing screen sends exactly the same message.
     *
     * @param  array{items?: array, location?: array, publicUrl?: ?string}  $context
     * @return array<string, string>
     */
    public static function orderMessageValues(array $order, array $context = []): array
    {
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $location = is_array($context['location'] ?? null) ? $context['location'] : [];

        // For pickup the seven address markers go empty EVEN IF the order carries a frozen
        // one. `{entrega}` isn't emptied: it's the marker that says which mode it is.
        $pickup = ($order['deliveryType'] ?? null) === 'pickup';
        $address = $pickup ? [] : $customer;

        [$envio, $totalSuffix] = self::resolveShipping($order);

        $string = fn (array $from, string $key) => is_string($from[$key] ?? null) ? $from[$key] : '';

        return [
            // The order
            'folio' => Checkout::orderFolio($order),
            'estado' => (string) ($order['statusLabel'] ?? ''),
            'notas' => (string) ($order['notes'] ?? ''),
            'platillos' => self::composeItems($order['items'] ?? [], $context['items'] ?? []),

            // Who placed it
            'nombre' => $string($customer, 'name'),
            'celular' => self::present($customer['phone'] ?? null) ? self::readablePhone((string) $customer['phone']) : '',

            // Where it goes
            'entrega' => $pickup ? 'Recoger en sucursal' : 'Entrega a domicilio',
            'calle' => $string($address, 'street'),
            'numero' => $string($address, 'exteriorNumber'),
            'colonia' => $string($address, 'neighborhood'),
            'entrecalles' => $string($address, 'crossStreets'),
            'referencias' => $string($address, 'addressReferences'),
            'ubicacion' => self::present($address['locationUrl'] ?? null) ? self::canonicalMapsUrl((string) $address['locationUrl']) : '',
            'direccion' => self::oneLineAddress($address),

            // Where to pick it up: in EVERY template, it's public data. The short address
            // is the same location written short, so it serves as well.
            'sucursal' => trim($string($location, 'address')) !== '' ? trim($string($location, 'address')) : trim($string($location, 'shortAddress')),
            'mapa' => $string($location, 'mapsUrl'),

            // What it adds up to. The discount only when there is one; the tip always,
            // even at zero: it was asked, and `$0` is the answer.
            'productos' => self::formatMessagePrice($order['subtotal'] ?? 0),
            'descuento' => ($order['discountTotal'] ?? 0) > 0 ? self::formatMessagePrice($order['discountTotal']) : '',
            'envio' => $envio,
            'propina' => self::formatMessagePrice($order['tipTotal'] ?? 0),
            'total' => self::formatMessagePrice($order['total'] ?? 0).$totalSuffix,
            'metodo' => is_string($order['paymentMethod'] ?? null) ? (Checkout::PAYMENT_LABEL[$order['paymentMethod']] ?? '') : '',

            // Where to check it
            'enlace' => self::orderLink($context['publicUrl'] ?? null),
        ];
    }

    /**
     * `{platillos}`: the whole order, one block per dish in the order they were placed,
     * with a blank line between dishes. The amounts are broken down (the base price per
     * unit and each option's), so the lines add up to `{productos}`.
     *
     * @param  array{showVariant?: bool, showCustomizations?: bool}  $options
     */
    public static function composeItems(array $items, array $options = []): string
    {
        return implode("\n\n", array_map(fn (array $item) => self::composeItem($item, $options), $items));
    }

    private static function composeItem(array $item, array $options): string
    {
        $variant = ! empty($options['showVariant']) && self::present($item['variantName'] ?? null) ? " - {$item['variantName']}" : '';

        // The variant's BASE price, per unit (not the line total, which breaks the sum).
        $price = array_key_exists('unitPrice', $item) ? ' ('.self::formatMessagePrice($item['unitPrice']).')' : '';

        $head = ($item['quantity'] ?? '').'x '.($item['name'] ?? '').$variant.$price;

        if (empty($options['showCustomizations'])) {
            return $head;
        }

        $lines = array_map(function (array $option) {
            // The quantity only past one, and the price only when it has one.
            $quantity = ($option['quantity'] ?? 0) > 1 ? "{$option['quantity']}x " : '';
            $cost = ($option['price'] ?? 0) > 0 ? ' ('.self::formatMessagePrice($option['price']).')' : '';

            return self::BULLET.' '.$quantity.($option['label'] ?? '').$cost;
        }, $item['options'] ?? []);

        return implode("\n", [$head, ...$lines]);
    }

    /** A replaced line, or null when it has to go. */
    private static function fillLine(string $line, array $values): ?string
    {
        $known = 0;
        $filled = 0;

        $substituted = preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function (array $match) use ($values, &$known, &$filled) {
            $key = $match[1];
            $name = strtolower($key);

            // Rule 1.
            if (! in_array($name, self::MARKERS, true)) {
                return $match[0];
            }

            $value = (string) ($values[$name] ?? '');
            $known++;

            if ($value !== '') {
                $filled++;
            }

            // Rule 3: written in capitals so the value comes out shouted.
            return preg_match('/[A-Z]/', $key) && $key === strtoupper($key) ? mb_strtoupper($value, 'UTF-8') : $value;
        }, $line);

        // Rule 2. `known` and not the marker count: a line whose only markers are unknown
        // stays, because its literal text is the warning that something needs fixing.
        if ($known > 0 && $filled === 0) {
            return null;
        }

        return self::cleanUp($substituted);
    }

    /**
     * Rule 4, within the line (never over the whole text: `{platillos}` brings its own
     * line breaks): the `#` left without its folio, the space before punctuation, double
     * spaces and the trailing one. The `#` goes first: it leaves the double space behind.
     */
    private static function cleanUp(string $line): string
    {
        $line = preg_replace('/#(?=['.self::JS_SPACE.']|\z)/u', '', $line);
        $line = preg_replace('/ +([,.;:!?])/u', '$1', $line);
        $line = preg_replace('/ {2,}/u', ' ', $line);

        return preg_replace('/[ \t]+\z/u', '', $line);
    }

    /**
     * The only amount that isn't always a figure: a delivery order that couldn't be
     * quoted comes with `shippingTotal: 0`, and that zero is a gap, not free. What tells
     * them apart is the `shipping` block, which only travels when there was a quote. When
     * it's a gap, the total repeats it (`$547 + envío`).
     *
     * @return array{0: string, 1: string}
     */
    private static function resolveShipping(array $order): array
    {
        if (($order['deliveryType'] ?? null) === 'pickup') {
            return ['', ''];
        }

        if (! isset($order['shipping'])) {
            return [self::SHIPPING_PENDING, ' + envío'];
        }

        if ((float) ($order['shippingTotal'] ?? 0) === 0.0) {
            return ['Gratis', ''];
        }

        return [self::formatMessagePrice($order['shippingTotal']), ''];
    }

    /** `Girasol 42, Col. Las Flores (entre Palma y Ceiba)`, with whatever pieces there are. */
    private static function oneLineAddress(array $customer): string
    {
        $street = implode(' ', array_filter([$customer['street'] ?? null, $customer['exteriorNumber'] ?? null], self::present(...)));
        $neighborhood = $customer['neighborhood'] ?? null;
        $head = implode(', ', array_filter([$street, self::present($neighborhood) ? "Col. {$neighborhood}" : null], self::present(...)));
        $cross = self::present($customer['crossStreets'] ?? null) ? "(entre {$customer['crossStreets']})" : '';

        return implode(' ', array_filter([$head, $cross], self::present(...)));
    }

    /**
     * The shared point's link, reduced to its canonical form from the coordinates it
     * carries. One without them (shortened links) is sent as is.
     */
    private static function canonicalMapsUrl(string $url): string
    {
        $space = '['.self::JS_SPACE.']*';

        if (! preg_match("/(-?\\d{1,3}\\.\\d+){$space},{$space}(-?\\d{1,3}\\.\\d+)/u", $url, $coords)) {
            return $url;
        }

        return "https://www.google.com/maps/search/?api=1&query={$coords[1]},{$coords[2]}";
    }

    /** The mobile written to be read and copied (`+52 9983948803`), not the glued E.164. */
    private static function readablePhone(string $phone): string
    {
        $digits = self::normalizePhone($phone);

        if ($digits === '') {
            return '';
        }

        return str_starts_with($digits, '52') ? '+52 '.substr($digits, 2) : "+{$digits}";
    }

    /**
     * The order link in the store: the public address plus `/recibido`. WITHOUT an order
     * id, knowingly: the acknowledgement paints the last order placed from that browser.
     */
    private static function orderLink(mixed $publicUrl): string
    {
        $base = is_string($publicUrl) ? rtrim(trim($publicUrl), '/') : '';

        return $base !== '' ? "{$base}/recibido" : '';
    }

    /** JavaScript's truthiness for the values these fields can carry. */
    private static function present(mixed $value): bool
    {
        return ! in_array($value, [null, false, '', 0, 0.0], true);
    }
}
