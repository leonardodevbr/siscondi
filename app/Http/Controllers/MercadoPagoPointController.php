<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payment\MercadoPagoPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller específico para Mercado Pago Point (maquininhas físicas).
 * Não confundir com PIX Online - use PaymentController para PIX.
 */
class MercadoPagoPointController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointService $pointService
    ) {
    }

    /**
     * Lista dispositivos Point disponíveis.
     * GET /api/mp-point/devices
     */
    public function indexDevices(Request $request): JsonResponse
    {
        try {
            $devices = $this->pointService->getDevices();

            return response()->json(['devices' => $devices], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Processa pagamento via Point (maquininha).
     * POST /api/mp-point/process-payment
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'device_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $saleId = (int) $request->input('sale_id');
            $deviceId = (string) $request->input('device_id');

            $sale = \App\Models\Sale::findOrFail($saleId);
            $result = $this->pointService->createPaymentIntent($sale, $deviceId);

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifica status de uma intenção de pagamento Point.
     * GET /api/mp-point/check-status/{intentId}
     */
    public function checkStatus(Request $request, string $intentId): JsonResponse
    {
        try {
            $status = $this->pointService->getPaymentIntentStatus($intentId);

            return response()->json($status, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela uma intenção de pagamento Point.
     * POST /api/mp-point/cancel-intent
     */
    public function cancelIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'intent_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $intentId = (string) $request->input('intent_id');
            $result = $this->pointService->cancelPaymentIntent($intentId);

            return response()->json(['success' => $result], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Método store removido - não usado.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Method not implemented',
        ], 501);
    }
}
