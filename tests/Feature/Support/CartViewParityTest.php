<?php

namespace Tests\Feature\Support;

use App\Support\CartView;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Concerns\FakesCarts;
use Tests\TestCase;

/**
 * The cart's lines are painted by the server (App\Support\CartView) and repainted by the
 * browser (renderLines() in resources/js/lib/cart-view.ts) after every change. If the two
 * drift apart, the list changes shape on the first "+". This runs both on the same carts.
 */
class CartViewParityTest extends TestCase
{
    use FakesCarts;

    #[Test]
    public function el_servidor_y_el_navegador_pintan_lo_mismo(): void
    {
        if (! (new ExecutableFinder)->find('node') || ! is_dir(base_path('node_modules/vite'))) {
            $this->markTestSkipped('Needs node and the npm dependencies (npm install).');
        }

        $cases = [
            ['cart' => $this->cart(), 'assetBase' => 'https://erp.test/'],
            ['cart' => $this->cart(['lines' => [], 'items' => []]), 'assetBase' => 'https://erp.test'],
            ['cart' => $this->cart(['items' => []]), 'assetBase' => 'https://erp.test/tienda'],
        ];

        $process = new Process(['node', base_path('tests/js/render-cart-lines.mjs')], base_path(), timeout: 60);
        $process->setInput(json_encode($cases));
        $process->mustRun();

        $browser = json_decode($process->getOutput(), true);

        foreach ($cases as $index => $case) {
            $this->assertSame($browser[$index], CartView::renderLines($case['cart'], $case['assetBase']), "Case {$index}");
        }
    }
}
