<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->expenseCategory = ExpenseCategory::factory()->create([
            'name' => 'Aluguel',
        ]);

        $this->manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->manager->assignRole('manager');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'financial.manage',
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
            'initial_balance' => 1000.00,
        ]);
    }

    public function test_can_create_pending_expense(): void
    {
        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson('/api/expenses', [
            'description' => 'Aluguel do mês',
            'amount' => 500.00,
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'expense_category_id' => $this->expenseCategory->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'description',
            'amount',
            'due_date',
            'paid_at',
            'is_paid',
            'is_overdue',
            'category' => ['id', 'name'],
            'user' => ['id', 'name'],
        ]);

        $this->assertDatabaseHas('expenses', [
            'description' => 'Aluguel do mês',
            'amount' => 500.00,
            'expense_category_id' => $this->expenseCategory->id,
            'user_id' => $this->manager->id,
        ]);

        $expense = Expense::query()->first();
        $this->assertNull($expense->paid_at);
        $this->assertFalse($expense->isPaid());
    }

    public function test_can_pay_expense_with_cash_and_creates_bleed(): void
    {
        $cashRegister = $this->openCashRegister($this->manager);

        $expense = Expense::factory()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 200.00,
            'paid_at' => null,
        ]);

        $initialBalance = $cashRegister->getCurrentBalance();

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/expenses/{$expense->id}/pay", [
            'payment_method' => 'CASH',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Expense paid successfully',
        ]);

        $expense->refresh();
        $this->assertNotNull($expense->paid_at);
        $this->assertTrue($expense->isPaid());
        $this->assertNotNull($expense->cash_register_transaction_id);

        $cashRegister->refresh();
        $this->assertEquals($initialBalance - 200.00, $cashRegister->getCurrentBalance());

        $this->assertDatabaseHas('cash_register_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => CashRegisterTransactionType::BLEED->value,
            'amount' => -200.00,
        ]);
    }

    public function test_cannot_pay_expense_with_cash_without_open_register(): void
    {
        $expense = Expense::factory()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 200.00,
            'paid_at' => null,
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/expenses/{$expense->id}/pay", [
            'payment_method' => 'CASH',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('cash register', $response->json('message'));

        $expense->refresh();
        $this->assertNull($expense->paid_at);
    }

    public function test_can_pay_expense_with_bank_transfer(): void
    {
        $expense = Expense::factory()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 300.00,
            'paid_at' => null,
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/expenses/{$expense->id}/pay", [
            'payment_method' => 'BANK_TRANSFER',
        ]);

        $response->assertStatus(200);

        $expense->refresh();
        $this->assertNotNull($expense->paid_at);
        $this->assertTrue($expense->isPaid());
        $this->assertNull($expense->cash_register_transaction_id);
    }

    public function test_dashboard_net_profit_includes_expenses(): void
    {
        $this->openCashRegister($this->manager);

        Cache::flush();

        $this->actingAs($this->manager, 'sanctum');

        $response1 = $this->getJson('/api/dashboard');
        $response1->assertStatus(200);
        $profitBefore = $response1->json('profit_month');
        $netProfitBefore = $response1->json('net_profit_month');

        $expense = Expense::factory()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
            'amount' => 150.00,
            'paid_at' => now(),
        ]);

        Cache::flush();

        $response2 = $this->getJson('/api/dashboard');
        $response2->assertStatus(200);
        $response2->assertJsonStructure([
            'sales_today',
            'sales_month',
            'profit_month',
            'net_profit_month',
            'total_sales_count_today',
            'low_stock_products',
            'top_selling_products',
        ]);

        $metrics = $response2->json();
        $this->assertArrayHasKey('net_profit_month', $metrics);
        $this->assertIsNumeric($metrics['net_profit_month']);

        $netProfitAfter = $metrics['net_profit_month'];
        $this->assertEquals($profitBefore, $metrics['profit_month']);
        $this->assertEquals($netProfitBefore - 150.00, $netProfitAfter);
    }

    public function test_expenses_require_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/expenses');

        $response->assertStatus(403);
    }

    public function test_cannot_update_paid_expense(): void
    {
        $expense = Expense::factory()->paid()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->putJson("/api/expenses/{$expense->id}", [
            'description' => 'Nova descrição',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Cannot update a paid expense.',
        ]);
    }

    public function test_cannot_delete_paid_expense(): void
    {
        $expense = Expense::factory()->paid()->create([
            'user_id' => $this->manager->id,
            'expense_category_id' => $this->expenseCategory->id,
        ]);

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Cannot delete a paid expense.',
        ]);
    }
}
