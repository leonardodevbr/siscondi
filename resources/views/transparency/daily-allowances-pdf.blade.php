<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portal da Transparência - Diárias e Passagens</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #000;
            padding: 10mm;
        }
        .report-header {
            text-align: center;
            margin-bottom: 14pt;
        }
        .report-header .brasao {
            display: block;
            margin: 0 auto 8pt;
            max-height: 76px;
            max-width: 153px;
        }
        .report-header .estado {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3pt;
        }
        .report-header .prefeitura {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 11pt;
            text-align: center;
            font-weight: bold;
            margin-bottom: 6pt;
        }
        .report-subtitle {
            font-size: 9pt;
            text-align: center;
            margin-bottom: 10pt;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10pt;
        }
        th, td {
            border: 1px solid #333;
            padding: 4pt;
            text-align: left;
            font-size: 7.5pt;
        }
        th {
            background: #e5e7eb;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .summary {
            margin-top: 12pt;
            padding: 8pt;
            background: #f5f5f5;
            border: 1px solid #ccc;
        }
        .summary-item { font-size: 9pt; margin-bottom: 2pt; }
    </style>
</head>
<body>
<div class="report-header">
    @if(!empty($municipality_logo_data))
        <img src="{{ $municipality_logo_data }}" alt="Brasão" class="brasao">
    @elseif(!empty($municipality_logo_url))
        <img src="{{ $municipality_logo_url }}" alt="Brasão" class="brasao">
    @endif
    <div class="estado">{{ mb_strtoupper($municipality?->display_state ?? 'ESTADO') }}</div>
    <div class="prefeitura">{{ mb_strtoupper($municipality?->display_name ?? $municipality?->name ?? 'PREFEITURA MUNICIPAL') }}</div>
</div>

<div class="report-title">Portal da Transparência - Diárias e Passagens</div>
<div class="report-subtitle">Gerado em: {{ $generated_at->format('d/m/Y H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>Gestão</th>
            <th>Servidor</th>
            <th>Matrícula</th>
            <th>Cargo</th>
            <th>Destino</th>
            <th>Data inicial</th>
            <th>Data final</th>
            <th class="text-right">Quant. diárias</th>
            <th class="text-right">Valor unit.</th>
            <th class="text-right">Valor</th>
            <th>Data liq.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $row)
        <tr>
            <td>{{ $row['gestao'] }}</td>
            <td>{{ $row['servidor'] }}</td>
            <td>{{ $row['matricula'] }}</td>
            <td>{{ $row['cargo'] }}</td>
            <td>{{ $row['destino'] }}</td>
            <td>{{ $row['data_inicial'] }}</td>
            <td>{{ $row['data_final'] }}</td>
            <td class="text-right">{{ number_format((float)$row['quant_diarias'], 1, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format(($row['valor_unitario'] ?? 0) / 100, 2, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format(($row['valor_total'] ?? 0) / 100, 2, ',', '.') }}</td>
            <td>{{ $row['data_liquidacao'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-item"><strong>Total de registros:</strong> {{ count($items) }}</div>
    <div class="summary-item"><strong>Valor total:</strong> R$ {{ number_format(($total_value ?? 0) / 100, 2, ',', '.') }}</div>
</div>

</body>
</html>
