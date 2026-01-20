<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;
    use ProductTestHelper;

    private User $manager;
    private User $seller;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

        $this->manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->manager->assignRole('manager');

        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->seller->assignRole('seller');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $sellerRole = Role::create(['name' => 'seller']);
        $sellerRole->givePermissionTo('products.view');

        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo($permissions);
    }

    public function test_unauthenticated_user_cannot_list_products(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_product(): void
    {
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'cost_price' => 10.00,
                'sell_price' => 20.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_create_product(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'cost_price' => 10.00,
                'sell_price' => 20.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'cost_price',
            'sell_price',
            'effective_price',
            'has_active_promotion',
            'category' => ['id', 'name'],
        ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
        ]);
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'cost_price' => 10.00,
                'sell_price' => 20.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_when_price_is_negative(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'category_id' => $this->category->id,
                'name' => 'Test Product',
                'cost_price' => -10.00,
                'sell_price' => 20.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cost_price']);
    }

    public function test_search_returns_only_matching_products(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Camiseta',
        ]);

        Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Calça',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products?search=Camiseta');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Camiseta');
    }
}
