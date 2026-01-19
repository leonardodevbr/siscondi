<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(15);

        return response()->json($customers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return response()->json(new CustomerResource($customer), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json(new CustomerResource($customer));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json(new CustomerResource($customer));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
