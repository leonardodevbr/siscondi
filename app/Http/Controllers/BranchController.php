<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('products.view');

        $query = Branch::query();

        if ($request->has('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $branches = $query->orderBy('is_main', 'desc')->orderBy('name', 'asc')->paginate(15);

        return BranchResource::collection($branches)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json(new BranchResource($branch), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('products.view');

        return response()->json(new BranchResource($branch));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return response()->json(new BranchResource($branch));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('products.delete');

        if ($branch->is_main) {
            return response()->json([
                'message' => 'Não é possível deletar a filial principal.',
            ], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}
