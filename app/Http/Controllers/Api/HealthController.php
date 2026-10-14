<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Sonda de runtime: comprueba que PHP y Laravel responden. No toca la API ni la cache.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'runtime' => 'php '.PHP_VERSION,
            'timestamp' => now()->toIso8601ZuluString(),
        ]);
    }
}
