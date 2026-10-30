// Block: menu-search — el buscador de la portada.
//
// Busca en los platillos que ya estan en la pagina: el catalogo llega entero en una
// respuesta, asi que no hay nada que pedir. Cada platillo de una categoria trae su
// texto ya normalizado en `data-search` (components/MenuSection.astro), y aqui se
// compara con lo escrito normalizado igual (src/lib/search.ts).
//
// Mientras hay texto, la portada cambia sus secciones por una sola lista: marca el
// contenedor con `data-searching` y cada platillo que no coincide con
// `data-search-miss`, y el CSS de MenuSection.astro hace el resto. Al vaciar el campo
// se retiran las dos marcas y las secciones vuelven tal cual estaban.
//
// There are two fields, one per screen (the order bar's on desktop, the tabs' on
// mobile), and they always hold the same text: there is only one search.
//
// On mobile the results open right below the pinned bar, and emptying the field returns
// the buyer to where they were reading.
//
// ⌘K o Ctrl+K lleva al campo desde cualquier parte de la pagina; Escape lo vacia.

import { DESKTOP_QUERY } from '../lib/breakpoints.ts';
import { matchesSearch, normalizeSearch } from '../lib/search.ts';

const MISS = 'data-search-miss';
const SEARCHING = 'data-searching';

const desktop = window.matchMedia(DESKTOP_QUERY);

const plural = (count: number) => (count === 1 ? 'platillo' : 'platillos');

function initMenuSearch(inputs: HTMLInputElement[], list: HTMLElement): void {
  const items = list.querySelectorAll<HTMLElement>('[data-search]');
  const results = document.querySelector<HTMLElement>('[data-search-results]');
  const summary = document.querySelector<HTMLElement>('[data-search-summary]');
  const status = document.querySelector<HTMLElement>('[data-search-status]');

  // Where the mobile buyer was before searching. Null while there's no search to undo.
  let origin: number | null = null;

  function apply(value: string): void {
    const query = normalizeSearch(value);

    if (!query) {
      list.removeAttribute(SEARCHING);
      for (const item of items) item.removeAttribute(MISS);
      if (results) results.hidden = true;
      if (status) status.textContent = '';

      if (origin !== null) window.scrollTo({ top: origin, behavior: 'instant' });
      origin = null;
      return;
    }

    // Before touching the list: a shorter page would clamp the position.
    if (!desktop.matches) origin ??= window.scrollY;

    let count = 0;

    for (const item of items) {
      const hit = matchesSearch(item.dataset.search ?? '', query);
      item.toggleAttribute(MISS, !hit);
      if (hit) count += 1;
    }

    list.setAttribute(SEARCHING, '');

    // Lo escrito tal cual, no normalizado: es lo que el comprador reconoce.
    const text = value.trim();
    const line =
      count > 0
        ? `${count} ${plural(count)} para «${text}»`
        : `Ningún platillo coincide con «${text}»`;

    if (results) results.hidden = false;
    if (summary) summary.textContent = line;
    if (status) status.textContent = line;

    if (!desktop.matches) results?.scrollIntoView({ block: 'start', behavior: 'instant' });
  }

  function write(value: string): void {
    for (const input of inputs) input.value = value;
    apply(value);
  }

  for (const input of inputs) {
    input.addEventListener('input', () => write(input.value));

    input.addEventListener('keydown', (event) => {
      // There's no form to submit: on mobile, Enter puts away the keyboard that was
      // covering the results.
      if (event.key === 'Enter' && !desktop.matches) input.blur();
      if (event.key !== 'Escape' || !input.value) return;

      write('');
    });
  }

  // El atajo. Solo con el buscador de desktop a la vista: robarle el Ctrl+K al navegador
  // en movil seria romperlo para nada.
  document.addEventListener('keydown', (event) => {
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') return;
    if (!desktop.matches) return;

    const input = inputs.find((field) => field.getClientRects().length > 0);
    if (!input) return;

    event.preventDefault();
    input.focus();
    input.select();
  });

  // El navegador puede devolver el texto al volver atras a la pagina: se aplica.
  const restored = inputs.find((input) => input.value);
  if (restored) write(restored.value);
}

const inputs = [...document.querySelectorAll<HTMLInputElement>('[data-menu-search]')];
const list = document.querySelector<HTMLElement>('[data-product-list]');

if (inputs.length > 0 && list) initMenuSearch(inputs, list);
