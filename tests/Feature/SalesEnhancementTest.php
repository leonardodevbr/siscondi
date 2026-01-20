<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class SalesEnhancementTest extends TestCase
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
            'initial_balance' => 1000.00,
        ]);
    }

    public function test_sale_uses_promotional_price_when_valid(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            [
                'category_id' => $this->category->id,
                'sell_price' => 100.00,
                'promotional_price' => 80.00,
                'promotional_expires_at' => now()->addDays(5),
            ],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => PaymentMethod::MONEY->value, 'amount' => 80.00],
                ],
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $this->assertEquals(80.00, $sale['total_amount']);
        $this->assertEquals(80.00, $sale['final_amount']);

        $saleItem = $sale['items'][0];
        $this->assertEquals(80.00, $saleItem['unit_price']);
        $this->assertEquals(80.00, $saleItem['total_price']);
    }

    public function test_sale_uses_regular_price_when_promotion_expired(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            [
                'category_id' => $this->category->id,
                'sell_price' => 100.00,
                'promotional_price' => 80.00,
                'promotional_expires_at' => now()->subDay(),
            ],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => PaymentMethod::MONEY->value, 'amount' => 100.00],
                ],
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $this->assertEquals(100.00, $sale['total_amount']);
        $this->assertEquals(100.00, $sale['final_amount']);

        $saleItem = $sale['items'][0];
        $this->assertEquals(100.00, $saleItem['unit_price']);
    }

    public function test_sale_with_percentage_discount(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 100.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => PaymentMethod::MONEY->value, 'amount' => 90.00],
                ],
                'discount_type' => 'percentage',
                'discount_value' => 10,
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $this->assertEquals(100.00, $sale['total_amount']);
        $this->assertEquals(10.00, $sale['discount_amount']);
        $this->assertEquals(90.00, $sale['final_amount']);
    }

    public function test_sale_with_fixed_discount(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 100.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => PaymentMethod::MONEY->value, 'amount' => 85.00],
                ],
                'discount_type' => 'fixed',
                'discount_value' => 15.00,
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $this->assertEquals(100.00, $sale['total_amount']);
        $this->assertEquals(15.00, $sale['discount_amount']);
        $this->assertEquals(85.00, $sale['final_amount']);
    }

    public function test_sale_with_card_authorization_code(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 100.00],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::CREDIT_CARD->value,
                        'amount' => 100.00,
                        'installments' => 1,
                        'card_authorization_code' => 'AUTH123456',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $payment = $sale['payments'][0];
        $this->assertEquals('AUTH123456', $payment['card_authorization_code']);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale['id'],
            'method' => PaymentMethod::CREDIT_CARD->value,
            'card_authorization_code' => 'AUTH123456',
        ]);
    }

    public function test_sale_with_promotional_price_and_percentage_discount(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            [
                'category_id' => $this->category->id,
                'sell_price' => 100.00,
                'promotional_price' => 80.00,
                'promotional_expires_at' => now()->addDays(5),
            ],
            [],
            10
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'branch_id' => $this->mainBranch->id,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
                'payments' => [
                    ['method' => PaymentMethod::MONEY->value, 'amount' => 72.00],
                ],
                'discount_type' => 'percentage',
                'discount_value' => 10,
            ]);

        $response->assertStatus(201);

        $sale = $response->json();
        $this->assertEquals(80.00, $sale['total_amount']);
        $this->assertEquals(8.00, $sale['discount_amount']);
        $this->assertEquals(72.00, $sale['final_amount']);
    }
}
