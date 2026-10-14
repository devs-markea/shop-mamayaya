<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningun test sale a la red: una peticion sin su Http::fake() revienta.
        Http::preventStrayRequests();

        // Las esperas entre reintentos de la API no se duermen de verdad.
        Sleep::fake();
    }
}
