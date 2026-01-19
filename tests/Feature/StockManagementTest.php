<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $stockist;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

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
            'sku' => 'SKU-STOCK-001',
            'cost_price' => 10.00,
            'stock_quantity' => 50,
        ]);

        $initialStock = $product->stock_quantity;
        $initialCostPrice = $product->cost_price;

        $token = $this->stockist->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/stock/entries', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 30,
                        'cost_price' => 12.00,
                    ],
                ],
                'reason' => 'Nota Fiscal 123',
            ]);

        $response->assertStatus(201);

        $product->refresh();

        $this->assertEquals($initialStock + 30, $product->stock_quantity);
        $this->assertEquals(12.00, (float) $product->cost_price);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $this->stockist->id,
            'type' => StockMovementType::ENTRY->value,
            'quantity' => 30,
            'reason' => 'Nota Fiscal 123',
        ]);
    }

    public function test_sale_creates_stock_movement_record(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Venda',
            'sku' => 'SKU-SALE-001',
            'sell_price' => 50.00,
            'stock_quantity' => 100,
        ]);

        $seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);

        Permission::firstOrCreate(['name' => 'pos.access']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->givePermissionTo('pos.access');
        $seller->assignRole('seller');

        $token = $seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product->id,
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
            'product_id' => $product->id,
            'user_id' => $seller->id,
            'type' => StockMovementType::SALE->value,
            'quantity' => 5,
            'reason' => "Sale #{$saleId}",
        ]);

        $product->refresh();
        $this->assertEquals(95, $product->stock_quantity);
    }
}
