// ---------------------------------------------------------------------------
// The home page (/mamayaya). The same modules the Astro page imported, plus the
// scripts of the components it mounts (in Astro they came with each component).
// ---------------------------------------------------------------------------

// Both indexes (sidebar and tabs) and the horizontal list of featured dishes.
import '../scripts/menu-nav.ts';
import '../scripts/featured-carousel.ts';
// The mobile bar: transparent over the banner, with its background once scrolled.
import '../scripts/navbar-scroll.ts';
// The search: filters the dishes already on the page.
import '../scripts/menu-search.ts';
// Drops each photo's skeleton when it arrives: only this page paints dish cards.
import '../scripts/image-skeleton.ts';
import '../scripts/delivery-mode.ts';
import '../scripts/sheet.ts';
// The category sheet's handle: it closes by dragging down.
import '../scripts/sheet-drag.ts';

// Component scripts: the retry countdown and the footer's promo phone form.
import '../components/retry-notice.ts';
import '../components/footer-promo.ts';
