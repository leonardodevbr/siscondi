<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Solicitação e Autorização de Diárias</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.4;
            background: #fff;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm 18mm;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { padding: 5pt 8pt; vertical-align: top; border: 1px solid #333; font-size: 10pt; }
        .header-table td { border: none; padding: 4pt 6pt; vertical-align: middle; }
        .section-title {
            background: #8a8a8a;
            color: #fff;
            font-weight: bold;
            font-size: 11pt;
            padding: 6pt 8pt;
            text-align: center;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .w-25 { width: 25%; }
        .w-33 { width: 33.33%; }
        .w-50 { width: 50%; }
        .title-main {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 10pt 0 6pt;
            line-height: 1.2;
        }
        .header-municipality { font-size: 10pt; line-height: 1.35; }
        .header-municipality strong { font-size: 11pt; }
        .signature-line { border-bottom: 1px solid #000; margin-top: 20pt; min-height: 28pt; font-size: 10pt; }
        .signature-name { margin-top: 2pt; font-size: 10pt; }
        .label-cell { width: 32%; font-weight: bold; background: #f5f5f5; }
        .value-cell { width: 68%; }
        .mt-1 { margin-top: 3pt; }
        .mb-1 { margin-bottom: 3pt; }
        .local-data { font-size: 9pt; margin-top: 4pt; }
        img.signature-img { max-height: 32px; display: block; }
    </style>
</head>
<body>
<div class="page">

<table class="header-table">
    <tr>
        <td class="w-25 text-center" style="border: none; padding: 0 8pt 0 0;">
            @if(!empty($municipality_logo_url))
                <img src="{{ $municipality_logo_url }}" alt="Brasão" style="max-height: 65px;">
            @else
                <span style="font-size: 9pt;">BRASÃO DO MUNICÍPIO</span>
            @endif
        </td>
        <td class="w-50 text-center" style="border: none;">
            <div class="header-municipality">{{ $estado_texto ?? 'Estado' }}</div>
            <div class="header-municipality"><strong>PREFEITURA MUNICIPAL DE {{ strtoupper($municipality?->name ?? '–') }}</strong></div>
            <div class="header-municipality"><strong>{{ strtoupper($fundo_nome ?? '–') }}</strong></div>
            <div class="header-municipality">CNPJ: {{ $cnpj_fundo ?? '–' }}</div>
            <div class="header-municipality">{{ $endereco_secretaria ?? '–' }}</div>
            <div class="header-municipality">EMAIL: {{ $email_secretaria ?? '–' }}</div>
            <div class="title-main">SOLICITAÇÃO E<br>AUTORIZAÇÃO DE<br>DIÁRIAS</div>
        </td>
        <td class="w-25 text-right" style="border: none; padding: 0 0 0 8pt;">
            <div style="font-size: 10pt;">ANO EXERCÍCIO: {{ $ano_exercicio ?? date('Y') }}</div>
            @if(!empty($department_logo_url))
                <img src="{{ $department_logo_url }}" alt="Logo" style="max-height: 65px; margin-top: 6pt;">
            @else
                <span style="font-size: 9pt;">LOGO SECRETARIA</span>
            @endif
        </td>
    </tr>
</table>

<table style="margin-top: 10pt;">
    <tr>
        <td colspan="2" class="section-title">SOLICITANTE</td>
    </tr>
    <tr>
        <td class="w-50">
            <div><strong>Setor Solicitante:</strong> {{ $department->name ?? '–' }}</div>
            <div class="mt-1"><strong>Data da solicitação:</strong> {{ $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}</div>
            <div class="mt-1"><strong>Responsável pela solicitação:</strong></div>
            @if(!empty($requester_signature_url))
                <div class="signature-line"><img src="{{ $requester_signature_url }}" alt="Assinatura" class="signature-img"></div>
            @else
                <div class="signature-line">{{ $dailyRequest->requester?->name ?? '–' }}</div>
            @endif
        </td>
        <td class="w-50">
            <div><strong>Autorização de concessão:</strong></div>
            <div class="mt-1">Autorizo a concessão da(s) diária(s) abaixo solicitada(s). {{ $cidade_uf ?? '' }}, {{ $data_autorizacao ?? $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}.</div>
            @if(!empty($authorizer_signature_data))
                <div class="signature-line"><img src="{{ $authorizer_signature_data }}" alt="Assinatura" class="signature-img"></div>
            @else
                <div class="signature-line">{{ $dailyRequest->authorizer?->name ?? '–' }}</div>
            @endif
            <div class="signature-name">{{ $department?->name ?? '–' }}</div>
        </td>
    </tr>
</table>

<table style="margin-top: 8pt;">
    <tr>
        <td colspan="2" class="section-title">SERVIDOR</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Beneficiário:</strong></td>
        <td class="value-cell">{{ $dailyRequest->servant?->name ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Cargo/função:</strong></td>
        <td class="value-cell">{{ $cargo_funcao ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Matrícula:</strong></td>
        <td class="value-cell">{{ $dailyRequest->servant?->matricula ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>CPF:</strong></td>
        <td class="value-cell">{{ $dailyRequest->servant?->formatted_cpf ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Identidade:</strong></td>
        <td class="value-cell">{{ trim(($dailyRequest->servant?->rg ?? '') . ' ' . ($dailyRequest->servant?->organ_expeditor ?? '')) ?: '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>E-mail:</strong></td>
        <td class="value-cell">{{ $dailyRequest->servant?->email ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Dados bancários:</strong></td>
        <td class="value-cell">{{ ($dailyRequest->servant?->agency_number ? 'AG. ' . $dailyRequest->servant->agency_number . ' / ' : '') . ($dailyRequest->servant?->account_number ? 'CC: ' . $dailyRequest->servant->account_number : '') ?: '–' }}</td>
    </tr>
</table>

<table style="margin-top: 8pt;">
    <tr>
        <td colspan="4" class="section-title">SOLICITAÇÃO</td>
    </tr>
    <tr>
        <td class="label-cell" style="width: 15%;"><strong>N° de diárias:</strong></td>
        <td style="width: 15%;">{{ number_format((float) $dailyRequest->quantity_days, 1, ',', '') }}</td>
        <td class="label-cell" style="width: 15%;"><strong>V. unitário R$:</strong></td>
        <td style="width: 15%;">{{ number_format(($dailyRequest->unit_value ?? 0) / 100, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>V. total R$:</strong></td>
        <td>{{ number_format(($dailyRequest->total_value ?? 0) / 100, 2, ',', '.') }}</td>
        <td class="label-cell"><strong>Finalidade:</strong></td>
        <td>Custeio de despesas com locomoção, hospedagem e alimentação.</td>
    </tr>
</table>

<table style="margin-top: 8pt;">
    <tr>
        <td colspan="2" class="section-title">RELATÓRIO DE VIAGEM</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Localidade(s) destino:</strong></td>
        <td class="value-cell">{{ $dailyRequest->destination_city ?? '–' }} - {{ $dailyRequest->destination_state ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Data de partida:</strong></td>
        <td class="value-cell">{{ $dailyRequest->departure_date?->format('d/m/Y') ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Data de retorno:</strong></td>
        <td class="value-cell">{{ $dailyRequest->return_date?->format('d/m/Y') ?? '–' }}</td>
    </tr>
    <tr>
        <td class="label-cell"><strong>Motivo da viagem:</strong></td>
        <td class="value-cell">{{ $dailyRequest->reason ?? '–' }}</td>
    </tr>
</table>

<table style="margin-top: 8pt;">
    <tr>
        <td colspan="2" class="section-title">ASSINATURAS</td>
    </tr>
    <tr>
        <td class="w-50">
            <div><strong>AUTORIZAÇÃO DE PAGAMENTO:</strong></div>
            <div class="mt-1">Autorizo o pagamento da(s) diária(s) acima mencionada(s). {{ $cidade_uf ?? '' }}, {{ $data_pagamento ?? '–' }}.</div>
            @if(!empty($payer_signature_url))
                <div class="signature-line"><img src="{{ $payer_signature_url }}" alt="Assinatura" class="signature-img"></div>
            @else
                <div class="signature-line">{{ $dailyRequest->payer?->name ?? '–' }}</div>
            @endif
            <div class="signature-name">PREFEITO</div>
        </td>
        <td class="w-50">
            <div><strong>DECLARAÇÃO DO SERVIDOR:</strong></div>
            <div class="mt-1">Declaro para os devidos fins, que estarei afastado(a) do Município, em viagem a serviço/atividade de interesse da administração pública municipal, conforme consta no relatório de viagem.</div>
            <div class="signature-line">{{ $dailyRequest->servant?->name ?? '–' }}</div>
            <div class="signature-name">SERVIDOR(A)</div>
        </td>
    </tr>
</table>

</div>
</body>
</html>
