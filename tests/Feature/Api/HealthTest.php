<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthTest extends TestCase
{
    #[Test]
    public function responde_sin_tocar_la_api(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }
}
