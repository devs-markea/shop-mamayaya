// ---------------------------------------------------------------------------
// Prefetch on TAP: what Astro's `prefetch: { prefetchAll: true, defaultStrategy: 'tap' }`
// did in the previous project.
//
// A same-origin page is prefetched when the link is PRESSED (touchstart / mousedown),
// not hovered. Every screen is rendered on the server with several API reads behind
// it, and the API's read limit is one shared bucket for the whole store (all buyers
// reach it through this server's IP): prefetching on hover would make the bill depend
// on how much the mouse moves instead of on real visits. On tap, the buyer already
// decided to go, and the milliseconds between pressing and releasing are enough to
// get the page started.
// ---------------------------------------------------------------------------

const prefetched = new Set<string>();

/** Slow or data-saving connections don't get prefetches they didn't ask for. */
function constrained(): boolean {
  const connection = (navigator as Navigator & { connection?: { saveData?: boolean; effectiveType?: string } })
    .connection;

  return Boolean(connection?.saveData || /2g/.test(connection?.effectiveType ?? ''));
}

function prefetchable(anchor: HTMLAnchorElement): URL | null {
  if (anchor.target === '_blank' || anchor.hasAttribute('download') || 'noPrefetch' in anchor.dataset) {
    return null;
  }

  const url = new URL(anchor.href, window.location.href);

  // Only pages of this same store; not the page we are on (a hash jump), and once.
  if (url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return null;
  if (url.pathname === window.location.pathname && url.search === window.location.search) return null;
  if (prefetched.has(url.href)) return null;

  return url;
}

function prefetch(url: URL): void {
  prefetched.add(url.href);

  const link = document.createElement('link');

  if (link.relList?.supports?.('prefetch')) {
    link.rel = 'prefetch';
    link.href = url.href;
    document.head.append(link);
  } else {
    void fetch(url.href, { priority: 'low' } as RequestInit).catch(() => {});
  }
}

function onPress(event: Event): void {
  if (constrained() || !(event.target instanceof Element)) return;

  const anchor = event.target.closest<HTMLAnchorElement>('a[href]');
  const url = anchor ? prefetchable(anchor) : null;

  if (url) prefetch(url);
}

document.addEventListener('touchstart', onPress, { passive: true });
document.addEventListener('mousedown', onPress, { passive: true });
