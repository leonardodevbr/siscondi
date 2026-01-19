<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

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

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Teste',
            'sku' => 'SKU-TEST-001',
            'sell_price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product->id,
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
            'total_amount' => 100.00,
            'final_amount' => 100.00,
            'status' => 'completed',
        ]);

        $sale = Sale::query()->first();

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
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

        $product->refresh();
        $this->assertEquals(8, $product->stock_quantity, 'Stock should be decremented from 10 to 8');
    }

    public function test_insufficient_stock_prevents_sale_and_rolls_back(): void
    {
        $this->openCashRegister($this->seller);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Teste',
            'sku' => 'SKU-TEST-002',
            'sell_price' => 50.00,
            'stock_quantity' => 5,
        ]);

        $initialStock = $product->stock_quantity;

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product->id,
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

        $product->refresh();
        $this->assertEquals($initialStock, $product->stock_quantity, 'Stock should remain unchanged after rollback');
    }

    public function test_payment_validation_fails_when_amount_does_not_match_total(): void
    {
        $this->openCashRegister($this->seller);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Teste',
            'sku' => 'SKU-TEST-003',
            'sell_price' => 100.00,
            'stock_quantity' => 10,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product->id,
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

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto Teste',
            'sku' => 'SKU-TEST-004',
            'sell_price' => 100.00,
            'stock_quantity' => 10,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product->id,
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

        $product1 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 1',
            'sku' => 'SKU-TEST-005',
            'sell_price' => 50.00,
            'stock_quantity' => 10,
        ]);

        $product2 = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Produto 2',
            'sku' => 'SKU-TEST-006',
            'sell_price' => 30.00,
            'stock_quantity' => 10,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $product1->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $product2->id,
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

        $product1->refresh();
        $product2->refresh();

        $this->assertEquals(8, $product1->stock_quantity);
        $this->assertEquals(9, $product2->stock_quantity);
    }
}
