<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('financial.manage');

        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get();

        return ExpenseCategoryResource::collection($categories)->response();
    }
}
