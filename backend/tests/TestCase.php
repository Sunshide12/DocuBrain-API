<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any HTTP call that isn't explicitly Http::fake()'d throws instead of
        // hitting the real network. Keeps CI runs fast, deterministic, and free
        // of accidental calls to the real OpenRouter API.
        Http::preventStrayRequests();
    }
}
