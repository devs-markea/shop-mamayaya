// ---------------------------------------------------------------------------
// Global script: the layout loads it on every screen.
//
// Only what every screen shares goes here (the chat button, the image
// skeletons). A screen's own code goes in its entry under resources/js/pages/,
// declared in vite.config.js, so each page loads only its own JavaScript.
//
// Bootstrap's JS is imported per component (`bootstrap/js/dist/dropdown.js`)
// and only where it's used, never the whole bundle.
// ---------------------------------------------------------------------------

// The chat button's drag. Without a button (turned off) it does nothing.
import './scripts/chat-fab.ts';

// Prefetch the next page when a link is pressed (Astro's `prefetch: 'tap'`).
import './scripts/prefetch-tap.ts';
