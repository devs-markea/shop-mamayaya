// ---------------------------------------------------------------------------
// Transfer payment (/mamayaya/pago/transferencia). The same modules the Astro page
// imported, plus the scripts of the components it mounts.
// ---------------------------------------------------------------------------

// The CLABE's copy button.
import '../scripts/copy-button.ts';
// Closes the order (POST /api/checkout) and moves on to /mamayaya/recibido.
import '../scripts/confirm-order.ts';
// Opens the schedule and location sheets from the desktop bar.
import '../scripts/sheet.ts';

// Component scripts: the footer's promo phone form.
import '../components/footer-promo.ts';
