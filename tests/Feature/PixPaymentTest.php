<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\ProductTestHelper;
use Tests\TestCase;

class PixPaymentTest extends TestCase
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

    public function test_sale_with_pix_creates_pending_payment(): void
    {
        $this->openCashRegister($this->seller);

        $variant = $this->createProductWithVariant(
            ['category_id' => $this->category->id, 'sell_price' => 50.00],
            [],
            100
        );

        $this->actingAs($this->seller, 'sanctum');

        $response = $this->postJson('/api/sales', [
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

        $response->assertStatus(201);
        $response->assertJsonPath('status', SaleStatus::PENDING_PAYMENT->value);

        $sale = Sale::query()->first();
        $this->assertEquals(SaleStatus::PENDING_PAYMENT, $sale->status);

        $payment = $sale->payments()->first();
        $this->assertEquals(PaymentMethod::PIX, $payment->method);
        $this->assertEquals(PaymentStatus::PENDING, $payment->status);
        $this->assertNull($payment->transaction_id);
    }

    public function test_generate_pix_returns_qrcode_data(): void
    {
        $this->openCashRegister($this->seller);

        $sale = Sale::factory()->create([
            'user_id' => $this->seller->id,
            'branch_id' => $this->mainBranch->id,
            'status' => SaleStatus::PENDING_PAYMENT,
            'final_amount' => 50.00,
        ]);

        $payment = Payment::factory()->create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::PIX,
            'amount' => 50.00,
            'status' => PaymentStatus::PENDING,
        ]);

        $this->actingAs($this->seller, 'sanctum');

        $response = $this->getJson("/api/pix/sales/{$sale->id}/generate");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'emv_payload',
            'qrcode_base64',
            'transaction_id',
        ]);

        $payment->refresh();
        $this->assertNotNull($payment->transaction_id);
        $this->assertStringStartsWith('DEV_', $payment->transaction_id);
    }

    public function test_webhook_approves_payment_and_completes_sale(): void
    {
        $cashRegister = $this->openCashRegister($this->seller);

        $sale = Sale::factory()->create([
            'user_id' => $this->seller->id,
            'branch_id' => $this->mainBranch->id,
            'status' => SaleStatus::PENDING_PAYMENT,
            'final_amount' => 50.00,
        ]);

        $payment = Payment::factory()->create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::PIX,
            'amount' => 50.00,
            'status' => PaymentStatus::PENDING,
            'transaction_id' => 'DEV_test123',
        ]);

        $initialBalance = $cashRegister->getCurrentBalance();

        $response = $this->postJson('/api/pix/webhook', [
            'transaction_id' => 'DEV_test123',
            'status' => 'paid',
        ]);

        $response->assertStatus(200);

        $sale->refresh();
        $payment->refresh();

        $this->assertEquals(SaleStatus::COMPLETED, $sale->status);
        $this->assertEquals(PaymentStatus::PAID, $payment->status);

        $cashRegister->refresh();
        $this->assertEquals($initialBalance + 50.00, $cashRegister->getCurrentBalance());

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::SALE->value,
            'amount' => 50.00,
            'sale_id' => $sale->id,
        ]);
    }

    public function test_webhook_fails_payment(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->seller->id,
            'branch_id' => $this->mainBranch->id,
            'status' => SaleStatus::PENDING_PAYMENT,
            'final_amount' => 50.00,
        ]);

        $payment = Payment::factory()->create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::PIX,
            'amount' => 50.00,
            'status' => PaymentStatus::PENDING,
            'transaction_id' => 'DEV_test123',
        ]);

        $response = $this->postJson('/api/pix/webhook', [
            'transaction_id' => 'DEV_test123',
            'status' => 'failed',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::FAILED, $payment->status);

        $sale->refresh();
        $this->assertEquals(SaleStatus::PENDING_PAYMENT, $sale->status);
    }

    public function test_generate_pix_requires_pending_payment_status(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->seller->id,
            'branch_id' => $this->mainBranch->id,
            'status' => SaleStatus::COMPLETED,
            'final_amount' => 50.00,
        ]);

        $this->actingAs($this->seller, 'sanctum');

        $response = $this->getJson("/api/pix/sales/{$sale->id}/generate");

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Sale is not in pending payment status.',
        ]);
    }
}
