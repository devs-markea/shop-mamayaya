<?php

namespace App\Services\ShopApi;

use RuntimeException;
use Throwable;

/**
 * Fallo al hablar con la API de la tienda. `status` vale 0 cuando ni siquiera respondio.
 */
class ShopApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $body  Cuerpo de la respuesta cuando venia en JSON. Lleva `errors` en los 422.
     * @param  int|null  $retryAfter  Segundos que pide esperar un 429 (`Retry-After`). Se guarda aqui porque quien
     *                                atiende el fallo suele estar lejos de la respuesta.
     * @param  bool  $timedOut  Si la API acepto la conexion y se callo hasta agotar el plazo. Con `status` 0 las dos
     *                          averias se parecen, y son distintas: una es del backend, la otra de la red.
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $path,
        public readonly ?array $body = null,
        public readonly ?int $retryAfter = null,
        public readonly bool $timedOut = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
