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

            $hasVariants = $data['has_variants'] ?? false;

            if (empty($variants) && ! $hasVariants) {
                // Produto simples: cria variação única com dados do formulário
                $simpleAttributes = [];
                if ($request->has('simple_attributes')) {
                    $simpleAttributesJson = $request->input('simple_attributes');
                    if (is_string($simpleAttributesJson)) {
                        $simpleAttributes = json_decode($simpleAttributesJson, true) ?? [];
                    } elseif (is_array($simpleAttributesJson)) {
                        $simpleAttributes = $simpleAttributesJson;
                    }
                }

                // Monta atributos (remove valores vazios)
                $attributes = array_filter([
                    'cor' => $simpleAttributes['cor'] ?? null,
                    'tamanho' => $simpleAttributes['tamanho'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');

                // Se não tem atributos, usa padrão
                if (empty($attributes)) {
                    $attributes = ['tipo' => 'único'];
                }

                // Gera SKU se não fornecido
                $sku = $request->input('sku');
                if (empty($sku)) {
                    $generatedSku = $this->skuGeneratorService->generate($product, $attributes);
                    $sku = $generatedSku ?? strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $product->name), 0, 8)) . '-' . strtoupper(Str::random(4));
                }

                // Gera barcode se não fornecido
                $barcode = $request->input('barcode');
                if (empty($barcode)) {
                    $barcode = $this->generateUniqueBarcode();
                }

                // Para produto simples, sempre deve haver apenas uma variação
                // Deleta todas as variações existentes primeiro
                $product->variants()->delete();

                // Cria a variação única
                $variant = $product->variants()->create([
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'price' => null,
                    'image' => $product->image,
                    'attributes' => $attributes,
                ]);

                // Cria estoque na filial atual
                if ($currentBranchId) {
                    \App\Models\Inventory::updateOrCreate(
                        [
                            'branch_id' => $currentBranchId,
                            'product_variant_id' => $variant->id,
                        ],
                        [
                            'quantity' => (int) $stock,
                            'min_quantity' => 0,
                        ]
                    );
                }
            } elseif (empty($variants)) {
                // Produto com variações mas sem variações enviadas (criação inicial)
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

            $hasVariants = $data['has_variants'] ?? false;

            // Sincronização inteligente das variações
            $hasVariantsInRequest = is_array($variants) && count($variants) > 0;
            
            if ($hasVariants && $hasVariantsInRequest) {
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
                // Produto simples (sem variações): atualiza/cria variação única com dados do formulário
                $existingVariants = $product->variants()->get();
                
                // Se havia múltiplas variações mas agora é produto simples, deleta todas
                if ($existingVariants->count() > 1) {
                    foreach ($existingVariants as $variantToDelete) {
                        if ($variantToDelete->image && $variantToDelete->image !== $product->image) {
                            Storage::disk('public')->delete($variantToDelete->image);
                        }
                    }
                    $product->variants()->delete();
                }
                
                // Processa atributos simples
                $simpleAttributes = [];
                if ($request->has('simple_attributes')) {
                    $simpleAttributesJson = $request->input('simple_attributes');
                    if (is_string($simpleAttributesJson)) {
                        $simpleAttributes = json_decode($simpleAttributesJson, true) ?? [];
                    } elseif (is_array($simpleAttributesJson)) {
                        $simpleAttributes = $simpleAttributesJson;
                    }
                }

                // Monta atributos (remove valores vazios)
                $attributes = array_filter([
                    'cor' => $simpleAttributes['cor'] ?? null,
                    'tamanho' => $simpleAttributes['tamanho'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');

                // Se não tem atributos, usa padrão
                if (empty($attributes)) {
                    $attributes = ['tipo' => 'único'];
                }

                // Pega SKU e barcode do request
                $sku = $request->input('sku');
                $barcode = $request->input('barcode');

                // Se SKU não foi fornecido, tenta gerar ou usar da variação existente
                if (empty($sku)) {
                    $existingVariant = $product->variants()->first();
                    if ($existingVariant && $existingVariant->sku) {
                        $sku = $existingVariant->sku;
                    } else {
                        $generatedSku = $this->skuGeneratorService->generate($product, $attributes);
                        $sku = $generatedSku ?? strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $product->name), 0, 8)) . '-' . strtoupper(Str::random(4));
                    }
                }

                // Se barcode não foi fornecido, tenta usar da variação existente ou gera
                if (empty($barcode)) {
                    $existingVariant = $product->variants()->first();
                    if ($existingVariant && $existingVariant->barcode) {
                        $barcode = $existingVariant->barcode;
                    } else {
                        $barcode = $this->generateUniqueBarcode();
                    }
                }

                // Para produto simples, sempre deve haver apenas uma variação
                // Se já existe uma variação, atualiza; senão, cria
                $existingVariant = $product->variants()->first();
                
                if ($existingVariant) {
                    // Atualiza a variação existente
                    $existingVariant->update([
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'price' => null,
                        'image' => $product->image,
                        'attributes' => $attributes,
                    ]);
                    $variant = $existingVariant;
                } else {
                    // Cria nova variação
                    $variant = $product->variants()->create([
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'price' => null,
                        'image' => $product->image,
                        'attributes' => $attributes,
                    ]);
                }

                // Atualiza estoque na filial atual
                if ($stock !== null && $stock !== '' && $currentBranchId) {
                    \App\Models\Inventory::updateOrCreate(
                        [
                            'branch_id' => $currentBranchId,
                            'product_variant_id' => $variant->id,
                        ],
                        [
                            'quantity' => (int) $stock,
                            'min_quantity' => 0,
                        ]
                    );
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
            // Novo arquivo enviado: faz upload
            $image = $request->file($key);
            $path = $image->store('product-variants', 'public');
            $variantData['image'] = $path;
        } elseif (isset($variantData['image']) && is_string($variantData['image']) && ! empty($variantData['image'])) {
            // String enviada: valida se é caminho válido
            if (! str_starts_with($variantData['image'], 'product-variants/') && ! str_starts_with($variantData['image'], 'products/')) {
                // Se não for caminho válido, remove o campo (não atualiza)
                unset($variantData['image']);
            }
            // Se for caminho válido, mantém no array (mas isso não deveria acontecer no update)
        } else {
            // Nenhum arquivo novo e nenhum campo image no request: remove do array para preservar imagem existente
            unset($variantData['image']);
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
