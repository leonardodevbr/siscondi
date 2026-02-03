<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Servidores</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #000;
            padding: 10mm;
        }
        h1 {
            font-size: 14pt;
            text-align: center;
            margin-bottom: 10pt;
            font-weight: bold;
        }
        .info {
            font-size: 8pt;
            text-align: center;
            margin-bottom: 15pt;
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
    </style>
</head>
<body>
@php
    mb_internal_encoding('UTF-8');
@endphp

<h1>{{ mb_strtoupper('Relatório de Servidores') }}</h1>
<div class="info">
    <div>{{ mb_strtoupper($municipality?->display_name ?? 'Município') }}</div>
    <div>Gerado em: {{ $generated_at->format('d/m/Y H:i') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>CPF</th>
            <th>Matrícula</th>
            <th>Cargo</th>
            <th>Secretaria</th>
            <th>E-mail</th>
            <th>Telefone</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($servants as $s)
        <tr>
            <td>{{ $s->name }}</td>
            <td>{{ $s->cpf }}</td>
            <td>{{ $s->matricula ?? '–' }}</td>
            <td>{{ $s->position?->name ?? '–' }}</td>
            <td>{{ $s->department?->name }}</td>
            <td>{{ $s->email ?? '–' }}</td>
            <td>{{ $s->phone ?? '–' }}</td>
            <td>{{ $s->is_active ? 'Ativo' : 'Inativo' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-item"><strong>Total de servidores:</strong> {{ count($servants) }}</div>
    <div class="summary-item"><strong>Ativos:</strong> {{ $servants->where('is_active', true)->count() }}</div>
    <div class="summary-item"><strong>Inativos:</strong> {{ $servants->where('is_active', false)->count() }}</div>
</div>

</body>
</html>
