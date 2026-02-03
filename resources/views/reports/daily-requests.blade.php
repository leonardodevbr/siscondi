<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Diárias</title>
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
            margin-bottom: 16pt;
        }
        .report-header .brasao {
            display: block;
            margin: 0 auto 10pt;
            max-height: 90px;
            max-width: 180px;
        }
        .report-header .estado {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4pt;
        }
        .report-header .prefeitura {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 12pt;
            text-align: center;
            font-weight: bold;
            margin-bottom: 4pt;
        }
        .report-info {
            font-size: 8pt;
            text-align: center;
            margin-bottom: 12pt;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 4pt;
            text-align: left;
            font-size: 8pt;
        }
        th {
            background: #e0e0e0;
            font-weight: bold;
        }
        .summary {
            margin-top: 15pt;
            padding: 8pt;
            background: #f5f5f5;
            border: 1px solid #ccc;
        }
        .summary-item {
            margin-bottom: 4pt;
            font-size: 9pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
@php
    mb_internal_encoding('UTF-8');
@endphp

<div class="report-header">
    @if(!empty($municipality_logo_data))
        <img src="{{ $municipality_logo_data }}" alt="Brasão" class="brasao">
    @elseif(!empty($municipality_logo_url))
        <img src="{{ $municipality_logo_url }}" alt="Brasão" class="brasao">
    @endif
    <div class="estado">{{ mb_strtoupper($municipality?->display_state ?? 'ESTADO') }}</div>
    <div class="prefeitura">{{ mb_strtoupper($municipality?->display_name ?? 'PREFEITURA MUNICIPAL') }}</div>
</div>

<div class="report-title">{{ mb_strtoupper('Relatório de Solicitações de Diárias') }}</div>
<div class="report-info">Gerado em: {{ $generated_at->format('d/m/Y H:i') }}</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Servidor</th>
            <th>Cargo</th>
            <th>Secretaria</th>
            <th>Destino</th>
            <th>Partida</th>
            <th>Retorno</th>
            <th>Qtd</th>
            <th class="text-right">Valor</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($requests as $req)
        <tr>
            <td>{{ $req->id }}</td>
            <td>{{ $req->created_at?->format('d/m/Y') }}</td>
            <td>{{ $req->servant?->name }}</td>
            <td>{{ $req->servant?->position?->name ?? '–' }}</td>
            <td>{{ $req->servant?->department?->name }}</td>
            <td>{{ $req->destination_city }} - {{ $req->destination_state }}</td>
            <td>{{ $req->departure_date?->format('d/m/Y') }}</td>
            <td>{{ $req->return_date?->format('d/m/Y') }}</td>
            <td class="text-center">{{ number_format((float)$req->quantity_days, 1, ',', '.') }}</td>
            <td class="text-right">R$ {{ number_format(($req->total_value ?? 0) / 100, 2, ',', '.') }}</td>
            <td>{{ $req->status?->label() ?? $req->status }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-item"><strong>Total de solicitações:</strong> {{ count($requests) }}</div>
    <div class="summary-item"><strong>Valor total:</strong> R$ {{ number_format(($total_value ?? 0) / 100, 2, ',', '.') }}</div>
</div>

</body>
</html>
