<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class SaleApiTest extends TestCase
{
    use RefreshDatabase;
    use ProductTestHelper;

    private User $seller;
    private Category $category;
    private Branch $mainBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

        $this->mainBranch = Branch::where('is_main', true)->first() 
            ?? Branch::factory()->create(['name' => 'Matriz', 'is_main' => true]);

        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->seller->assignRole('seller');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'pos.access',
            'products.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->syncPermissions($permissions);
    }

    private function openCashRegister(User $user): CashRegister
    {
        return CashRegister::factory()->create([
            'user_id' => $user->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);
    }

    public function test_successful_sale_creates_all_records_and_decrements_stock(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto Teste', 'sell_price' => 50.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::CREDIT_CARD->value,
                        'amount' => 100.00,
                        'installments' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'user',
            'total_amount',
            'final_amount',
            'status',
            'items',
            'payments',
        ]);

        $this->assertDatabaseHas('sales', [
            'user_id' => $this->seller->id,
            'branch_id' => $this->mainBranch->id,
            'total_amount' => 100.00,
            'final_amount' => 100.00,
            'status' => 'completed',
        ]);

        $sale = Sale::query()->first();

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00,
        ]);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'method' => PaymentMethod::CREDIT_CARD->value,
            'amount' => 100.00,
            'installments' => 1,
        ]);

        $inventory = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant->id)
            ->first();
        
        $this->assertEquals(8, $inventory->quantity, 'Stock should be decremented from 10 to 8');
    }

    public function test_insufficient_stock_prevents_sale_and_rolls_back(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto Teste', 'sell_price' => 50.00],
            [],
            5
        );

        $initialStock = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant->id)
            ->value('quantity');

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 6,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::PIX->value,
                        'amount' => 300.00,
                        'installments' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(400);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('payments', 0);

        $inventory = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant->id)
            ->first();
        
        $this->assertEquals($initialStock, $inventory->quantity, 'Stock should remain unchanged after rollback');
    }

    public function test_payment_validation_fails_when_amount_does_not_match_total(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto Teste', 'sell_price' => 100.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::PIX->value,
                        'amount' => 50.00,
                        'installments' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_sale_with_discount_calculates_correctly(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto Teste', 'sell_price' => 100.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::MONEY->value,
                        'amount' => 90.00,
                        'installments' => 1,
                    ],
                ],
                'discount_amount' => 10.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('total_amount', '100.00');
        $response->assertJsonPath('discount_amount', '10.00');
        $response->assertJsonPath('final_amount', '90.00');

        $this->assertDatabaseHas('sales', [
            'total_amount' => 100.00,
            'discount_amount' => 10.00,
            'final_amount' => 90.00,
        ]);
    }

    public function test_sale_with_multiple_items_and_payments(): void
    {
        $this->openCashRegister($this->seller);

        $variant1 = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto 1', 'sell_price' => 50.00],
            [],
            10
        );

        $variant2 = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'name' => 'Produto 2', 'sell_price' => 30.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    [
                        'product_variant_id' => $variant1->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_variant_id' => $variant2->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::PIX->value,
                        'amount' => 80.00,
                        'installments' => 1,
                    ],
                    [
                        'method' => PaymentMethod::CREDIT_CARD->value,
                        'amount' => 50.00,
                        'installments' => 3,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('total_amount', '130.00');
        $response->assertJsonPath('final_amount', '130.00');

        $sale = Sale::query()->first();

        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseCount('payments', 2);

        $inventory1 = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant1->id)
            ->first();
        $inventory2 = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant2->id)
            ->first();

        $this->assertEquals(8, $inventory1->quantity);
        $this->assertEquals(9, $inventory2->quantity);
    }
}
