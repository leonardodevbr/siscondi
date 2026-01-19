<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashRegisterApiTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'stock_quantity' => 100,
        ]);

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
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::MONEY->value,
                        'amount' => (float) $this->product->sell_price,
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

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::MONEY->value,
                        'amount' => $this->product->sell_price,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::SALE->value,
            'amount' => (string) $this->product->sell_price,
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
        // 1. Vendedor abre o caixa
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $openResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/cash-register/open', [
                'initial_balance' => 100.00,
            ]);

        $openResponse->assertStatus(201);
        $cashRegisterId = $openResponse->json('cash_register.id');

        // 2. Vendedor faz uma venda
        $saleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'method' => PaymentMethod::MONEY->value,
                        'amount' => $this->product->sell_price,
                    ],
                ],
            ]);

        $saleResponse->assertStatus(201);

        // 3. Gerente faz Sangria
        $manager = User::factory()->create([
            'email' => 'manager-flow@test.com',
            'password' => Hash::make('password'),
        ]);

        // FIX DEFINITIVO: Dar a permissão diretamente ao usuário, ignorando roles/cache
        $permission = Permission::firstOrCreate(['name' => 'financial.manage']);
        $manager->givePermissionTo($permission);
        
        // Limpa cache do Spatie Permission ANTES de criar o token
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Recarrega o usuário do banco para garantir que as permissões estejam atualizadas
        $manager = $manager->fresh();
        
        // Verifica se a permissão foi atribuída corretamente usando can()
        if (!$manager->can('financial.manage')) {
            $this->fail('Manager does not have financial.manage permission. Has: ' . json_encode($manager->getAllPermissions()->pluck('name')->toArray()));
        }

        // Criar o token DEPOIS de dar a permissão e limpar o cache
        $managerToken = $manager->createToken('manager-token')->plainTextToken;
        
        // Limpar cache novamente após criar o token (por garantia)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Usar actingAs para garantir que o usuário correto esteja autenticado
        $this->actingAs($manager, 'sanctum');

        $bleedResponse = $this->postJson("/api/cash-register/{$cashRegisterId}/movement", [
            'type' => 'bleed',
            'amount' => 20.00,
            'description' => 'Sangria',
        ]);

        $bleedResponse->assertStatus(201);

        // 4. Vendedor fecha o caixa (precisa desautenticar o manager e autenticar o seller)
        $this->actingAs($this->seller, 'sanctum');
        
        $expectedBalance = 100.00 + (float) $this->product->sell_price - 20.00;

        $closeResponse = $this->postJson("/api/cash-register/{$cashRegisterId}/close", [
            'final_balance' => $expectedBalance,
        ]);

        $closeResponse->assertStatus(200);

        $cashRegister = CashRegister::find($cashRegisterId);
        $this->assertEquals(CashRegisterStatus::CLOSED, $cashRegister->status);
        $this->assertCount(4, $cashRegister->transactions);
    }
}
