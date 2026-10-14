<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Las direcciones de cuando las pantallas vivian en la raiz, y la del acuse viejo de
 * Mercado Pago. Ver routes/web.php.
 *
 * Existe en lugar de Route::redirect() porque aquel descarta la query, y la vuelta de
 * Mercado Pago a /mamayaya/confirmado trae `?order={id}`: es el respaldo del acuse
 * cuando la cookie del pedido se perdio.
 */
class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $query = $request->getQueryString();

        // 302 y no 301: un 301 se queda cacheado en el navegador para siempre, y el dia
        // que la raiz tenga contenido propio obligaria a vaciar el de cada visitante.
        return redirect()->to($request->route()->defaults['to'].($query ? "?{$query}" : ''), 302);
    }
}
