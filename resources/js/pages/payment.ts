// ---------------------------------------------------------------------------
// Payment summary (/mamayaya/pago). The same modules the Astro page imported, plus
// the scripts of the components it mounts.
// ---------------------------------------------------------------------------

// Tip, method and total: the tip repaints the total, and continuing goes to the
// method's screen or closes the order (Mercado Pago).
import '../scripts/payment-summary.ts';
// Opens the schedule and location sheets from the desktop bar.
import '../scripts/sheet.ts';

// Component scripts: the footer's promo phone form.
import '../components/footer-promo.ts';
