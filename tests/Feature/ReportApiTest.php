<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class ReportApiTest extends TestCase
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
            'pos.access',
            'products.view',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);
    }

    private function openCashRegister(User $user): CashRegister
    {
        return CashRegister::factory()->create([
            'user_id' => $user->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);
    }

    public function test_sales_report_json(): void
    {
        $this->openCashRegister($this->manager);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastWeek = Carbon::today()->subWeek();

        $saleToday = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 100.00,
            'created_at' => $today->copy()->setTime(10, 0),
        ]);

        $saleYesterday = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 50.00,
            'created_at' => $yesterday->copy()->setTime(10, 0),
        ]);

        $saleLastWeek = Sale::factory()->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'final_amount' => 200.00,
            'created_at' => $lastWeek->copy()->setTime(10, 0),
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->getJson('/api/reports/sales?' . http_build_query([
            'start_date' => $yesterday->format('Y-m-d'),
            'end_date' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'user', 'customer', 'final_amount'],
                ],
            ],
            'summary' => ['total_sales', 'total_amount'],
        ]);

        $data = $response->json('data.data');
        $saleIds = array_column($data, 'id');

        $this->assertContains($saleToday->id, $saleIds);
        $this->assertContains($saleYesterday->id, $saleIds);
        $this->assertNotContains($saleLastWeek->id, $saleIds);

        $summary = $response->json('summary');
        $this->assertEquals(2, $summary['total_sales']);
        $this->assertEquals(150.00, $summary['total_amount']);
    }

    public function test_sales_report_export(): void
    {
        $this->openCashRegister($this->manager);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        Sale::factory()->count(3)->create([
            'user_id' => $this->manager->id,
            'branch_id' => $this->mainBranch->id,
            'created_at' => $today->copy()->setTime(10, 0),
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->get('/api/reports/sales/export?' . http_build_query([
            'start_date' => $yesterday->format('Y-m-d'),
            'end_date' => $today->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('vendas_periodo', $response->headers->get('Content-Disposition'));
    }

    public function test_stock_report_low_stock(): void
    {
        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $product3 = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        $variant1 = ProductVariant::factory()->for($product1)->create();
        $variant2 = ProductVariant::factory()->for($product2)->create();
        $variant3 = ProductVariant::factory()->for($product3)->create();

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant1->id,
            'quantity' => 100,
            'min_quantity' => 10,
        ]);

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant2->id,
            'quantity' => 5,
            'min_quantity' => 10,
        ]);

        \App\Models\Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant3->id,
            'quantity' => 0,
            'min_quantity' => 10,
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->getJson('/api/reports/stock?status=low_stock');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ]);

        $data = $response->json('data');
        $productIds = array_column($data, 'id');

        $this->assertContains($product2->id, $productIds);
        $this->assertNotContains($product1->id, $productIds);
        $this->assertNotContains($product3->id, $productIds);
    }

    public function test_stock_report_export(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
        ]);

        ProductVariant::factory()->for($product)->create();

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->get('/api/reports/stock/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('estoque_atual', $response->headers->get('Content-Disposition'));
    }

    public function test_reports_require_authentication(): void
    {
        $response = $this->getJson('/api/reports/sales?' . http_build_query([
            'start_date' => Carbon::yesterday()->format('Y-m-d'),
            'end_date' => Carbon::today()->format('Y-m-d'),
        ]));

        $response->assertStatus(401);
    }

    public function test_reports_require_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/reports/sales?' . http_build_query([
            'start_date' => Carbon::yesterday()->format('Y-m-d'),
            'end_date' => Carbon::today()->format('Y-m-d'),
        ]));

        $response->assertStatus(403);
    }
}
