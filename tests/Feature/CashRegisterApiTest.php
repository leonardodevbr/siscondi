<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class CashRegisterApiTest extends TestCase
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
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'pos.access',
            'products.view',
            'stock.view',
            'financial.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->syncPermissions(['pos.access', 'products.view', 'stock.view']);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);

        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->seller->assignRole('seller');
    }

    public function test_can_open_cash_register(): void
    {
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-register/open', [
                'initial_balance' => 100.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'cash_register' => [
                'id',
                'user_id',
                'opened_at',
                'initial_balance',
                'status',
            ],
        ]);

        $this->assertDatabaseHas('cash_registers', [
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN->value,
            'initial_balance' => '100.00',
        ]);

        $this->assertDatabaseHas('cash_register_transactions', [
            'type' => CashRegisterTransactionType::OPENING_BALANCE->value,
            'amount' => '100.00',
        ]);
    }

    public function test_cannot_open_cash_register_if_already_open(): void
    {
        CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 50.00,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-register/open', [
                'initial_balance' => 100.00,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Usuário já possui um caixa aberto.',
        ]);
    }

    public function test_can_get_cash_register_status(): void
    {
        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/cash-register/status');

        $response->assertStatus(200);
        $response->assertJson([
            'is_open' => true,
            'cash_register' => [
                'id' => $cashRegister->id,
                'initial_balance' => '100.00',
            ],
        ]);
    }

    public function test_status_returns_false_when_no_open_register(): void
    {
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/cash-register/status');

        $response->assertStatus(200);
        $response->assertJson([
            'is_open' => false,
        ]);
    }

    public function test_sale_blocked_without_open_cash_register(): void
    {
        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id],
            [],
            100
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
                        'amount' => (float) $variant->getEffectivePrice(),
                    ],
                ],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Não é possível realizar vendas sem um caixa aberto.',
        ]);
    }

    public function test_can_sell_with_open_cash_register(): void
    {
        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 50.00],
            [],
            100
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
                        'amount' => $variant->getEffectivePrice(),
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::SALE->value,
            'amount' => (string) $variant->getEffectivePrice(),
        ]);
    }

    public function test_can_add_supply_movement(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole('manager');

        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/cash-register/{$cashRegister->id}/movement", [
                'type' => 'supply',
                'amount' => 50.00,
                'description' => 'Suprimento de caixa',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::SUPPLY->value,
            'amount' => '50.00',
        ]);
    }

    public function test_can_add_bleed_movement(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo('financial.manage');
        $manager->assignRole('manager');

        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/cash-register/{$cashRegister->id}/movement", [
                'type' => 'bleed',
                'amount' => 30.00,
                'description' => 'Sangria de caixa',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::BLEED->value,
            'amount' => '-30.00',
        ]);
    }

    public function test_cannot_bleed_more_than_available_balance(): void
    {
        $manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->givePermissionTo('financial.manage');
        $manager->assignRole('manager');

        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/cash-register/{$cashRegister->id}/movement", [
                'type' => 'bleed',
                'amount' => 200.00,
                'description' => 'Sangria excessiva',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Saldo insuficiente para realizar a sangria.',
        ]);
    }

    public function test_can_close_cash_register(): void
    {
        $cashRegister = CashRegister::factory()->create([
            'user_id' => $this->seller->id,
            'status' => CashRegisterStatus::OPEN,
            'initial_balance' => 100.00,
        ]);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/cash-register/{$cashRegister->id}/close", [
                'final_balance' => 150.00,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'cash_register' => [
                'id',
                'closed_at',
                'final_balance',
                'status',
            ],
        ]);

        $cashRegister->refresh();
        $this->assertEquals(CashRegisterStatus::CLOSED, $cashRegister->status);
        $this->assertEquals('150.00', (string) $cashRegister->final_balance);
        $this->assertNotNull($cashRegister->closed_at);

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::CLOSING_BALANCE->value,
        ]);
    }

    public function test_complete_flow_open_sell_bleed_close(): void
    {
        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 50.00],
            [],
            100
        );

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $openResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-register/open', [
                'initial_balance' => 100.00,
            ]);

        $openResponse->assertStatus(201);
        $cashRegisterId = $openResponse->json('cash_register.id');

        $saleResponse = $this->withHeader('Authorization', "Bearer {$token}")
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
                        'amount' => $variant->getEffectivePrice(),
                    ],
                ],
            ]);

        $saleResponse->assertStatus(201);

        $manager = User::factory()->create([
            'email' => 'manager-flow@test.com',
            'password' => Hash::make('password'),
        ]);

        $permission = Permission::firstOrCreate(['name' => 'financial.manage']);
        $manager->givePermissionTo($permission);
        
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $manager = $manager->fresh();
        
        if (!$manager->can('financial.manage')) {
            $this->fail('Manager does not have financial.manage permission. Has: ' . json_encode($manager->getAllPermissions()->pluck('name')->toArray()));
        }

        $managerToken = $manager->createToken('manager-token')->plainTextToken;
        
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $this->actingAs($manager, 'sanctum');

        $bleedResponse = $this->postJson("/api/cash-register/{$cashRegisterId}/movement", [
            'type' => 'bleed',
            'amount' => 20.00,
            'description' => 'Sangria',
        ]);

        $bleedResponse->assertStatus(201);

        $this->actingAs($this->seller, 'sanctum');
        
        $expectedBalance = 100.00 + (float) $variant->getEffectivePrice() - 20.00;

        $closeResponse = $this->postJson("/api/cash-register/{$cashRegisterId}/close", [
            'final_balance' => $expectedBalance,
        ]);

        $closeResponse->assertStatus(200);

        $cashRegister = CashRegister::find($cashRegisterId);
        $this->assertEquals(CashRegisterStatus::CLOSED, $cashRegister->status);
        $this->assertCount(4, $cashRegister->transactions);
    }
}
