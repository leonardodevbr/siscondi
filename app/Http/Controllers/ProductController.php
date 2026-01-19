<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
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

        $query = Product::query()->with(['category', 'supplier']);

        if ($request->has('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->paginate(15);

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        $product->load(['category', 'supplier']);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $this->authorize('products.view');

        $product->load(['category', 'supplier']);

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('products.edit');

        $validated = $request->validate([
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'max:13', 'unique:products,barcode,' . $product->id],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sell_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'min_stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
        ]);

        $product->update($validated);
        $product->load(['category', 'supplier']);

        return response()->json($product);
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
