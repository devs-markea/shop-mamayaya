<?php

namespace App\Services\ShopApi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Cliente HTTP de la API de la tienda (el ERP).
 *
 * Toda respuesta llega en camelCase y envuelta por el estandar de Laravel Resources:
 * `{ "data": ... }`. El sobre se abre aqui una sola vez, en unwrap(), para que los
 * servicios trabajen con el dato y no con el envoltorio.
 *
 * Solo lo usa el servidor. El navegador nunca habla con la API: llama a /api/* de esta
 * tienda, y eso es lo que permite que SHOP_API_KEY sea un secreto de verdad.
 *
 * NO SE REINTENTA LO QUE CAMBIA ESTADO. Repetir un POST /api/cart/items mete la linea
 * dos veces, y el unico con defensa propia contra el doble envio es el checkout, con su
 * `Idempotency-Key`. El reintento se limita a GET y HEAD.
 */
class ShopApiClient
{
    /** Cuanto de un cuerpo que no es JSON se copia al mensaje del error. */
    private const EXCERPT_CHARS = 500;

    /**
     * The read that already gave up without an answer during THIS request, if any.
     *
     * PHP renders a page with its reads one after another, while Astro launched them in
     * parallel: with an API that accepts connections and stays silent, four reads would
     * add up to four full budgets (~32 s) before the page could show its notice. Once one
     * read exhausted its budget without a response, the next ones fail at once with the
     * same error and the page falls back to cached copies and notices.
     *
     * The client is a singleton per request (PHP-FPM), so this never outlives the page.
     * Writes are not affected: they never retry and are never skipped.
     */
    private ?ShopApiException $unreachable = null;

    /**
     * @param  array{url: ?string, key: ?string, user_agent: string, reads: array{attempt_timeout: float, total_budget: float, max_attempts: int, min_attempt: float, backoff_ms: list<int>}, send_timeout: int}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    /** GET de una lectura: abre el sobre o lanza ShopApiException. */
    public function get(string $path, array $headers = []): mixed
    {
        return $this->unwrap($this->fetch('GET', $path, $headers), $path);
    }

    /**
     * Peticion cruda. No interpreta el resultado: quien llama decide si abre el sobre o
     * reenvia la respuesta tal cual (el proxy del carrito).
     *
     * Solo las lecturas llevan plazo corto y reintento. En ningun caso cambia el contrato:
     * un 5xx se devuelve como Response, y solo un fallo de red lanza ShopApiException.
     */
    public function fetch(string $method, string $path, array $headers = [], ?string $body = null): Response
    {
        $method = strtoupper($method);

        return in_array($method, ['GET', 'HEAD'], true)
            ? $this->readWithRetry($method, $path, $headers)
            : $this->sendOnce($method, $path, $headers, $body);
    }

    /**
     * Une base y ruta. Concatena en lugar de resolver la URL porque resolverla se come
     * el subpath de la base (`https://host/tienda` + `/api/x` daria `https://host/api/x`).
     */
    public function endpoint(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }

    /**
     * Resuelve una URL de imagen contra el host de la API: `image.url` llega absoluta, y
     * una que empiece por `/` es una ruta del ERP, no de esta tienda.
     */
    public function assetUrl(string $url): string
    {
        return str_starts_with($url, '/') ? $this->endpoint($url) : $url;
    }

    /** Abre el sobre `{ data }` o lanza ShopApiException con el cuerpo del fallo. */
    public function unwrap(Response $response, string $path): mixed
    {
        if (! $response->successful()) {
            $body = $response->json();

            throw new ShopApiException(
                "La API respondio {$response->status()} en {$path}.",
                $response->status(),
                $path,
                body: is_array($body) ? $body : null,
                retryAfter: self::retryAfterSeconds($response),
            );
        }

        // UNA RESPUESTA "BUENA" QUE NO TRAE JSON TAMBIEN ES UN FALLO. El antibots del
        // hosting contesta un `202` con una pagina HTML que lleva a su captcha: sin esto se
        // daria por buena y, desde que hay cache, se guardaria como si fuera el catalogo.
        // El cuerpo se copia al mensaje porque esa pagina trae la IP retada y la hora,
        // que es lo que pide el soporte de SiteGround para encontrarlo.
        $text = $response->body();
        $json = json_decode($text, true);

        if (! is_array($json) || ! array_key_exists('data', $json)) {
            throw new ShopApiException(
                "La API respondio {$response->status()} sin JSON en {$path}. ".self::excerpt($text),
                $response->status(),
                $path,
            );
        }

        return $json['data'];
    }

    /**
     * Segundos que pide esperar la respuesta, o null. `Retry-After` puede ser tambien una
     * fecha HTTP, pero Laravel siempre manda segundos y solo se interpreta esa forma.
     */
    public static function retryAfterSeconds(Response $response): ?int
    {
        $seconds = (int) $response->header('Retry-After');

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Lo que cambia estado: una sola peticion, sin reintento y con un plazo largo.
     *
     * Cortar por tiempo un POST /api/checkout es peor que esperar: el backend sigue
     * creando el pedido, el comprador reintenta y puede acabar con dos.
     */
    private function sendOnce(string $method, string $path, array $headers, ?string $body): Response
    {
        $url = $this->endpoint($path);

        try {
            return $this->request($headers)
                ->timeout($this->config['send_timeout'])
                ->send($method, $url, $body === null ? [] : ['body' => $body]);
        } catch (ConnectionException $e) {
            throw new ShopApiException("Sin respuesta de la API en {$url}.", 0, $path, previous: $e);
        }
    }

    /** Lecturas: plazo por tentativa, hasta `max_attempts` y un presupuesto total. */
    private function readWithRetry(string $method, string $path, array $headers): Response
    {
        $url = $this->endpoint($path);

        if ($this->unreachable) {
            throw new ShopApiException(
                "Sin intentar {$url}: la API ya no respondio en esta peticion ({$this->unreachable->path}).",
                0,
                $path,
                timedOut: $this->unreachable->timedOut,
                previous: $this->unreachable,
            );
        }
        $reads = $this->config['reads'];
        $backoff = $reads['backoff_ms'];
        $deadline = microtime(true) + $reads['total_budget'];

        $lastCause = null;
        $lastTimedOut = false;
        $lastBudget = $reads['attempt_timeout'];

        for ($attempt = 0; $attempt < $reads['max_attempts']; $attempt++) {
            if ($attempt > 0) {
                $pause = $backoff[$attempt - 1] ?? end($backoff) ?: 0;

                // Sin presupuesto para la espera MAS una tentativa que valga algo, no se
                // abre otra: moriria a medias y el log hablaria de una lentitud que no hubo.
                if ($deadline - microtime(true) <= $pause / 1000 + $reads['min_attempt']) {
                    break;
                }

                Sleep::usleep($pause * 1000);
            }

            $budget = min($reads['attempt_timeout'], max($deadline - microtime(true), 0));

            if ($budget <= 0) {
                break;
            }

            try {
                $response = $this->request($headers)
                    ->timeout($budget)
                    ->connectTimeout($budget)
                    ->send($method, $url);
            } catch (ConnectionException $e) {
                $lastCause = $e;
                $lastTimedOut = str_contains($e->getMessage(), 'cURL error 28');
                $lastBudget = $budget;

                continue;
            }

            // Antes de descartar un 5xx se comprueba que la tentativa siguiente vaya a
            // existir de verdad, con el MISMO margen que la comprobacion de arriba. Si no,
            // un 5xx lento se descartaria para nada y el fallo saldria como "sin
            // respuesta" cuando la hubo.
            //
            // El 429 no se reintenta: es un techo por minuto, y repetir contra el solo
            // gasta otra ficha del mismo cubo. Ese caso tiene su camino: `Retry-After`.
            $pause = $backoff[$attempt] ?? end($backoff) ?: 0;
            $willRetry = $attempt < $reads['max_attempts'] - 1
                && $deadline - microtime(true) > $pause / 1000 + $reads['min_attempt'];

            if ($willRetry && $response->serverError()) {
                continue;
            }

            return $response;
        }

        $budgetMs = (int) round($lastBudget * 1000);

        throw $this->unreachable = new ShopApiException(
            $lastTimedOut
                ? "La API no respondio en {$budgetMs} ms en {$url}."
                : "Sin respuesta de la API en {$url}.",
            0,
            $path,
            timedOut: $lastTimedOut,
            previous: $lastCause,
        );
    }

    /**
     * Las cabeceras de quien llama y, AL FINAL, las que identifican a esta aplicacion: la
     * clave no es negociable por quien llama, y asi ningun `$headers` puede pisarla.
     */
    private function request(array $headers): PendingRequest
    {
        $client = [
            'User-Agent' => $this->config['user_agent'],
            ...($this->config['key'] ? ['X-Shop-Key' => $this->config['key']] : []),
        ];

        return $this->http->withHeaders(['Accept' => 'application/json', ...$headers, ...$client]);
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) $this->config['url'], '/');

        if ($url === '') {
            throw new RuntimeException('Falta API_URL en el .env: es el origen de la API de la tienda.');
        }

        return $url;
    }

    /** El principio de un cuerpo que no es JSON, en una sola linea, para el log. */
    private static function excerpt(string $text): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($line === '') {
            return 'Cuerpo vacio.';
        }

        return mb_strlen($line) > self::EXCERPT_CHARS
            ? 'Cuerpo: '.mb_substr($line, 0, self::EXCERPT_CHARS).'...'
            : "Cuerpo: {$line}";
    }
}
