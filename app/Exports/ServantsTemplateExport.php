<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServantsTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        // Exemplo de linha para facilitar o preenchimento
        return [
            [
                'João Silva Santos',
                '123.456.789-00',
                '1234567',
                'SSP/BA',
                '123456',
                '1', // ID do cargo/posição
                '1', // ID da secretaria/departamento
                'Banco do Brasil',
                '1234-5',
                '12345-6',
                'Corrente',
                'joao@exemplo.com',
                '', // Username (vazio = gera automaticamente: primeiro.ultimo, ex: joao.santos)
                '(77) 98888-9999',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Nome Completo',
            'CPF',
            'RG',
            'Órgão Expeditor',
            'Matrícula',
            'ID Cargo/Posição',
            'ID Secretaria',
            'Banco',
            'Agência',
            'Conta',
            'Tipo de Conta',
            'E-mail',
            'Username',
            'Telefone',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']]],
        ];
    }
}
