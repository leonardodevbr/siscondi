<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_shell_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_health_endpoint_loads(): void
    {
        $this->get('/up')->assertOk();
    }
}
