<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $query = Product::query()->with(['category', 'supplier', 'variants.inventories']);

        if ($request->has('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
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
            unset($data['variants'], $data['initial_stock']);

            if ($request->hasFile('cover_image')) {
                $image = $request->file('cover_image');
                $path = $image->store('products', 'public');
                $data['image'] = $path;
            }

            $product = Product::create($data);
            $mainBranch = \App\Models\Branch::where('is_main', true)->first();

            if (empty($variants)) {
                $this->createDefaultVariant($product, $request);
            } else {
                foreach ($variants as $index => $variantData) {
                    $variantData = $this->handleVariantImage($request, $variantData, $index);
                    $variant = $product->variants()->create($variantData);

                    if (! empty($initialStock)) {
                        $stockQuantity = $initialStock[$index]['quantity'] ?? 0;
                        if ($stockQuantity > 0 && $mainBranch) {
                            \App\Models\Inventory::create([
                                'branch_id' => $mainBranch->id,
                                'product_variant_id' => $variant->id,
                                'quantity' => $stockQuantity,
                                'min_quantity' => 0,
                            ]);
                        }
                    }
                }
            }

            $product->load(['category', 'supplier', 'variants.inventories']);

            return response()->json(new ProductResource($product), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $this->authorize('products.view');

        $product->load(['category', 'supplier', 'variants.inventories']);

        return response()->json(new ProductResource($product));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return DB::transaction(function () use ($request, $product): JsonResponse {
            $data = $request->validated();
            $variants = $data['variants'] ?? [];
            unset($data['variants']);

            if ($request->hasFile('cover_image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $image = $request->file('cover_image');
                $path = $image->store('products', 'public');
                $data['image'] = $path;
            }

            $product->update($data);

            if (isset($variants)) {
                $existingVariantIds = $product->variants()->pluck('id')->toArray();
                $submittedVariantIds = [];

                foreach ($variants as $index => $variantData) {
                    $variantData = $this->handleVariantImage($request, $variantData, $index, $product->id);

                    if (isset($variantData['id']) && in_array($variantData['id'], $existingVariantIds)) {
                        $variant = $product->variants()->find($variantData['id']);
                        
                        if (isset($variantData['image']) && $variant->image && $variantData['image'] !== $variant->image) {
                            Storage::disk('public')->delete($variant->image);
                        }
                        
                        $variant->update($variantData);
                        $submittedVariantIds[] = $variantData['id'];
                    } else {
                        $newVariant = $product->variants()->create($variantData);
                        $submittedVariantIds[] = $newVariant->id;
                    }
                }

                $variantsToDelete = array_diff($existingVariantIds, $submittedVariantIds);
                if (! empty($variantsToDelete)) {
                    $variantsToDeleteModels = $product->variants()->whereIn('id', $variantsToDelete)->get();
                    foreach ($variantsToDeleteModels as $variantToDelete) {
                        if ($variantToDelete->image) {
                            Storage::disk('public')->delete($variantToDelete->image);
                        }
                    }
                    $product->variants()->whereIn('id', $variantsToDelete)->delete();
                }
            }

            $product->load(['category', 'supplier', 'variants.inventories']);

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
    private function createDefaultVariant(Product $product, Request $request): void
    {
        $baseName = Str::upper(Str::substr(Str::slug($product->name), 0, 8));
        $sku = $baseName . '-' . Str::upper(Str::random(4));

        $variantData = [
            'sku' => $sku,
            'barcode' => $this->generateUniqueBarcode(),
            'price' => null,
            'image' => $product->image,
            'attributes' => ['tipo' => 'único'],
        ];

        $variant = $product->variants()->create($variantData);

        $mainBranch = \App\Models\Branch::where('is_main', true)->first();
        if ($mainBranch) {
            \App\Models\Inventory::create([
                'branch_id' => $mainBranch->id,
                'product_variant_id' => $variant->id,
                'quantity' => 0,
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
}
