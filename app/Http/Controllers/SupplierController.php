<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $query = Supplier::query();

        if ($request->has('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->paginate(15);

        return response()->json($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return response()->json(new SupplierResource($supplier), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('products.view');

        return response()->json(new SupplierResource($supplier));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json(new SupplierResource($supplier));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('products.delete');

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully']);
    }
}
