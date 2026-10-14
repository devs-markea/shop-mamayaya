// Renders cart lines with the browser's renderLines() (resources/js/lib/cart-view.ts),
// for tests/Feature/Support/CartViewParityTest.php: the server's PHP port must write the
// same bytes.
//
// Reads [{cart, assetBase}, ...] as JSON on stdin and writes the list of HTML strings as
// JSON on stdout. The TypeScript is loaded through Vite, the same way the build reads it.
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

const root = fileURLToPath(new URL('../..', import.meta.url));

let input = '';
for await (const chunk of process.stdin) input += chunk;

const server = await createServer({
    root,
    configFile: false,
    logLevel: 'silent',
    server: { middlewareMode: true, hmr: false },
    appType: 'custom',
});

try {
    const { renderLines, imageResolver } = await server.ssrLoadModule('/resources/js/lib/cart-view.ts');
    const output = JSON.parse(input).map(({ cart, assetBase }) => renderLines(cart, imageResolver(assetBase)));

    process.stdout.write(JSON.stringify(output));
} finally {
    await server.close();
}
