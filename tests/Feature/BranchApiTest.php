<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->manager->assignRole('manager');

        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->seller->assignRole('seller');
    }

    private function seedRolesAndPermissions(): void
    {
        $permissions = [
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->syncPermissions(['products.view']);
    }

    public function test_branches_require_authentication(): void
    {
        $response = $this->getJson('/api/branches');

        $response->assertStatus(401);
    }

    public function test_branches_require_permission(): void
    {
        $user = User::factory()->create([
            'email' => 'no-permission@test.com',
            'password' => Hash::make('password'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/branches');

        $response->assertStatus(403);
    }

    public function test_can_list_branches(): void
    {
        Branch::factory()->count(3)->create();

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/branches');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'is_main',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(4, count($data));
        $mainBranch = collect($data)->firstWhere('is_main', true);
        $this->assertNotNull($mainBranch);
        $this->assertTrue($mainBranch['is_main']);
    }

    public function test_can_search_branches(): void
    {
        Branch::factory()->create(['name' => 'Filial Shopping Center']);
        Branch::factory()->create(['name' => 'Filial Centro']);

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/branches?search=Shopping');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Filial Shopping Center', $data[0]['name']);
    }

    public function test_can_create_branch(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $payload = [
            'name' => 'Filial Shopping Center',
            'is_main' => false,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/branches', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'is_main',
            'created_at',
            'updated_at',
        ]);

        $this->assertDatabaseHas('branches', [
            'name' => 'Filial Shopping Center',
            'is_main' => false,
        ]);
    }

    public function test_can_view_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Filial Teste']);

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/branches/{$branch->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $branch->id,
            'name' => 'Filial Teste',
        ]);
    }

    public function test_can_update_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Filial Antiga']);

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $payload = [
            'name' => 'Filial Atualizada',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/branches/{$branch->id}", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Filial Atualizada',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Filial Atualizada',
        ]);
    }

    public function test_cannot_delete_main_branch(): void
    {
        $mainBranch = Branch::where('is_main', true)->first();

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/branches/{$mainBranch->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Não é possível deletar a filial principal.',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $mainBranch->id,
        ]);
    }

    public function test_can_delete_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Filial para Deletar']);

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/branches/{$branch->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Branch deleted successfully',
        ]);

        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ]);
    }

    public function test_seller_can_view_but_not_create_branches(): void
    {
        $token = $this->seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/branches');

        $response->assertStatus(200);

        $payload = [
            'name' => 'Nova Filial',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/branches', $payload);

        $response->assertStatus(403);
    }

    public function test_validation_requires_name(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/branches', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }
}
