<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

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
            'email' => 'seller-error@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->seller->assignRole('seller');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'pos.access',
            'products.view',
            'products.create',
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

    public function test_route_not_found_returns_clean_json_404(): void
    {
        $response = $this->getJson('/api/non-existing-route');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Registro ou rota não encontrada.',
        ]);
        $response->assertJsonMissing(['trace', 'exception']);
    }

    public function test_model_not_found_returns_clean_json_404(): void
    {
        $this->openCashRegister($this->seller);

        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sales/999999');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Registro não encontrado.',
        ]);
        $response->assertJsonMissing(['trace', 'exception']);
    }

    public function test_validation_exception_returns_standard_json_422(): void
    {
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                // category_id ausente de propósito
                'name' => '',
                'cost_price' => -10,
                'sell_price' => -5,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors',
        ]);
        $response->assertJsonMissing(['trace', 'exception']);
    }
}
