<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
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
            $query->where('code', 'like', '%'.$request->string('code').'%');
        }

        $coupons = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($coupons);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return response()->json($coupon, 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('marketing.manage');

        return response()->json($coupon);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());

        return response()->json($coupon);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('marketing.manage');

        $coupon->delete();

        return response()->json(null, 204);
    }
}
