<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupons_routes_require_authentication(): void
    {
        $response = $this->getJson('/api/coupons');

        $response->assertStatus(401);
    }
}
