<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\TestProductsExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create([
            'name' => 'Categoria Existente',
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
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions($permissions);
    }

    public function test_template_download_works(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/products/import/template');

        $response->assertStatus(200);
        $response->assertDownload('products_import_template.xlsx');
    }

    public function test_import_creates_new_products(): void
    {
        $data = [
            ['Produto Importado 1', 'SKU-IMP-001', 'Nova Categoria', '50.00', '100.00', '10', 'Novo Fornecedor'],
        ];

        $filePath = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
        
        $content = Excel::raw(new TestProductsExport($data), \Maatwebsite\Excel\Excel::XLSX);
        
        file_put_contents($filePath, $content);
        
        $this->assertFileExists($filePath);
        
        $file = new UploadedFile(
            $filePath,
            'test_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Products imported successfully',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-IMP-001',
            'name' => 'Produto Importado 1',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Nova Categoria',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Novo Fornecedor',
        ]);

        $product = Product::where('sku', 'SKU-IMP-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals(100.00, (float) $product->sell_price);
        $this->assertEquals(50.00, (float) $product->cost_price);
        $this->assertEquals(10, $product->stock_quantity);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_import_updates_existing_product(): void
    {
        $existingProduct = Product::factory()->create([
            'sku' => 'SKU-EXIST-001',
            'name' => 'Produto Existente',
            'sell_price' => 50.00,
            'cost_price' => 25.00,
        ]);

        $data = [
            ['Produto Atualizado', 'SKU-EXIST-001', $this->category->name, '30.00', '75.00', '20', ''],
        ];

        $filePath = sys_get_temp_dir() . '/test_import_update_' . uniqid() . '.xlsx';

        $content = Excel::raw(new TestProductsExport($data), \Maatwebsite\Excel\Excel::XLSX);
        
        file_put_contents($filePath, $content);

        $this->assertFileExists($filePath);

        $file = new UploadedFile(
            $filePath,
            'test_import_update.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $token = $this->manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products/import', [
                'file' => $file,
            ]);

        $response->assertStatus(200);

        $existingProduct->refresh();

        $this->assertEquals(75.00, (float) $existingProduct->sell_price);
        $this->assertEquals(30.00, (float) $existingProduct->cost_price);
        $this->assertEquals(20, $existingProduct->stock_quantity);
        $this->assertEquals('Produto Atualizado', $existingProduct->name);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_import_validation_fails_with_invalid_file(): void
    {
        $token = $this->manager->createToken('test-token')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products/import', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_import_requires_authentication(): void
    {
        $response = $this->getJson('/api/products/import/template');

        $response->assertStatus(401);
    }

    public function test_import_requires_permission(): void
    {
        $seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->givePermissionTo('products.view');
        $seller->assignRole('seller');

        $token = $seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products/import/template');

        $response->assertStatus(403);
    }
}
