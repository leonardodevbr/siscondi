<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ProductsTemplateExport;
use App\Imports\ProductsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    /**
     * Download template Excel file with headers.
     */
    public function template(): BinaryFileResponse
    {
        $this->authorize('products.create');

        return Excel::download(new ProductsTemplateExport(), 'products_import_template.xlsx');
    }

    /**
     * Import products from Excel file.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('products.create');

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            Excel::import(new ProductsImport(), $request->file('file'));

            return response()->json([
                'message' => 'Products imported successfully',
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            return response()->json([
                'message' => 'Import validation failed',
                'errors' => $failures,
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
