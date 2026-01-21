<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\SkuGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        private readonly SkuGeneratorService $skuGeneratorService,
    ) {
    }

    private function currentBranchId(?Request $request = null): ?int
    {
        if (app()->bound('current_branch_id')) {
            return (int) app('current_branch_id');
        }

        $user = ($request?->user()) ?? auth()->user();
        if ($user && $user->branch_id) {
            return (int) $user->branch_id;
        }

        $fallback = \App\Models\Branch::where('is_main', true)->value('id')
            ?? \App\Models\Branch::query()->min('id');

        return $fallback ? (int) $fallback : null;
    }

    private function sanitizeBranchId(?int $branchId): ?int
    {
        if ($branchId === null) {
            return null;
        }

        $exists = \App\Models\Branch::where('id', $branchId)->exists();
        return $exists ? $branchId : null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $branchId = app()->bound('current_branch_id') ? (int) app('current_branch_id') : null;

        $query = Product::with([
            'category',
            'supplier',
            'variants' => function ($query) use ($branchId): void {
                $query->with(['inventories' => function ($inventoryQuery) use ($branchId): void {
                    if ($branchId !== null) {
                        $inventoryQuery->where('branch_id', $this->sanitizeBranchId($branchId));
                    }
                }]);
            },
        ]);

        // 1. Filtro por Texto (Nome, SKU ou Barcode)
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('variants', function ($q2) use ($search) {
                      $q2->where('sku', 'like', "%{$search}%")
                         ->orWhere('barcode', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Filtro por Categoria (CORREÇÃO AQUI)
        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->paginate(15);

        return ProductResource::collection($products)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $data = $request->validated();
            $variants = $data['variants'] ?? [];
            $initialStock = $data['initial_stock'] ?? [];
            $stock = $data['stock'] ?? 0;
            unset($data['variants'], $data['initial_stock'], $data['stock']);

            if ($request->hasFile('cover_image')) {
                $image = $request->file('cover_image');
                $path = $image->store('products', 'public');
                $data['image'] = $path;
            }

            $product = Product::create($data);
            $currentBranchId = $this->currentBranchId($request);

            if (empty($variants)) {
                $this->createDefaultVariant($product, $request, (int) $stock, $currentBranchId);
            } else {
                foreach ($variants as $index => $variantData) {
                    $variantData = $this->handleVariantImage($request, $variantData, $index);
                    $variantData['sku'] = $variantData['sku']
                        ?? $this->skuGeneratorService->generate($product, $variantData['attributes'] ?? []);

                    $variant = $product->variants()->create($variantData);

                    if (! empty($initialStock)) {
                        $stockQuantity = $initialStock[$index]['quantity'] ?? 0;
                        if ($stockQuantity > 0 && $currentBranchId) {
                            \App\Models\Inventory::create([
                                'branch_id' => $currentBranchId,
                                'product_variant_id' => $variant->id,
                                'quantity' => $stockQuantity,
                                'min_quantity' => 0,
                            ]);
                        }
                    }
                }
            }

            $this->loadProductWithCurrentBranchStock($product, $request);

            return response()->json(new ProductResource($product), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product, Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $this->loadProductWithCurrentBranchStock($product, $request);

        return response()->json(new ProductResource($product));
    }

    /**
     * Check availability of all variants across all branches.
     */
    public function checkAvailability(Product $product): JsonResponse
    {
        $this->authorize('products.view');

        $product->load([
            'variants.inventories.branch',
        ]);

        $variants = $product->variants;

        /** @var \Illuminate\Support\Collection<int, \App\Models\Branch> $branches */
        $branches = $variants
            ->flatMap(static fn (ProductVariant $variant) => $variant->inventories->pluck('branch'))
            ->filter()
            ->unique('id')
            ->values();

        $branchesData = $branches
            ->map(static fn ($branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values()
            ->all();

        $variantsData = $variants
            ->map(static function (ProductVariant $variant) use ($branches): array {
                $stockByBranch = [];

                foreach ($branches as $branch) {
                    $inventory = $variant->inventories->firstWhere('branch_id', $branch->id);
                    $stockByBranch[(string) $branch->id] = $inventory?->quantity ?? 0;
                }

                return [
                    'sku' => $variant->sku,
                    'attributes' => $variant->attributes ?? [],
                    'stock_by_branch' => $stockByBranch,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'product_name' => $product->name,
            'branches' => $branchesData,
            'variants' => $variantsData,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return DB::transaction(function () use ($request, $product): JsonResponse {
            $data = $request->validated();
            
            // Pega variants do validated ou do input direto (para garantir que stock venha)
            $variants = $data['variants'] ?? $request->input('variants', []);
            // Pega stock do validated ou do input direto
            $stock = $data['stock'] ?? $request->input('stock');
            unset($data['variants'], $data['stock']);

            if ($request->hasFile('cover_image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }

                $image = $request->file('cover_image');
                $path = $image->store('products', 'public');
                $data['image'] = $path;
            }

            $product->update($data);

            $currentBranchId = $this->currentBranchId($request);

            // Sincronização inteligente das variações
            $hasVariants = is_array($variants) && count($variants) > 0;
            
            if ($hasVariants) {
                $existingVariantIds = $product->variants()->pluck('id')->all();

                $submittedIds = collect($variants)
                    ->pluck('id')
                    ->filter()
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                // Remove variações que não vieram no payload (foram excluídas no frontend)
                $variantsToDelete = array_diff($existingVariantIds, $submittedIds);
                if ($variantsToDelete !== []) {
                    $variantsToDeleteModels = $product->variants()
                        ->whereIn('id', $variantsToDelete)
                        ->get();

                    foreach ($variantsToDeleteModels as $variantToDelete) {
                        if ($variantToDelete->image) {
                            Storage::disk('public')->delete($variantToDelete->image);
                        }
                    }

                    $product->variants()
                        ->whereIn('id', $variantsToDelete)
                        ->delete();
                }

                foreach ($variants as $index => $variantData) {
                    if (! is_array($variantData)) {
                        continue;
                    }

                    $variantData = $this->handleVariantImage($request, $variantData, $index, $product->id);

                    // Gera SKU se necessário
                    if (empty($variantData['sku'])) {
                        $generatedSku = $this->skuGeneratorService->generate($product, $variantData['attributes'] ?? []);
                        if ($generatedSku !== null) {
                            $variantData['sku'] = $generatedSku;
                        }
                    }

                    // Extrai informações de estoque da variação (se vierem)
                    $stockValue = null;
                    if (isset($variantData['stock'])) {
                        $stockValue = $variantData['stock'] !== null && $variantData['stock'] !== '' 
                            ? (int) $variantData['stock'] 
                            : null;
                    } elseif (isset($variantData['quantity'])) {
                        $stockValue = $variantData['quantity'] !== null && $variantData['quantity'] !== '' 
                            ? (int) $variantData['quantity'] 
                            : null;
                    }
                    unset($variantData['stock'], $variantData['quantity']);

                    // Garante que attributes seja um array
                    if (isset($variantData['attributes'])) {
                        if (is_string($variantData['attributes'])) {
                            $variantData['attributes'] = json_decode($variantData['attributes'], true) ?? [];
                        }
                        if (! is_array($variantData['attributes'])) {
                            $variantData['attributes'] = [];
                        }
                    } else {
                        $variantData['attributes'] = [];
                    }

                    $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;
                    unset($variantData['id']);

                    // Atualiza ou cria a variação
                    if ($variantId && in_array($variantId, $existingVariantIds, true)) {
                        $variant = $product->variants()->findOrFail($variantId);

                        if (isset($variantData['image']) && $variant->image && $variantData['image'] !== $variant->image) {
                            Storage::disk('public')->delete($variant->image);
                        }

                        $variant->update($variantData);
                    } else {
                        $variant = $product->variants()->create($variantData);
                    }

                    // Atualização de estoque: sempre atualiza se veio explicitamente (mesmo que seja 0)
                    if ($currentBranchId && $stockValue !== null) {
                        \App\Models\Inventory::updateOrCreate(
                            [
                                'branch_id' => $currentBranchId,
                                'product_variant_id' => $variant->id,
                            ],
                            [
                                'quantity' => $stockValue,
                                'min_quantity' => 0,
                            ]
                        );
                    }
                }
            } else {
                // Produto simples (sem variações): atualiza estoque da variação padrão
                // Se todas as variações foram removidas, deleta as antigas
                $existingVariants = $product->variants()->get();
                if ($existingVariants->isNotEmpty()) {
                    // Se havia variações mas não veio nenhuma no request, deleta todas
                    foreach ($existingVariants as $variantToDelete) {
                        if ($variantToDelete->image) {
                            Storage::disk('public')->delete($variantToDelete->image);
                        }
                    }
                    $product->variants()->delete();
                }
                
                // Atualiza ou cria estoque na variação padrão (se stock foi enviado)
                $hasStock = $stock !== null && $stock !== '';
                if ($hasStock && $currentBranchId) {
                    $defaultVariant = $product->variants()->first();
                    if (! $defaultVariant) {
                        // Se não tem variação padrão, cria uma
                        $this->createDefaultVariant($product, $request, (int) $stock, $currentBranchId);
                    } else {
                        // Atualiza estoque da variação padrão existente
                        \App\Models\Inventory::updateOrCreate(
                            [
                                'branch_id' => $currentBranchId,
                                'product_variant_id' => $defaultVariant->id,
                            ],
                            [
                                'quantity' => (int) $stock,
                                'min_quantity' => 0,
                            ]
                        );
                    }
                }
            }

            $product->refresh();
            $this->loadProductWithCurrentBranchStock($product, $request);

            return response()->json(new ProductResource($product));
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('products.delete');

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Create a default variant for simple products.
     */
    private function createDefaultVariant(Product $product, Request $request, int $stock = 0, ?int $branchId = null): void
    {
        $generatedSku = $this->skuGeneratorService->generate($product, ['tipo' => 'único']);

        if ($generatedSku !== null) {
            $sku = $generatedSku;
        } else {
            $baseName = Str::upper(Str::substr(Str::slug($product->name), 0, 8));
            $sku = $baseName . '-' . Str::upper(Str::random(4));
        }

        $variantData = [
            'sku' => $sku,
            'barcode' => $this->generateUniqueBarcode(),
            'price' => null,
            'image' => $product->image,
            'attributes' => ['tipo' => 'único'],
        ];

        $variant = $product->variants()->create($variantData);

        if ($branchId) {
            \App\Models\Inventory::create([
                'branch_id' => $branchId,
                'product_variant_id' => $variant->id,
                'quantity' => $stock,
                'min_quantity' => 0,
            ]);
        }
    }

    /**
     * Handle image upload for variant.
     *
     * @param array<string, mixed> $variantData
     * @return array<string, mixed>
     */
    private function handleVariantImage(Request $request, array $variantData, int $index, ?int $productId = null): array
    {
        $key = "variants.{$index}.image";
        
        if ($request->hasFile($key)) {
            $image = $request->file($key);
            $path = $image->store('product-variants', 'public');
            $variantData['image'] = $path;
        } elseif (isset($variantData['image']) && is_string($variantData['image']) && ! empty($variantData['image'])) {
            if (! str_starts_with($variantData['image'], 'product-variants/') && ! str_starts_with($variantData['image'], 'products/')) {
                $variantData['image'] = null;
            }
        } else {
            $variantData['image'] = null;
        }

        if (isset($variantData['attributes']) && is_string($variantData['attributes'])) {
            $variantData['attributes'] = json_decode($variantData['attributes'], true) ?? [];
        }

        return $variantData;
    }

    /**
     * Generate a unique EAN13 barcode (13 digits).
     */
    private function generateUniqueBarcode(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $barcode = \Faker\Factory::create()->ean13();
            $exists = \App\Models\ProductVariant::where('barcode', $barcode)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            do {
                $barcode = (string) \Faker\Factory::create()->unique()->numerify('#############');
                $exists = \App\Models\ProductVariant::where('barcode', $barcode)->exists();
            } while ($exists && strlen($barcode) === 13);
        }

        return $barcode;
    }

    /**
     * Load product with inventories filtered by current branch and add virtual attributes.
     */
    private function loadProductWithCurrentBranchStock(Product $product, Request $request): void
    {
        $currentBranchId = $this->currentBranchId($request);

        $product->load([
            'category',
            'supplier',
            'variants' => function ($query) use ($currentBranchId): void {
                $query->with(['inventories' => function ($inventoryQuery) use ($currentBranchId): void {
                    if ($currentBranchId !== null) {
                        $inventoryQuery->where('branch_id', $this->sanitizeBranchId($currentBranchId));
                    }
                }]);
            },
        ]);

        // Adiciona atributo virtual current_stock para o produto (se for simples)
        if ($product->variants->count() === 1) {
            $variant = $product->variants->first();
            $inventory = $variant->inventories->firstWhere('branch_id', $currentBranchId);
            $product->setAttribute('current_stock', $inventory?->quantity ?? 0);
        }

        // Adiciona atributo virtual current_stock para cada variação
        foreach ($product->variants as $variant) {
            $inventory = $variant->inventories->firstWhere('branch_id', $currentBranchId);
            $variant->setAttribute('current_stock', $inventory?->quantity ?? 0);
        }
    }
}
