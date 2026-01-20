<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $data = $request->validated();
        $variants = $data['variants'] ?? [];
        $initialStock = $data['initial_stock'] ?? [];
        unset($data['variants'], $data['initial_stock']);

        $product = Product::create($data);

        if (! empty($variants)) {
            $mainBranch = \App\Models\Branch::where('is_main', true)->first();
            
            foreach ($variants as $index => $variantData) {
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
        $data = $request->validated();
        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        $product->update($data);

        if (isset($variants)) {
            $existingVariantIds = $product->variants()->pluck('id')->toArray();
            $submittedVariantIds = [];

            foreach ($variants as $variantData) {
                if (isset($variantData['id']) && in_array($variantData['id'], $existingVariantIds)) {
                    $variant = $product->variants()->find($variantData['id']);
                    $variant->update($variantData);
                    $submittedVariantIds[] = $variantData['id'];
                } else {
                    $newVariant = $product->variants()->create($variantData);
                    $submittedVariantIds[] = $newVariant->id;
                }
            }

            $variantsToDelete = array_diff($existingVariantIds, $submittedVariantIds);
            if (! empty($variantsToDelete)) {
                $product->variants()->whereIn('id', $variantsToDelete)->delete();
            }
        }

        $product->load(['category', 'supplier', 'variants.inventories']);

        return response()->json(new ProductResource($product));
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
}
