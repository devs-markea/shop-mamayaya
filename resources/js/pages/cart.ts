// ---------------------------------------------------------------------------
// The cart (/mamayaya/carrito). The same modules the Astro page imported, plus the
// scripts of the components it mounts.
// ---------------------------------------------------------------------------

// Quantities, removals and totals: every change goes to the API and the page repaints
// with the whole cart it answers.
import '../scripts/order-summary.ts';
// The comments field grows as you type.
import '../scripts/auto-grow.ts';
// Opens the schedule and location sheets from the desktop bar and the closed-store card.
import '../scripts/sheet.ts';

// Component scripts: the retry link (cart unreachable) and the footer's promo phone form.
import '../components/retry-notice.ts';
import '../components/footer-promo.ts';
