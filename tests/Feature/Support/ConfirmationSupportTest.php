<?php

namespace Tests\Feature\Support;

use App\Support\Checkout;
use App\Support\Confirmation;
use App\Support\WhatsApp;
use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The acknowledgement's rules. Checked case by case against the Astro project's own
 * functions (fillMessage, orderMessageValues, resolveConfirmation); these pin the ones
 * that matter most.
 */
class ConfirmationSupportTest extends TestCase
{
    #[Test]
    public function el_env_no_se_escribe_en_el_entorno_del_proceso(): void
    {
        // bootstrap/app.php: on shared hosting another app in the same PHP worker would
        // read this one's variables through getenv().
        $this->assertFalse((new ReflectionProperty(Env::class, 'putenv'))->getValue());
    }

    #[Test]
    public function rellena_la_plantilla_con_sus_cuatro_reglas(): void
    {
        $values = ['folio' => '91', 'nombre' => 'Ana', 'notas' => '', 'estado' => 'Por confirmar'];

        $this->assertSame(
            "Pedido #91 de Ana!\n{inventado}\nPOR CONFIRMAR",
            // 1 unknown stays literal, 2 the empty line goes, 3 capitals shout, 4 the space
            // before "!" and the double spaces go (and a "#" left without its folio, below).
            WhatsApp::fillMessage("Pedido #{folio}  de {nombre} !\n{inventado}\nNotas: {notas}\n{ESTADO}", $values),
        );

        $this->assertSame('Total: 91', WhatsApp::fillMessage("Total: {folio}#\nRef: {notas} {referencias}", $values));
    }

    #[Test]
    public function escribe_los_importes_como_el_erp(): void
    {
        $this->assertSame(['$0', '$1,190', '$119.50', '-$5'], array_map(WhatsApp::formatMessagePrice(...), [0, 1190, 119.5, -5]));
    }

    #[Test]
    public function el_envio_sin_cotizar_se_grita(): void
    {
        $order = ['deliveryType' => 'delivery', 'subtotal' => 500, 'discountTotal' => 0, 'shippingTotal' => 0, 'tipTotal' => 0, 'total' => 500, 'items' => []];

        $values = WhatsApp::orderMessageValues($order);
        $this->assertSame("\u{1F6A8} Por definir \u{1F6A8}", $values['envio']);
        $this->assertSame('$500 + envío', $values['total']);

        $values = WhatsApp::orderMessageValues([...$order, 'shipping' => ['quoteId' => 'q1']]);
        $this->assertSame('Gratis', $values['envio']);
        $this->assertSame('$500', $values['total']);

        // Pickup: no address, no shipping, and `{entrega}` says it.
        $values = WhatsApp::orderMessageValues([...$order, 'deliveryType' => 'pickup', 'customer' => ['street' => 'Coba']]);
        $this->assertSame(['', '', 'Recoger en sucursal'], [$values['envio'], $values['calle'], $values['entrega']]);
    }

    #[Test]
    public function la_plantilla_de_recoger_cae_en_la_de_domicilio(): void
    {
        $templates = ['delivery' => ['bank_transfer' => 'T', 'efectivo' => 'E'], 'pickup' => ['efectivo' => 'P']];

        $this->assertSame('P', WhatsApp::pickTemplate($templates, 'pickup', 'efectivo'));
        $this->assertSame('T', WhatsApp::pickTemplate($templates, 'pickup', 'bank_transfer'));
        $this->assertNull(WhatsApp::pickTemplate($templates, 'delivery', 'mercado_pago'));
    }

    #[Test]
    public function rotula_el_folio_y_no_el_id(): void
    {
        $this->assertSame('49', Checkout::orderFolio(['id' => 'abc', 'folio' => 49]));
        $this->assertSame('000', Checkout::orderFolio(['id' => 'abc', 'folio' => null]));
    }

    #[Test]
    public function el_acuse_de_mercado_pago_sigue_al_cobro(): void
    {
        $order = ['status' => 'pending_payment', 'statusLabel' => 'Pago pendiente', 'deliveryType' => 'delivery'];

        $this->assertSame(['kind' => 'refresh'], Confirmation::resolve('mercado_pago', $order)['action']);
        $this->assertSame(['kind' => 'pay', 'label' => 'Pagar ahora'], Confirmation::resolve('mercado_pago', $order, false)['action']);

        $rejected = Confirmation::resolve('mercado_pago', [...$order, 'payment' => ['rejection' => ['message' => 'Sin fondos.', 'retryable' => false]]]);
        $this->assertSame(['No pudimos cobrar tu pago', 'Sin fondos.', false, ['kind' => 'whatsapp']], [$rejected['title'], $rejected['note'], $rejected['showFolio'], $rejected['action']]);

        // `in_preparation` is "paid" in Mercado Pago, and in cash it only says it's under way.
        $this->assertSame('Pedido confirmado', Confirmation::resolve('mercado_pago', [...$order, 'status' => 'in_preparation'])['title']);
        $this->assertStringEndsWith('Lo pagas en efectivo al recogerlo.', Confirmation::resolve('efectivo', [...$order, 'status' => 'in_preparation', 'deliveryType' => 'pickup'])['note']);
    }

    #[Test]
    public function sin_puntero_deduce_el_metodo_como_puede(): void
    {
        $this->assertSame('mercado_pago', Confirmation::inferMethod(['payment' => ['status' => 'pending']]));
        $this->assertSame('efectivo', Confirmation::inferMethod(['deliveryType' => 'pickup']));
        $this->assertSame('bank_transfer', Confirmation::inferMethod(['deliveryType' => 'delivery']));
    }
}
