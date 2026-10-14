// ---------------------------------------------------------------------------
// The product page (/mamayaya/{slug}), which is also the store's 404. The same
// modules the Astro page imported, plus the scripts of the components it mounts.
// ---------------------------------------------------------------------------

// The options, the total, the caps and the add to cart.
import '../scripts/order-form.ts';
// The chosen variant's photo in the header. Without a header (no photo) it does nothing.
import '../scripts/product-hero.ts';
import '../scripts/sheet.ts';

// Component scripts: the retry countdown (404/503) and the footer's promo phone form.
import '../components/retry-notice.ts';
import '../components/footer-promo.ts';
