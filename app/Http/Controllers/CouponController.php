<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('marketing.manage');

        $query = Coupon::query();

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->string('code') . '%');
        }

        $coupons = $query->orderByDesc('created_at')->paginate(15);

        return CouponResource::collection($coupons)->response();
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data = collect($request->validated());
        $productIds = $data->pull('product_ids') ?? [];
        $categoryIds = $data->pull('category_ids') ?? [];
        $coupon = Coupon::create($data->all());
        $coupon->products()->sync($productIds);
        $coupon->categories()->sync($categoryIds);

        return response()->json(new CouponResource($coupon->load(['products', 'categories'])), 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('marketing.manage');
        $coupon->load(['products', 'categories']);

        return response()->json(new CouponResource($coupon));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $data = collect($request->validated());
        $productIds = $data->pull('product_ids');
        $categoryIds = $data->pull('category_ids');
        $coupon->update($data->all());
        if ($productIds !== null) {
            $coupon->products()->sync($productIds);
        }
        if ($categoryIds !== null) {
            $coupon->categories()->sync($categoryIds);
        }

        return response()->json(new CouponResource($coupon->load(['products', 'categories'])));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('marketing.manage');

        $coupon->delete();

        return response()->json(null, 204);
    }
}
