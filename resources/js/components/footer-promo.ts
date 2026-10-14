// ---------------------------------------------------------------------------
// El registro del telefono de las promociones: `POST /api/store/phones`, por el
// proxy de este front (ver src/pages/api/store/phones.ts). Contrato:
// `api/post-store-phones.md`.
//
// La respuesta buena es la misma si el numero es nuevo que si ya estaba, y a
// proposito (ver el contrato): la confirmacion tampoco los distingue.
// ---------------------------------------------------------------------------
import { throttleMessage } from '../lib/throttle.ts';

// Los dos primeros son los textos del 422 de la API. Se escriben aqui para
// decirlos sin gastar una peticion: el limite es de cinco registros por minuto y
// un campo vacio no merece ninguno.
const EMPTY = 'Escribe tu teléfono.';
const INVALID = 'Escribe un teléfono válido, con 10 dígitos.';
const DONE = '¡Listo! Registramos tu número.';
const FAILED = 'No pudimos registrar tu número. Inténtalo de nuevo en un momento.';

/**
 * El aviso de un rechazo que no es un 429.
 *
 * El del 422 se ensena tal cual: el contrato dice que esta escrito para el
 * comprador, y es lo que hace la tienda con los `errors` del checkout (ver
 * readError en src/lib/checkout.ts). Cualquier otro fallo —la clave, la API
 * caida, el proxy sin contacto— no es del comprador, y se dice lo mismo en todos.
 */
async function rejection(response: Response): Promise<string> {
  if (response.status !== 422) return FAILED;

  const body = (await response.json().catch(() => null)) as {
    errors?: Record<string, string[]>;
  } | null;

  return body?.errors?.phone?.[0] ?? INVALID;
}

for (const form of document.querySelectorAll<HTMLFormElement>('[data-promo-form]')) {
  const input = form.querySelector<HTMLInputElement>('input[name="phone"]');
  const button = form.querySelector<HTMLButtonElement>('button[type="submit"]');
  const status = form.querySelector<HTMLElement>('[data-promo-status]');

  if (!input || !button || !status) continue;

  const say = (text: string, tone: 'error' | 'success') => {
    status.textContent = text;
    status.dataset.tone = tone;
    status.hidden = false;

    if (tone === 'error') input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  };

  // Al volver a escribir, el aviso deja de hablar de lo que hay en el campo.
  input.addEventListener('input', () => {
    status.hidden = true;
    input.removeAttribute('aria-invalid');
  });

  // `aria-disabled` y no `disabled` mientras va de camino: un boton deshabilitado
  // con el foco encima lo suelta, y quien lo pulso con el teclado se quedaria
  // sin saber donde esta. La marca basta para no mandar dos veces.
  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (button.getAttribute('aria-disabled') === 'true') return;

    const phone = input.value.trim();
    // Los mismos limites que la API —de 10 a 15 digitos— y la misma cuenta que
    // phoneDigits en src/lib/checkout-draft.ts, sin importar aquel modulo.
    const digits = phone.replace(/\D/g, '').length;

    if (!phone || digits < 10 || digits > 15) {
      say(phone ? INVALID : EMPTY, 'error');
      input.focus();
      return;
    }

    button.setAttribute('aria-disabled', 'true');

    try {
      const response = await fetch('/api/store/phones', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ phone }),
      });

      if (response.ok) {
        input.value = '';
        say(DONE, 'success');
      } else {
        say(throttleMessage(response) ?? (await rejection(response)), 'error');
      }
    } catch {
      // fetch solo rechaza por red: no llego ni al proxy.
      say(FAILED, 'error');
    } finally {
      button.removeAttribute('aria-disabled');
    }
  });
}
