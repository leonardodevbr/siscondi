<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use ProductTestHelper;

    private User $manager;
    private Category $category;
    private Branch $mainBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

        $this->mainBranch = Branch::where('is_main', true)->first() 
            ?? Branch::factory()->create(['name' => 'Matriz', 'is_main' => true]);

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
            'sell_price' => 100.00,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 2',
            'sell_price' => 50.00,
        ]);

        $product3 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 3',
            'sell_price' => 75.00,
        ]);

        $variant1 = ProductVariant::factory()->for($product1)->create();
        $variant2 = ProductVariant::factory()->for($product2)->create();
        $variant3 = ProductVariant::factory()->for($product3)->create();

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant1->id,
            'quantity' => 5,
            'min_quantity' => 10,
        ]);

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant2->id,
            'quantity' => 3,
            'min_quantity' => 5,
        ]);

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant3->id,
            'quantity' => 20,
            'min_quantity' => 10,
        ]);

        $saleToday1 = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 200.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::today(),
        ]);

        $saleToday2 = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 150.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::today(),
        ]);

        $saleYesterday = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 300.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::yesterday(),
        ]);

        $saleLastMonth = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 500.00,
            'status' => SaleStatus::COMPLETED,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $saleToday1->items()->create([
            'product_variant_id' => $variant1->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        $saleToday2->items()->create([
            'product_variant_id' => $variant2->id,
            'quantity' => 3,
            'unit_price' => 50.00,
            'total_price' => 150.00,
        ]);

        $saleYesterday->items()->create([
            'product_variant_id' => $variant3->id,
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
            'branch_id' => $this->mainBranch->id,
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
