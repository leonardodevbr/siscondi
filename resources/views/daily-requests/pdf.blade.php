<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Solicitação e Autorização de Diárias</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 11pt; color: #000; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 8px; vertical-align: top; border: 1px solid #333; }
        .header-table td { border: none; padding: 8px; vertical-align: middle; }
        .section-title { background: #e5e5e5; font-weight: bold; padding: 8px; font-size: 11pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .w-33 { width: 33%; }
        .w-50 { width: 50%; }
        .title-main { font-size: 14pt; font-weight: bold; text-align: center; margin: 8px 0; }
        .signature-line { border-bottom: 1px solid #000; margin-top: 24px; min-height: 18px; }
        .mt-1 { margin-top: 4px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="w-33 text-center">
            @if($municipality?->logo_path)
                <img src="{{ storage_path('app/public/' . $municipality->logo_path) }}" alt="Brasão" style="max-height: 70px;">
            @else
                <span style="font-size: 9pt;">BRASÃO DO MUNICÍPIO</span>
            @endif
        </td>
        <td class="w-33">
            <div>{{ $estado_texto ?? 'Estado' }}</div>
            <div><strong>PREFEITURA MUNICIPAL {{ $municipality->name ?? '–' }}</strong></div>
            <div>{{ $fundo_nome ?? '–' }}</div>
            <div>CNPJ: {{ $cnpj_fundo ?? '–' }}</div>
            <div>{{ $endereco_secretaria ?? '–' }}</div>
            <div>EMAIL: {{ $email_secretaria ?? '–' }}</div>
            <div class="title-main">SOLICITAÇÃO E AUTORIZAÇÃO DE DIÁRIAS</div>
        </td>
        <td class="w-33 text-right">
            <div>ANO EXERCÍCIO: {{ $ano_exercicio ?? date('Y') }}</div>
            @if($department?->logo_path)
                <img src="{{ storage_path('app/public/' . $department->logo_path) }}" alt="Logo" style="max-height: 70px; margin-top: 8px;">
            @else
                <span style="font-size: 9pt;">LOGO BRASÃO DA SECRETARIA</span>
            @endif
        </td>
    </tr>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td colspan="2" class="section-title">SOLICITANTE</td>
    </tr>
    <tr>
        <td class="w-50">
            <div><strong>Setor Solicitante:</strong> {{ $department->name ?? '–' }}</div>
            <div class="mt-1"><strong>Data da solicitação:</strong> {{ $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}</div>
            <div class="mt-1"><strong>Responsável pela solicitação:</strong></div>
            <div class="signature-line">{{ $dailyRequest->requester?->name ?? '–' }}</div>
        </td>
        <td class="w-50">
            <div><strong>Autorização de concessão:</strong></div>
            <div class="mt-1">Autorizo a concessão da(s) diária(s) abaixo solicitada(s).</div>
            <div class="signature-line">{{ $dailyRequest->authorizer?->name ?? '–' }}</div>
            <div class="mt-1">{{ $department->name ?? '–' }}</div>
        </td>
    </tr>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td colspan="2" class="section-title">SERVIDOR</td>
    </tr>
    <tr>
        <td><strong>Beneficiário:</strong></td>
        <td>{{ $dailyRequest->servant?->name ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Cargo/função:</strong></td>
        <td>{{ $cargo_funcao ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Matrícula:</strong></td>
        <td>{{ $dailyRequest->servant?->matricula ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>CPF:</strong></td>
        <td>{{ $dailyRequest->servant?->formatted_cpf ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Identidade:</strong></td>
        <td>{{ ($dailyRequest->servant?->rg ?? '') . ' ' . ($dailyRequest->servant?->organ_expeditor ?? '') ?: '–' }}</td>
    </tr>
    <tr>
        <td><strong>E-mail:</strong></td>
        <td>{{ $dailyRequest->servant?->email ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Dados bancários:</strong></td>
        <td>{{ ($dailyRequest->servant?->agency_number ? 'AG. ' . $dailyRequest->servant->agency_number . ' / ' : '') . ($dailyRequest->servant?->account_number ? 'CC: ' . $dailyRequest->servant->account_number : '') ?: '–' }}</td>
    </tr>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td colspan="2" class="section-title">SOLICITAÇÃO</td>
    </tr>
    <tr>
        <td><strong>Nº de diárias:</strong></td>
        <td>{{ number_format((float) $dailyRequest->quantity_days, 1, ',', '') }}</td>
    </tr>
    <tr>
        <td><strong>V. unitário R$:</strong></td>
        <td>{{ number_format(($dailyRequest->unit_value ?? 0) / 100, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td><strong>V. total R$:</strong></td>
        <td>{{ number_format(($dailyRequest->total_value ?? 0) / 100, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td><strong>Finalidade:</strong></td>
        <td>Custeio de despesas com locomoção, hospedagem e alimentação.</td>
    </tr>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td colspan="2" class="section-title">RELATÓRIO DE VIAGEM</td>
    </tr>
    <tr>
        <td><strong>Localidade(s) destino:</strong></td>
        <td>{{ $dailyRequest->destination_city ?? '–' }} - {{ $dailyRequest->destination_state ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Data de partida:</strong></td>
        <td>{{ $dailyRequest->departure_date?->format('d/m/Y') ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Data de retorno:</strong></td>
        <td>{{ $dailyRequest->return_date?->format('d/m/Y') ?? '–' }}</td>
    </tr>
    <tr>
        <td><strong>Motivo da viagem:</strong></td>
        <td>{{ $dailyRequest->reason ?? '–' }}</td>
    </tr>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td colspan="2" class="section-title">ASSINATURAS</td>
    </tr>
    <tr>
        <td class="w-50">
            <div><strong>AUTORIZAÇÃO DE PAGAMENTO:</strong></div>
            <div class="mt-1">Autorizo o pagamento da(s) diária(s) acima mencionada(s).</div>
            <div class="signature-line">{{ $dailyRequest->payer?->name ?? '–' }}</div>
        </td>
        <td class="w-50">
            <div><strong>DECLARAÇÃO DO SERVIDOR:</strong></div>
            <div class="mt-1">Declaro para os devidos fins, que estarei afastado(a) do Município, em viagem a serviço/atividade de interesse da administração pública municipal, conforme consta no relatório de viagem.</div>
            <div class="signature-line">{{ $dailyRequest->servant?->name ?? '–' }}</div>
            <div class="mt-1">SERVIDOR(A)</div>
        </td>
    </tr>
</table>

</body>
</html>
