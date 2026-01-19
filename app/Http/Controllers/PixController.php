<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PixController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway
    ) {
    }

    /**
     * Generate PIX payment for a sale.
     */
    public function generate(Sale $sale): JsonResponse
    {
        $this->authorize('pos.access');

        if ($sale->status !== SaleStatus::PENDING_PAYMENT) {
            return response()->json([
                'message' => 'Sale is not in pending payment status.',
            ], 400);
        }

        $pixPayment = $sale->payments()
            ->where('method', \App\Enums\PaymentMethod::PIX)
            ->where('status', PaymentStatus::PENDING)
            ->first();

        if (! $pixPayment) {
            return response()->json([
                'message' => 'No pending PIX payment found for this sale.',
            ], 404);
        }

        $pixData = $this->paymentGateway->generatePix($sale);

        $pixPayment->update([
            'transaction_id' => $pixData['transaction_id'],
        ]);

        return response()->json([
            'emv_payload' => $pixData['emv_payload'],
            'qrcode_base64' => $pixData['qrcode_base64'],
            'transaction_id' => $pixData['transaction_id'],
        ]);
    }

    /**
     * Process webhook from payment gateway.
     */
    public function webhook(Request $request): JsonResponse
    {
        $transactionId = $this->paymentGateway->processWebhook($request->all());

        if (! $transactionId) {
            return response()->json([
                'message' => 'Invalid webhook payload.',
            ], 400);
        }

        return DB::transaction(function () use ($transactionId, $request): JsonResponse {
            $payment = Payment::where('transaction_id', $transactionId)->first();

            if (! $payment) {
                return response()->json([
                    'message' => 'Payment not found.',
                ], 404);
            }

            $sale = $payment->sale;

            $status = $request->input('status', 'paid');

            if ($status === 'paid') {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                ]);

                $sale->update([
                    'status' => SaleStatus::COMPLETED,
                ]);

                $cashRegister = CashRegister::query()
                    ->where('user_id', $sale->user_id)
                    ->where('status', CashRegisterStatus::OPEN)
                    ->first();

                if ($cashRegister) {
                    $cashRegister->transactions()->create([
                        'type' => CashRegisterTransactionType::SALE,
                        'amount' => (float) $payment->amount,
                        'description' => "Venda #{$sale->id} - PIX",
                        'sale_id' => $sale->id,
                    ]);
                }
            } elseif ($status === 'failed') {
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                ]);
            }

            return response()->json([
                'message' => 'Webhook processed successfully.',
            ]);
        });
    }
}
