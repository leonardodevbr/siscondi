<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;
    use ProductTestHelper;

    private User $stockist;
    private Category $category;
    private Branch $mainBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

        $this->mainBranch = Branch::where('is_main', true)->first() 
            ?? Branch::factory()->create(['name' => 'Matriz', 'is_main' => true]);

        $this->stockist = User::factory()->create([
            'email' => 'stockist@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->stockist->assignRole('stockist');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'stock.view',
            'stock.entry',
            'products.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $stockistRole = Role::firstOrCreate(['name' => 'stockist']);
        $stockistRole->syncPermissions($permissions);
    }

    public function test_stock_entry_increases_quantity_and_updates_cost_price(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Teste',
            'cost_price' => 10.00,
        ]);

        $variant = ProductVariant::factory()->for($product)->create();

        $inventory = Inventory::create([
            'branch_id' => $this->mainBranch->id,
            'product_variant_id' => $variant->id,
            'quantity' => 50,
            'min_quantity' => 10,
        ]);

        $initialStock = $inventory->quantity;
        $initialCostPrice = $product->cost_price;

        $token = $this->stockist->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/stock/entries', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 30,
                        'cost_price' => 12.00,
                    ],
                ],
                'reason' => 'Nota Fiscal 123',
            ]);

        $response->assertStatus(201);

        $inventory->refresh();
        $product->refresh();

        $this->assertEquals($initialStock + 30, $inventory->quantity);
        $this->assertEquals(12.00, (float) $product->cost_price);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'branch_id' => $this->mainBranch->id,
            'user_id' => $this->stockist->id,
            'type' => StockMovementType::ENTRY->value,
            'quantity' => 30,
            'reason' => 'Nota Fiscal 123',
        ]);
    }

    public function test_sale_creates_stock_movement_record(): void
    {
        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto Venda', 'sell_price' => 50.00],
            [],
            100
        );

        $seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);

        Permission::firstOrCreate(['name' => 'pos.access']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->givePermissionTo('pos.access');
        $seller->assignRole('seller');

        CashRegister::factory()->create([
            'user_id' => $seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 5,
                    ],
                ],
                'payments' => [
                    [
                        'method' => 'pix',
                        'amount' => 250.00,
                        'installments' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $saleId = $response->json('id');

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'branch_id' => $this->mainBranch->id,
            'user_id' => $seller->id,
            'type' => StockMovementType::SALE->value,
            'quantity' => 5,
            'reason' => "Sale #{$saleId}",
        ]);

        $inventory = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant->id)
            ->first();
        
        $this->assertEquals(95, $inventory->quantity);
    }
}
