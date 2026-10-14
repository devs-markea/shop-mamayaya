// ---------------------------------------------------------------------------
// La cuenta atras y la recarga.
//
// POCAS RECARGAS Y CADA VEZ MAS ESPACIADAS: ESTO ES LO IMPORTANTE DE TODO EL ARCHIVO. Si
// cada pantalla que falla se recarga sola sin limite, lo que se ha construido es una
// avalancha contra un backend que justo estaba cayendo — y en este hosting cae
// precisamente por quedarse sin procesos de PHP. Cada recarga son hasta cinco llamadas a
// la API, y cada llamada hasta tres tentativas en el servidor: insistir deprisa le quita
// al backend los pocos procesos que le quedaban para levantarse.
//
// Asi que la recarga automatica tiene tres frenos:
//
//   espera creciente  3 s, 6 s, 12 s. El tropiezo del arranque en frio se cura en la
//                     primera; una caida mas larga no se arregla insistiendo mas deprisa.
//   aleatoriedad      +-25 %. Sin ella, todas las pestanas que fallaron en el mismo
//                     segundo vuelven a pedir en el mismo segundo, que es la rafaga otra
//                     vez.
//   un final          tras la tercera, el aviso se queda quieto con su enlace manual.
//
// La cuenta va por pantalla —agotarla en la portada no deja sin ella a la ficha— y se
// reinicia tras cinco minutos sin recargas: quien tropieza, sigue comprando y vuelve a
// tropezar media hora despues tiene otra vez sus intentos.
// ---------------------------------------------------------------------------

/** Primera espera, en segundos. Cada intento siguiente la dobla. */
const BASE_S = 3;
/** Recargas automaticas antes de dejar solo el enlace. */
const MAX_AUTO = 3;
/** Techo de la espera. Con tres intentos no se llega a el; esta por si sube MAX_AUTO. */
const MAX_DELAY_S = 30;
/** Sin recargas durante este tiempo, la cuenta vuelve a empezar. */
const RESET_MS = 5 * 60_000;

const KEY = `sf:retry:${location.pathname}`;

const notice = document.querySelector<HTMLElement>('.retry-notice[data-retry-mode="auto"]');
const action = document.querySelector<HTMLAnchorElement>('[data-retry-action]');

// El enlace recarga en lugar de navegar. Navegar a la misma direccion puede resolverse
// desde el cache del navegador y devolver el mismo aviso sin haber preguntado nada.
//
// Y NO toca la cuenta: es el comprador quien decide insistir, uno a uno, y eso no es la
// avalancha que la cuenta existe para frenar.
action?.addEventListener('click', (event) => {
  event.preventDefault();
  location.reload();
});

interface RetryState {
  /** Recargas automaticas ya hechas en esta pantalla. */
  attempts: number;
  /** Cuando se hizo la ultima, en ms. 0 si nunca. */
  last: number;
}

/** La cuenta de esta pantalla, o `null` si no hay donde guardarla. */
function readState(): RetryState | null {
  let raw: string | null;

  try {
    raw = sessionStorage.getItem(KEY);
  } catch {
    // Sin almacenamiento —ventana privada, cookies bloqueadas— no hay forma de contar las
    // recargas. Ante la duda NO se recarga: no poder contarlas es justo lo que convierte
    // una recarga en un bucle.
    return null;
  }

  try {
    const parsed = JSON.parse(raw ?? '{}') as Partial<RetryState>;

    return {
      attempts: Number.isInteger(parsed.attempts) ? Number(parsed.attempts) : 0,
      last: Number.isFinite(parsed.last) ? Number(parsed.last) : 0,
    };
  } catch {
    // Un valor ilegible es una cuenta nueva, no un almacenamiento roto: si se tratara
    // como lo segundo, esa pantalla no volveria a recargarse sola nunca.
    return { attempts: 0, last: 0 };
  }
}

/** Segundos de espera antes de la recarga numero `attempt` (0, 1, 2…). */
function delayFor(attempt: number): number {
  const base = Math.min(BASE_S * 2 ** attempt, MAX_DELAY_S);
  const jitter = base * (Math.random() * 0.5 - 0.25);

  // Al segundo entero, porque es lo que se lee en pantalla; y nunca menos de uno.
  return Math.max(1, Math.round(base + jitter));
}

const state = notice ? readState() : null;

if (notice && state) {
  const attempts = Date.now() - state.last > RESET_MS ? 0 : state.attempts;

  if (attempts < MAX_AUTO) {
    const line = notice.querySelector<HTMLElement>('[data-retry-countdown]');
    const slot = notice.querySelector<HTMLElement>('[data-retry-count]');

    let left = delayFor(attempts);

    if (slot) slot.textContent = String(left);
    if (line) line.hidden = false;

    const tick = setInterval(() => {
      left -= 1;

      if (left > 0) {
        if (slot) slot.textContent = String(left);
        return;
      }

      clearInterval(tick);

      // Se anota ANTES de recargar: despues no hay despues, la pagina ya se fue. Y si no
      // se puede anotar, tampoco se recarga — ver readState.
      try {
        sessionStorage.setItem(KEY, JSON.stringify({ attempts: attempts + 1, last: Date.now() }));
      } catch {
        return;
      }

      location.reload();
    }, 1000);
  }
}
