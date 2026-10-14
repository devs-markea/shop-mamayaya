<div class="chat-fab">
    {{-- The message goes before the button: reading and tab order. The bubble is another
         link to the same chat, out of the tab order (the button is already in it). --}}
    <div class="chat-fab__callout">
        <a class="chat-fab__bubble" href="{{ $href }}" target="_blank" rel="noopener" tabindex="-1">
            Chatea con nosotros
        </a>
        <button class="chat-fab__close btn-plain" type="button" aria-label="Cerrar el aviso del chat">
            <x-icon name="close" size="sm" />
        </button>
    </div>

    <a class="chat-fab__button" href="{{ $href }}" target="_blank" rel="noopener" aria-label="Chatea con nosotros por WhatsApp">
        <x-inline-svg src="icons/whatsapp.svg" class="chat-fab__glyph" width="28" height="28" aria-hidden="true" focusable="false" />
    </a>
</div>

{{-- INLINE AND RIGHT AFTER THE BUTTON: the closed-store notice has to be measured before
     the first paint so the button doesn't jump. The notice is already parsed: the page
     renders it before the layout mounts the chat. --}}
<script>
    (function (linkSlot) {
        const chat = document.querySelector('.chat-fab');
        const callout = chat.querySelector('.chat-fab__callout');
        const button = chat.querySelector('.chat-fab__button');

        // The dish link, in the product page's message: the page as it is called,
        // without the tracking parameters ads hang on it.
        const link = encodeURIComponent(window.location.origin + window.location.pathname);

        for (const anchor of chat.querySelectorAll('a')) {
            anchor.setAttribute('href', anchor.getAttribute('href').replace(linkSlot, link));
        }

        // The X closes the bubble on THIS screen only; focus moves to the button so
        // keyboard users don't land back at the top of the page.
        chat.querySelector('.chat-fab__close').addEventListener('click', () => {
            callout.hidden = true;
            button.focus();
        });

        // Mobile only: keep the chat 14px above the closed-store notice, re-measured when
        // the card changes height or the window resizes. On desktop the CSS decides.
        const notice = document.querySelector('.store-closed__card');

        if (notice) {
            const mobile = window.matchMedia('(max-width: 767.98px)');

            const lift = () => {
                if (!mobile.matches) {
                    chat.style.bottom = '';
                    return;
                }

                const above = window.innerHeight - notice.getBoundingClientRect().top;
                chat.style.bottom = `${Math.ceil(above) + 14}px`;
            };

            lift();
            new ResizeObserver(lift).observe(notice);
            window.addEventListener('resize', lift);
        }
    })(@json($linkSlot));
</script>
