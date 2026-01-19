<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
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
            Permission::firstOrCreate(['name' => $permission]);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);
    }

    public function test_dashboard_returns_correct_metrics(): void
    {
        Cache::flush();

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 1',
            'sku' => 'SKU-DASH-001',
            'sell_price' => 100.00,
            'stock_quantity' => 5,
            'min_stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 2',
            'sku' => 'SKU-DASH-002',
            'sell_price' => 50.00,
            'stock_quantity' => 3,
            'min_stock_quantity' => 5,
        ]);

        $product3 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 3',
            'sku' => 'SKU-DASH-003',
            'sell_price' => 75.00,
            'stock_quantity' => 20,
            'min_stock_quantity' => 10,
        ]);

        $saleToday1 = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'final_amount' => 200.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::today(),
        ]);

        $saleToday2 = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'final_amount' => 150.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::today(),
        ]);

        $saleYesterday = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'final_amount' => 300.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::yesterday(),
        ]);

        $saleLastMonth = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'final_amount' => 500.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $saleToday1->items()->create([
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        $saleToday2->items()->create([
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 50.00,
            'total_price' => 150.00,
        ]);

        $saleYesterday->items()->create([
            'product_id' => $product3->id,
            'quantity' => 1,
            'unit_price' => 75.00,
            'total_price' => 75.00,
        ]);

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'sales_today',
            'sales_month',
            'profit_month',
            'total_sales_count_today',
            'low_stock_products',
            'top_selling_products',
        ]);

        $data = $response->json();

        $this->assertEquals(350.00, $data['sales_today']);
        $this->assertEquals(2, $data['total_sales_count_today']);
        $this->assertGreaterThanOrEqual(350.00, $data['sales_month']);
        $this->assertLessThan(850.00, $data['sales_month']);

        $this->assertCount(2, $data['low_stock_products']);
        
        $lowStockIds = array_column($data['low_stock_products'], 'id');
        $this->assertContains($product1->id, $lowStockIds);
        $this->assertContains($product2->id, $lowStockIds);
        $this->assertNotContains($product3->id, $lowStockIds);

        $this->assertGreaterThanOrEqual(2, count($data['top_selling_products']));
    }

    public function test_dashboard_uses_cache(): void
    {
        Cache::flush();

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard');

        $response1->assertStatus(200);

        $sale = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'final_amount' => 999.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::today(),
        ]);

        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard');

        $response2->assertStatus(200);

        $this->assertEquals(
            $response1->json('sales_today'),
            $response2->json('sales_today'),
            'Cache should return same value even after creating new sale'
        );
    }
}
