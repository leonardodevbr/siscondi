<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Exports\TestProductsExport;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
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
    private Branch $mainBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->category = Category::factory()->create([
            'name' => 'Categoria Existente',
        ]);

        $this->mainBranch = Branch::where('is_main', true)->first() 
            ?? Branch::factory()->create(['name' => 'Matriz', 'is_main' => true]);

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
        $response->assertJsonStructure([
            'message',
            'batch' => [
                'id',
                'user_id',
                'filename',
                'status',
                'total_rows',
                'success_count',
                'error_count',
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Produto Importado 1',
        ]);

        $product = Product::where('name', 'Produto Importado 1')->first();
        $this->assertNotNull($product);
        $this->assertEquals(100.00, (float) $product->sell_price);
        $this->assertEquals(50.00, (float) $product->cost_price);

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('sku', 'SKU-IMP-001')
            ->first();
        
        $this->assertNotNull($variant, 'Product variant should be created with SKU');

        $inventory = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $variant->id)
            ->first();
        
        $this->assertNotNull($inventory, 'Inventory should be created for the variant');
        $this->assertEquals(10, $inventory->quantity);

        $this->assertDatabaseHas('categories', [
            'name' => 'Nova Categoria',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Novo Fornecedor',
        ]);

        $batch = ImportBatch::latest()->first();
        $this->assertNotNull($batch);
        $this->assertEquals($this->manager->id, $batch->user_id);
        $this->assertEquals('test_import.xlsx', $batch->filename);
        $this->assertEquals(ImportBatchStatus::COMPLETED, $batch->status);
        $this->assertEquals(1, $batch->total_rows);
        $this->assertEquals(1, $batch->success_count);
        $this->assertEquals(0, $batch->error_count);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_import_updates_existing_product(): void
    {
        $existingProduct = Product::factory()->create([
            'name' => 'Produto Existente',
            'sell_price' => 50.00,
            'cost_price' => 25.00,
        ]);

        $existingVariant = ProductVariant::factory()->for($existingProduct)->create([
            'sku' => 'SKU-EXIST-001',
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
        $this->assertEquals('Produto Atualizado', $existingProduct->name);

        $inventory = Inventory::where('branch_id', $this->mainBranch->id)
            ->where('product_variant_id', $existingVariant->id)
            ->first();
        
        if ($inventory) {
            $this->assertEquals(20, $inventory->quantity);
        }

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
