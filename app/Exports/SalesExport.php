<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly Collection $sales
    ) {
    }

    public function collection(): Collection
    {
        return $this->sales;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Data',
            'Filial',
            'Vendedor',
            'Cliente',
            'Subtotal',
            'Desconto',
            'Total',
            'Status',
            'Métodos de Pagamento',
        ];
    }

    /**
     * @param  \App\Models\Sale  $sale
     */
    public function map($sale): array
    {
        $paymentMethods = $sale->payments->map(function ($payment) {
            $method = match ($payment->method->value) {
                'money' => 'Dinheiro',
                'credit_card' => 'Cartão de Crédito',
                'debit_card' => 'Cartão de Débito',
                'pix' => 'PIX',
                'point' => 'Mercado Pago Point',
                default => $payment->method->value,
            };

            $installments = $payment->installments > 1 ? " ({$payment->installments}x)" : '';

            return $method.$installments.' - R$ '.number_format($payment->amount, 2, ',', '.');
        })->join('; ');

        $statusLabel = match ($sale->status->value) {
            'completed' => 'Concluída',
            'pending_payment' => 'Aguardando Pagamento',
            'canceled' => 'Cancelada',
            default => $sale->status->value,
        };

        return [
            $sale->id,
            $sale->created_at->format('d/m/Y H:i'),
            $sale->branch?->name ?? '-',
            $sale->user->name,
            $sale->customer?->name ?? 'Cliente Avulso',
            'R$ '.number_format($sale->total_amount, 2, ',', '.'),
            'R$ '.number_format($sale->discount_amount, 2, ',', '.'),
            'R$ '.number_format($sale->final_amount, 2, ',', '.'),
            $statusLabel,
            $paymentMethods,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
