<?php

namespace App\Support;

/**
 * The store chat: which number it opens and with which message. Port of src/lib/chat.ts.
 */
final class Chat
{
    /**
     * The number the business gave for questions. It matches the panel's `whatsapp.phone`
     * today, but they are two pieces of data: that one receives order receipts.
     */
    public const PHONE = '5219987566999';

    /**
     * Placeholder for the dish link inside the message. The browser fills it in, since it
     * knows which domain the page is being viewed on. Letters and underscores only, so
     * rawurlencode() leaves it intact and it can be found inside the encoded link.
     */
    public const LINK_SLOT = '__dish_link__';

    /** Greeting by store name ("Hola, Mamá Yaya."), turned off for now. */
    private const GREET_BY_NAME = false;

    /**
     * The message the chat opens with, already written in WhatsApp for the buyer to finish.
     * Unencoded: whoever puts it in a link encodes it.
     */
    public static function message(?string $storeName = null, ?string $dish = null, bool $order = false): string
    {
        $greeting = self::GREET_BY_NAME && $storeName ? "Hola, {$storeName}." : 'Hola.';

        if ($dish) {
            return "{$greeting} Tengo una duda sobre {$dish}\n".self::LINK_SLOT."\n";
        }

        if ($order) {
            return "{$greeting} Tengo una duda sobre mi pedido en línea ";
        }

        return '¡Hola! Necesito ayuda para hacer un pedido en línea. ';
    }
}
