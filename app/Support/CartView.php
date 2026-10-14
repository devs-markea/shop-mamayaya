<?php

namespace App\Support;

use App\Services\Catalog\CatalogService;

/**
 * The cart's VIEW: the `lines` of GET /api/cart as HTML. PHP port of renderLines() in
 * resources/js/lib/cart-view.ts.
 *
 * The browser repaints the whole list with that TS function after every quantity change
 * (a "buy X get Y" group takes units from several rows, so a change can rebuild the
 * groups and reorder everything). The server paints the first render with this one.
 * BOTH MUST WRITE THE SAME BYTES: tests/Feature/Support/CartViewParityTest.php runs the
 * two on the same carts. A change here is a change there, and the other way around.
 *
 * Paints `lines` and only `lines`, branching by `kind` and never by the presence of
 * `promotion`: a normal line can bring one too.
 */
final class CartView
{
    private const SEPARATOR = ' · ';

    /**
     * The whole list. Rows give two things: the real quantity (the PATCH base) and the
     * entry order, which picks the row a group's controls edit.
     *
     * @param  array{lines: list<array>, items: list<array{id: string, quantity: int}>}  $cart
     * @param  string  $assetBase  The API host, to resolve relative image URLs.
     */
    public static function renderLines(array $cart, string $assetBase): string
    {
        $rows = [];

        foreach (array_values($cart['items'] ?? []) as $index => $item) {
            $rows[(string) $item['id']] = ['quantity' => $item['quantity'], 'index' => $index];
        }

        $host = rtrim($assetBase, '/');
        $resolve = fn (string $url) => str_starts_with($url, '/') ? $host.$url : $url;

        return implode('', array_map(fn (array $line) => ($line['kind'] ?? null) === 'promoGroup'
            ? self::groupLineHtml($line, $rows, $resolve)
            : self::itemLineHtml($line, $rows[(string) $line['itemId']]['quantity'] ?? $line['quantity'], $resolve),
            $cart['lines'] ?? []));
    }

    /** "Grande · Guacamole x2": what tells two lines of the same dish apart. */
    public static function describeSelection(array $selection, string $separator = self::SEPARATOR): string
    {
        $options = self::describeOptions($selection['options'] ?? [], $separator);
        $variant = $selection['variantName'] ?? null;

        if (! $variant) {
            return $options;
        }

        return $options !== '' ? $variant.$separator.$options : $variant;
    }

    /** Only the customizations: "Guacamole x2 · Queso". Empty when there are none. */
    public static function describeOptions(array $options, string $separator = self::SEPARATOR): string
    {
        return implode($separator, array_map(
            fn (array $option) => $option['quantity'] > 1 ? "{$option['label']} x{$option['quantity']}" : $option['label'],
            $options,
        ));
    }

    /** The dish name, linked to its page while there still is one (`productSlug` null: removed). */
    private static function titleHtml(array $line): string
    {
        $name = Js::escapeHtml((string) $line['name']);

        if (empty($line['productSlug'])) {
            return $name;
        }

        return '<a class="text-reset" href="/mamayaya/'.Js::encodeURIComponent((string) $line['productSlug']).'">'.$name.'</a>';
    }

    /** Mirror of <x-quantity-stepper>, as a string. */
    private static function stepperHtml(int|float $quantity, string $itemName, bool $canDecrease): string
    {
        $name = Js::escapeHtml($itemName);
        $disabled = $canDecrease ? '' : ' disabled';
        $plus = Icons::html('plus', 'sm');

        return <<<HTML
        <div class="stepper" data-stepper>
          <button class="stepper__button stepper__button--minus btn-plain" type="button" aria-label="Quitar una unidad de {$name}" data-step="-1"{$disabled}><span class="stepper__minus"></span></button>
          <span class="stepper__value" aria-live="polite" data-quantity>{$quantity}</span>
          <button class="stepper__button stepper__button--plus btn-plain" type="button" aria-label="Agregar una unidad de {$name}" data-step="1">{$plus}</button>
        </div>
        HTML;
    }

    /**
     * A line's controls: trash, price pair and counter. They always act on a ROW of
     * cart_items (what PATCH can change), so the three numbers may differ: the row's real
     * quantity, what the trash takes, and what is shown.
     */
    private static function controlsHtml(
        string $itemId,
        string $name,
        int|float $rowQuantity,
        int|float $removeQuantity,
        int|float $display,
        bool $canDecrease,
        string $removeLabel,
        string $pricing = '',
    ): string {
        $id = Js::escapeHtml($itemId);
        $label = Js::escapeHtml($removeLabel);
        $trash = Icons::html('trash', 'sm');
        $stepper = self::stepperHtml($display, $name, $canDecrease);

        return <<<HTML
        <div class="order-item__actions" data-row data-line-id="{$id}" data-row-quantity="{$rowQuantity}" data-line-quantity="{$removeQuantity}">
          <button class="order-item__remove btn-plain" type="button" aria-label="Quitar {$label} del pedido" data-remove-line>{$trash}</button>
          {$pricing}
          {$stepper}
        </div>
        HTML;
    }

    /** The photo box, or nothing: a dish without a photo leaves no gap. */
    private static function mediaHtml(mixed $image, callable $resolve): string
    {
        if (! CatalogService::hasImage($image)) {
            return '';
        }

        $src = Js::escapeHtml($resolve($image['url']));
        $alt = Js::escapeHtml((string) ($image['alt'] ?? ''));

        return <<<HTML
        <div class="order-item__media">
          <img class="order-item__image" src="{$src}" width="70" height="70" alt="{$alt}" loading="lazy" decoding="async">
        </div>
        HTML;
    }

    /** The badge next to the name; the promotion's name goes in the title. */
    private static function badgeHtml(array $promotion): string
    {
        return '<span class="promo-badge order-item__badge" title="'.Js::escapeHtml((string) $promotion['name']).'">'
            .Icons::html('tag', 'sm')
            .'<span class="promo-badge__label">'.Js::escapeHtml((string) $promotion['label']).'</span></span>';
    }

    /** The original + final pair, only with a discount: without it there's nothing to compare. */
    private static function pricingHtml(int|float $originalTotal, int|float $total, int|float $discount): string
    {
        if ($discount <= 0 || $originalTotal <= $total) {
            return '';
        }

        return '<span class="order-item__pricing"><s class="price-was">'.Price::format($originalTotal)
            .'</s><span class="order-item__pricing-final">'.Price::format($total).'</span></span>';
    }

    private static function itemLineHtml(array $line, int|float $rowQuantity, callable $resolve): string
    {
        $selection = self::describeSelection($line);

        // The row gives units to a group besides this line.
        $split = $rowQuantity > $line['quantity'];
        $hint = $line['promoHint'] ?? null;

        $notes = [];

        if ($hint) {
            $notes[] = ['text' => "Agrega {$hint['missing']} ".($hint['missing'] === 1 ? 'unidad' : 'unidades')." mas y aprovecha el {$hint['label']}", 'muted' => false];
        }

        if ($split) {
            $notes[] = ['text' => 'Las demas unidades de esta configuracion ya estan en la promocion.', 'muted' => true];
        }

        $media = self::mediaHtml($line['image'] ?? null, $resolve);
        $title = self::titleHtml($line).(! empty($line['promotion']) ? self::badgeHtml($line['promotion']) : '');
        $custom = $selection !== '' ? '<p class="order-item__custom">'.Js::escapeHtml($selection).'</p>' : '';
        $price = Price::format($line['total']);
        $notesHtml = implode('', array_map(
            fn (array $note) => '<p class="order-item__hint'.($note['muted'] ? ' order-item__hint--muted' : '').'">'.Js::escapeHtml($note['text']).'</p>',
            $notes,
        ));
        $controls = self::controlsHtml(
            itemId: (string) $line['itemId'],
            name: (string) $line['name'],
            rowQuantity: $rowQuantity,
            removeQuantity: $line['quantity'],
            display: $line['quantity'],
            canDecrease: $line['quantity'] > 1,
            removeLabel: (string) $line['name'],
            pricing: self::pricingHtml($line['originalTotal'], $line['total'], $line['discount']),
        );

        return <<<HTML
        <li class="order-item">
          {$media}
          <div class="order-item__body">
            <div class="order-item__head">
              <div class="order-item__info">
                <h2 class="order-item__title">{$title}</h2>
                {$custom}
              </div>
              <span class="order-item__price">{$price}</span>
            </div>
            {$notesHtml}
            {$controls}
          </div>
        </li>
        HTML;
    }

    /** A unit of the group, named as the dish it is ("Hamburguesa - Doble"). */
    private static function unitHtml(array $unit, string $productName): string
    {
        $title = Js::escapeHtml(! empty($unit['variantName']) ? "{$productName} - {$unit['variantName']}" : $productName);
        $options = self::describeOptions($unit['options'] ?? []);
        $optionsHtml = $options !== '' ? '<span class="promo-unit__options">'.Js::escapeHtml($options).'</span>' : '';

        // "GRATIS" alone: the free unit is free whole, customizations included.
        $amount = ! empty($unit['isFree'])
            ? '<span class="promo-unit__free">GRATIS</span>'
            : '<span class="promo-unit__price">'.Price::format($unit['unitPrice']).'</span>';

        $class = ! empty($unit['isFree']) ? 'promo-unit promo-unit--free' : 'promo-unit';

        return <<<HTML
        <li class="{$class}">
          <span class="promo-unit__info">
            <span class="promo-unit__label">{$title}</span>
            {$optionsHtml}
          </span>
          {$amount}
        </li>
        HTML;
    }

    /**
     * The row a group's counter and trash edit: the LAST one into the cart of those giving
     * it units (by `items` order, not `units`, which the promotion engine orders).
     */
    private static function lastRow(array $units, array $rows): ?array
    {
        $last = null;

        foreach ($units as $unit) {
            $row = $rows[(string) $unit['itemId']] ?? null;
            // Without a row there's nothing to edit; it stays behind any other.
            $index = $row['index'] ?? -1;

            if ($last && $index < $last['index']) {
                continue;
            }

            $last = ['itemId' => (string) $unit['itemId'], 'quantity' => $row['quantity'] ?? 1, 'unit' => $unit, 'index' => $index];
        }

        return $last;
    }

    private static function groupLineHtml(array $line, array $rows, callable $resolve): string
    {
        $units = implode('', array_map(fn (array $unit) => self::unitHtml($unit, (string) $line['name']), $line['units']));
        $count = count($line['units']);

        // ONE control for the whole group, outside the accordion, pointing at the last
        // row in: "+" and "−" move it and the trash removes it whole.
        $target = self::lastRow($line['units'], $rows);
        $targetLabel = $target ? self::describeSelection($target['unit']) : '';

        $controls = $target
            ? self::controlsHtml(
                itemId: $target['itemId'],
                name: (string) $line['name'],
                rowQuantity: $target['quantity'],
                removeQuantity: $target['quantity'],
                // The counter shows the GROUP's units, which is what the buyer takes.
                display: $count,
                // A group has two units or more: "−" always has something to remove.
                canDecrease: true,
                removeLabel: $targetLabel !== '' ? "{$line['name']} - {$targetLabel}" : (string) $line['name'],
                pricing: self::pricingHtml($line['originalTotal'], $line['total'], $line['discount']),
            )
            : '';

        $media = self::mediaHtml($line['image'] ?? null, $resolve);
        $title = self::titleHtml($line).self::badgeHtml($line['promotion']);
        $price = Price::format($line['total']);

        return <<<HTML
        <li class="order-item order-item--promo">
          {$media}
          <div class="order-item__body">
            <div class="order-item__head">
              <div class="order-item__info">
                <h2 class="order-item__title">{$title}</h2>
              </div>
              <span class="order-item__price">{$price}</span>
            </div>

            <details class="promo-group">
              <summary class="promo-group__summary">Ver las {$count} unidades</summary>
              <ul class="promo-group__units list-unstyled">{$units}</ul>
            </details>

            {$controls}
          </div>
        </li>
        HTML;
    }
}
