<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cache\CacheTag;
use App\Services\Cache\ShopCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vacia la cache de las lecturas a peticion del BACKEND: el otro extremo del boton
 * "Refrescar tienda" de Configuracion de la tienda, en el panel.
 *
 *     POST /api/cache/invalidate
 *     X-Cache-Purge-Key: <SHOP_CACHE_PURGE_KEY>
 *     { "tags": ["tienda"] }
 *
 *   204  purgado
 *   401  la clave no es la de aqui
 *   422  el cuerpo no es una lista de etiquetas conocidas
 *   502  la cache no acepto la purga
 *   503  esta tienda no tiene SHOP_CACHE_PURGE_KEY: no se purga nada
 *
 * Solo acepta las etiquetas de CacheTag: aunque alguien tuviera la clave, lo unico que
 * puede hacer con ella es vaciar la cache de esta tienda.
 */
class CacheInvalidateController extends Controller
{
    public function __invoke(Request $request, ShopCache $cache): Response|JsonResponse
    {
        $expected = (string) config('shop.cache_purge_key');

        if ($expected === '') {
            Log::warning('[cache] peticion de purga rechazada: falta SHOP_CACHE_PURGE_KEY');

            return response()->json(['message' => 'La tienda no tiene configurada la clave de purga.'], 503);
        }

        // hash_equals no delata por el tiempo cuantos caracteres acerto.
        $provided = (string) $request->header('X-Cache-Purge-Key', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('[cache] peticion de purga rechazada: clave incorrecta');

            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $tags = $this->readTags($request->json('tags'));

        if ($tags === null) {
            return response()->json(['message' => 'Se esperaba { "tags": [...] } con etiquetas de la tienda.'], 422);
        }

        try {
            $cache->purge(...$tags);
        } catch (Throwable $e) {
            Log::error('[cache] no se pudo purgar', ['tags' => $tags, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'La cache no acepto la purga.'], 502);
        }

        return response()->noContent();
    }

    /**
     * Las etiquetas del cuerpo, o null si el cuerpo no sirve. Una desconocida invalida la
     * peticion entera: si el backend pide algo que aqui no existe, los dos lados dejaron de
     * hablar de lo mismo, y un 204 lo taparia.
     *
     * @return list<CacheTag>|null
     */
    private function readTags(mixed $tags): ?array
    {
        if (! is_array($tags) || $tags === [] || ! array_is_list($tags)) {
            return null;
        }

        $parsed = [];

        foreach ($tags as $tag) {
            $case = is_string($tag) ? CacheTag::tryFrom($tag) : null;

            if ($case === null) {
                return null;
            }

            $parsed[$case->value] = $case;
        }

        return array_values($parsed);
    }
}
