<?php

namespace App\Services\Cart;

use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * La sesion del carrito: un ULID en una cookie httpOnly de esta tienda.
 *
 * El navegador NO habla con la API: llama a /api/* de esta tienda y el servidor reenvia
 * el token en `X-Cart-Token`. Asi ningun script de la pagina puede leerlo, no hace falta
 * CORS con credenciales en el backend, y no hay que reenviar el Set-Cookie del ERP. El
 * contrato de la API lo contempla: el token puede gestionarlo el cliente (un ULID) y tiene
 * prioridad sobre su cookie.
 */
class CartSession
{
    public function __construct(
        private readonly CookieJar $cookies,
        private readonly string $cookieName,
        private readonly int $lifetimeMinutes,
        private readonly bool $secure,
    ) {}

    /**
     * El token que ya tiene la sesion, o null: aqui no se emite ninguno.
     *
     * Es lo que necesita una pantalla que solo quiere ENSENAR el carrito. `GET /api/cart`
     * crea el carrito si no existe, asi que emitir un token por visita acumularia en el
     * backend un carrito vacio por visitante y por rastreador.
     */
    public function peek(Request $request): ?string
    {
        $token = $request->cookie($this->cookieName);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** El token de la sesion, emitiendolo si es la primera vez. */
    public function resolve(Request $request): string
    {
        if ($token = $this->peek($request)) {
            return $token;
        }

        $token = (string) Str::ulid();

        $this->cookies->queue($this->cookies->make(
            $this->cookieName,
            $token,
            $this->lifetimeMinutes,
            path: '/',
            secure: $this->secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));

        // Para que lo que se lea despues en esta misma peticion vea la sesion nueva.
        $request->cookies->set($this->cookieName, $token);

        return $token;
    }
}
