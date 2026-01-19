<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Exports\ProductsTemplateExport;
use App\Imports\ProductsImport;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        $tempPath = $file->store('temp');
        $filePath = Storage::path($tempPath);

        $data = Excel::toArray([], $filePath);
        $totalRows = count($data[0] ?? []) - 1;

        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'status' => ImportBatchStatus::PENDING,
            'total_rows' => max(0, $totalRows),
            'success_count' => 0,
            'error_count' => 0,
        ]);

        try {
            $batch->update(['status' => ImportBatchStatus::PROCESSING]);

            Excel::import(new ProductsImport($batch), $filePath);

            $batch->refresh();
            $batch->update(['status' => ImportBatchStatus::COMPLETED]);

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'message' => 'Products imported successfully',
                'batch' => $batch,
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $batch->update(['status' => ImportBatchStatus::FAILED]);
            $failures = $e->failures();

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'message' => 'Import validation failed',
                'errors' => $failures,
                'batch' => $batch,
            ], 422);
        } catch (\Exception $e) {
            $batch->update(['status' => ImportBatchStatus::FAILED]);
            
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            \Log::error('Product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'batch_id' => $batch->id,
            ]);

            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
                'batch' => $batch,
            ], 500);
        }
    }
}
