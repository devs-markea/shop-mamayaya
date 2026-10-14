<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartSession;
use App\Services\ShopApi\ShopApiClient;
use App\Services\ShopApi\ShopApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * El proxy de la sesion: carrito, cotizacion del envio, cierre del pedido, cobro y
 * registro de telefonos. Las rutas son las mismas del ERP al que llaman.
 *
 * El token sale SIEMPRE de la cookie httpOnly de esta tienda, nunca de una cabecera del
 * navegador: si se reenviara lo que manda el cliente, cualquiera podria leer el carrito de
 * otra persona poniendo su token a mano. Eso es tambien lo que decide QUE carrito se
 * convierte en pedido.
 *
 * La respuesta del ERP vuelve TAL CUAL —estado y cuerpo— para que un 422 llegue con sus
 * `errors` intactos: sus mensajes de negocio vienen en espanol y los muestra la interfaz.
 */
class ShopProxyController extends Controller
{
    public function __construct(
        private readonly ShopApiClient $api,
        private readonly CartSession $session,
    ) {}

    /** Carrito de la sesion. La API lo crea vacio en la primera visita. */
    public function cart(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'GET', '/api/cart');
    }

    /** Vacia el carrito. */
    public function clearCart(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'DELETE', '/api/cart');
    }

    /** Anade un platillo configurado. Quien valida y pone los precios es la API. */
    public function addItem(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'POST', '/api/cart/items', withBody: true);
    }

    /** Cambia la cantidad de una linea. Con 0 la elimina. */
    public function updateItem(Request $request, string $item): Response|JsonResponse
    {
        return $this->forward($request, 'PATCH', '/api/cart/items/'.rawurlencode($item), withBody: true);
    }

    /** Quita una linea. Una de otra sesion responde 404: la API no confirma que exista. */
    public function removeItem(Request $request, string $item): Response|JsonResponse
    {
        return $this->forward($request, 'DELETE', '/api/cart/items/'.rawurlencode($item));
    }

    /**
     * Cierra el pedido. De las cabeceras del navegador suben DOS, nombradas a mano:
     *
     *   Idempotency-Key    sin ella cada reintento crearia un pedido nuevo
     *   X-Meli-Session-Id  la huella de dispositivo de Mercado Pago, que mejora la
     *                      aprobacion del cobro
     */
    public function checkout(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'POST', '/api/checkout', withBody: true, headers: $this->pick($request, [
            'Idempotency-Key',
            'X-Meli-Session-Id',
        ]));
    }

    /**
     * Abre un cobro nuevo sobre un pedido que sigue esperando pago (Mercado Pago). La API
     * responde 404 si el pedido es de otra sesion.
     */
    public function payment(Request $request, string $id): Response|JsonResponse
    {
        return $this->forward($request, 'POST', '/api/orders/'.rawurlencode($id).'/payment', headers: $this->pick($request, [
            'X-Meli-Session-Id',
        ]));
    }

    /** Cotiza un punto. Quien mide, decide la ciudad y pone el precio es la API. */
    public function quote(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'POST', '/api/shipping/quote', withBody: true);
    }

    /**
     * La ultima cotizacion de esta sesion, sin volver a medir: la red de seguridad del
     * borrador, que dura dos horas contra los treinta dias de la sesion.
     */
    public function lastQuote(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'GET', '/api/shipping/quote');
    }

    /**
     * Registra el telefono de un cliente para las promociones (el formulario del pie).
     *
     * Pasa por la sesion por su cabecera: la API cuenta su limite por `X-Cart-Token`, y sin
     * el lo contaria por IP —la de este servidor, un solo cubo para todos los compradores—.
     */
    public function registerPhone(Request $request): Response|JsonResponse
    {
        return $this->forward($request, 'POST', '/api/store/phones', withBody: true);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function forward(Request $request, string $method, string $path, bool $withBody = false, array $headers = []): Response|JsonResponse
    {
        $token = $this->session->resolve($request);

        if ($withBody) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $upstream = $this->api->fetch(
                $method,
                $path,
                [...$headers, 'X-Cart-Token' => $token],
                $withBody ? $request->getContent() : null,
            );
        } catch (ShopApiException $e) {
            Log::error('[carrito] no se pudo contactar con la API', ['path' => $path, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'No pudimos contactar con la tienda.'], 502, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $response = response($upstream->body(), $upstream->status(), [
            'Content-Type' => $upstream->header('Content-Type') ?: 'application/json',
            // El carrito es de esta sesion: que no lo guarde nadie por el camino.
            'Cache-Control' => 'no-store',
        ]);

        // `Retry-After` es el UNICO dato util de un 429: con el, la pantalla puede decir
        // cuanto falta. Por eso las cabeceras se nombran una a una en lugar de copiarlas.
        if ($retryAfter = $upstream->header('Retry-After')) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Las cabeceras del navegador que se dejan subir, por nombre.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function pick(Request $request, array $names): array
    {
        $picked = [];

        foreach ($names as $name) {
            if (($value = $request->header($name)) !== null && $value !== '') {
                $picked[$name] = $value;
            }
        }

        return $picked;
    }
}
