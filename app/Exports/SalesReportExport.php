<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesReportExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?string $startDate = null,
        private readonly ?string $endDate = null,
        private readonly ?int $userId = null,
        private readonly ?string $paymentMethod = null
    ) {
    }

    /**
     * @return Builder<Sale>
     */
    public function query(): Builder
    {
        $query = Sale::query()
            ->with(['user', 'customer', 'payments'])
            ->orderBy('created_at', 'desc');

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->paymentMethod) {
            $query->whereHas('payments', function (Builder $q) {
                $q->where('method', $this->paymentMethod);
            });
        }

        return $query;
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Data',
            'Cliente',
            'Vendedor',
            'Forma de Pagamento',
            'Valor Total',
            'Status',
        ];
    }

    /**
     * @param Sale $sale
     * @return array<string|float>
     */
    public function map($sale): array
    {
        $paymentMethods = $sale->payments
            ->map(fn ($payment) => $this->formatPaymentMethod($payment->method))
            ->unique()
            ->implode(', ');

        return [
            $sale->id,
            $sale->created_at->format('d/m/Y H:i'),
            $sale->customer?->name ?? 'Cliente Avulso',
            $sale->user->name,
            $paymentMethods ?: 'N/A',
            number_format((float) $sale->final_amount, 2, ',', '.'),
            $sale->status->value,
        ];
    }

    private function formatPaymentMethod(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::MONEY => 'Dinheiro',
            PaymentMethod::CREDIT_CARD => 'Cartão de Crédito',
            PaymentMethod::DEBIT_CARD => 'Cartão de Débito',
            PaymentMethod::PIX => 'PIX',
            PaymentMethod::STORE_CREDIT => 'Crédito Loja',
        };
    }
}
