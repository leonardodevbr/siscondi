<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\NoOpenCashRegisterException;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSaleAction $createSaleAction
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('pos.access');

        $query = Sale::query()->with(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $sales = $query->latest()->paginate(15);

        return SaleResource::collection($sales)->response();
    }

    /**
     * Store a newly created sale.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->createSaleAction->execute(
                $request->validated(),
                $request->user()
            );

            return response()->json(new SaleResource($sale), 201);
        } catch (NoOpenCashRegisterException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidCouponException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('pos.access');

        $sale->load(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        return response()->json(new SaleResource($sale));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        $sale->update($request->validated());
        $sale->load(['user', 'customer', 'coupon', 'items.productVariant.product', 'payments', 'branch']);

        return response()->json(new SaleResource($sale));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale): JsonResponse
    {
        $this->authorize('pos.discount');

        $sale->update(['status' => SaleStatus::CANCELED]);

        return response()->json(new SaleResource($sale));
    }
}
