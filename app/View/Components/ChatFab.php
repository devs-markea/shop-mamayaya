<?php

namespace App\View\Components;

use App\Support\Chat;
use App\Support\WhatsApp;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The chat button: a WhatsApp circle that opens the conversation with the message
 * already written, and the "Chatea con nosotros" bubble. Port of
 * src/components/ChatFab.astro; its styles are resources/css/components/chat-fab.css
 * and its drag is resources/js/scripts/chat-fab.ts. The layout mounts it on every page.
 */
class ChatFab extends Component
{
    /** Hiding the button is setting this to false: nothing else needs to change. */
    private const ENABLED = true;

    public ?string $href;

    public string $linkSlot = Chat::LINK_SLOT;

    /**
     * @param  string|null  $dish  The dish, when the page is its product page.
     * @param  bool  $order  Whether the page is one of the order screens.
     */
    public function __construct(public array $store, public ?string $dish = null, public bool $order = false)
    {
        $this->href = self::ENABLED
            ? WhatsApp::url(Chat::PHONE, Chat::message($store['name'] ?? null, $dish, $order))
            : null;
    }

    public function shouldRender(): bool
    {
        return $this->href !== null;
    }

    public function render(): View
    {
        return view('components.chat-fab');
    }
}
