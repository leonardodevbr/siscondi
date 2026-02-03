<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Solicitação e Autorização de Diárias</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.3;
            background: #fff;
            padding: 15mm 12mm; /* AQUI - margem via padding no body */
        }

        .page {
            width: 100%;
            margin: 0;
            padding: 0; /* ZERO padding aqui */
            background: #fff;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15pt;
        }
        
        td { 
            padding: 3pt 6pt; 
            vertical-align: top; 
            border: 1px solid #000; 
            font-size: 10pt;
            line-height: 1.25;
        }
        
        .no-border {
            border: none;
        }
        
        /* Cabeçalho Principal - Tabela 1 */
        .header-table td {
            vertical-align: middle;
            text-align: center;
        }
        
        .header-logo {
            width: 15%;
            padding: 8pt;
        }
        
        .header-logo img {
            max-height: 80px;
            max-width: 100%;
        }
        
        .header-text {
            width: 65%;
            padding: 6pt;
        }
        
        .header-text div {
            line-height: 1.3;
            font-size: 9pt;
        }
        
        .header-text .title-line {
            font-size: 10pt;
            font-weight: bold;
        }
        
        .header-year {
            width: 15%;
            padding: 8pt;
            text-align: right;
            vertical-align: top;
        }
        
        .header-year-text {
            font-size: 9pt;
            text-align: right;
            margin-bottom: 8pt;
        }
        
        .header-year img {
            max-height: 80px;
            max-width: 100%;
        }
        
        /* Título do documento - Linha 2 da Tabela 1 */
        .doc-title {
            width: 77%;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            padding: 6pt;
            background-color: #bfbfbf;
        }
        
        .doc-year {
            width: 18%;
            text-align: right;
            font-size: 11pt;
            padding: 4pt;
            vertical-align: top;
            line-height: 0.9;
        }
        
        .doc-year strong {
            font-size: 9pt;
        }

        /* Títulos de seção */
        .section-title {
            background: #bfbfbf;
            color: #000;
            font-weight: bold;
            font-size: 9pt;
            padding: 3pt 8pt;
            text-align: center;
        }
        
        /* Label de assinatura: uma linha apenas */
        .signature-label {
            font-weight: bold;
            font-size: 10pt;
            padding: 2pt 6pt;
            vertical-align: middle;
            line-height: 1.2;
            width: 1%;
            white-space: nowrap;
        }
        
        /* Larguras específicas */
        .w-15 { width: 15%; }
        .w-20 { width: 20%; }
        .w-25 { width: 25%; }
        .w-30 { width: 30%; }
        .w-33 { width: 33.33%; }
        .w-35 { width: 35%; }
        .w-40 { width: 40%; }
        .w-45 { width: 45%; }
        .w-50 { width: 50%; }
        .w-60 { width: 60%; }
        
        /* Linha de assinatura - compacta para caber em 1 página */
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 36pt;
            padding-top: 2pt;
            text-align: center;
            font-size: 9pt;
        }
        
        .signature-name {
            text-align: center; 
            font-size: 9pt;
            margin-top: 1pt;
        }
        
        /* Imagem de assinatura */
        img.signature-img { 
            height:45px; 
            display: block;
            margin: 0 auto;
        }
        
        /* Texto em negrito */
        strong {
            font-weight: bold;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        .sub-table{
            border: none!important;
        }
        .sub-table td{
            margin-bottom: 0 !important;
        }
        .sub-table td.sub-table-title{
            background-color: #bfbfbf;
            text-align: center;
            border-top: 1px solid #000!important;
            border-bottom: 1px solid #000!important;
        }
    </style>
</head>
<body>
@php
    mb_internal_encoding('UTF-8');
@endphp
<div class="page">
    <!-- TABELA 1: CABEÇALHO -->
    <table class="header-table">
        <!-- Linha 1: Brasão | Dados | Logo -->
        <tr>
            <td class="header-logo">
                @if(!empty($municipality_logo_data))
                    <img src="{{ $municipality_logo_data }}" alt="Brasão">
                @elseif(!empty($municipality_logo_url))
                    <img src="{{ $municipality_logo_url }}" alt="Brasão">
                @endif
            </td>
            <td class="header-text">
                <div class="title-line">{{ mb_strtoupper($municipality?->display_state ?? 'ESTADO') }}</div>
                <div class="title-line">{{ mb_strtoupper($municipality?->display_name ?? 'MUNICÍPIO') }}</div>
                <div class="title-line">{{ mb_strtoupper($department?->fund_name ?? 'FUNDO RESPONSÁVEL') }}</div>
                <div class="title-line">CNPJ: {{ $cnpj_formatado ?? ($department?->fund_cnpj ?? '–') }}</div>
                <div class="title-line">{{ mb_strtoupper($endereco_completo_departamento ?? 'ENDEREÇO COMPLETO DO DEPARTAMENTO') }}</div>
                <div class="title-line">E-MAIL: {{ $email_secretaria ?? '–' }}</div>
            </td>
            <td class="header-year">
                @if(!empty($department_logo_data))
                    <img src="{{ $department_logo_data }}" alt="Logo Secretaria">
                @elseif(!empty($department_logo_url))
                    <img src="{{ $department_logo_url }}" alt="Logo Secretaria">
                @endif
            </td>
        </tr>
        <!-- Linha 2: Título do documento | Ano exercício -->
        <tr>
            <td colspan="2" class="doc-title">
                SOLICITAÇÃO E AUTORIZAÇÃO DE DIÁRIAS
            </td>
            <td class="doc-year">
                <strong>ANO EXERCÍCIO:</strong><br>{{ $ano_exercicio ?? date('Y') }}
            </td>
        </tr>
    </table>

    <!-- TABELA 2: SOLICITANTE -->
    <table>
        <!-- Linha 1: Título -->
        <tr>
            <td colspan="2" class="section-title">SOLICITANTE</td>
        </tr>
        <!-- Linha 2: Setor e Autorização -->
        <tr>
            <td class="w-50" style="padding:0!important;vertical-align: top;">
                <table class="sub-table">
                    <tr>
                        <td class="w-50" style="vertical-align: top;">
                            <div><strong>Setor Solicitante:</strong> {{ mb_strtoupper($department?->name ?? '–') }}</div>
                            <div style="margin-top: 6pt;"><strong>Data da solicitação:</strong> {{ $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="signature-label sub-table-title">Responsável pela solicitação:</td>
                    </tr>
                    <tr>
                        <td style="vertical-align: middle; padding: 2pt 6pt;">
                            @if(!empty($requester_signature_url))
                                <img style="margin-top: 10px;" src="{{ $requester_signature_url }}" alt="Assinatura" class="signature-img">
                                <div style="margin-top: 2px;" class="signature-line"></div>
                            @else
                                <div class="signature-line"></div>
                            @endif
                            <div class="signature-name">
                                <strong>{{ mb_strtoupper($dailyRequest->requester?->name ?? '–') }}</strong>
                                <div class="signature-cargo"><strong>{{ mb_strtoupper($dailyRequest->requester?->position?->name ?? 'RESPONSÁVEL') }}</strong></div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="w-50" style="padding:0!important; vertical-align: top;">
                <table class="sub-table">
                    <tr>
                        <td class="sub-table-title" style="border-top:none!important;">
                            <div><strong>Autorização de concessão:</strong></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">
                            <div style="margin-top: 6pt;">{{ mb_strtoupper('Autorizo a concessão da(s) diária(s) abaixo solicitada(s).') }}</div>
                            <div style="margin-top: 4pt;">{{ $municipality?->name ?? 'Cafarnaum' }} - Ba, {{ $dailyRequest->authorized_at?->format('d/m/Y') ?? $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}.</div>
                            <div>
                                <div>
                                    @if(!empty($validator_signature_url))
                                        <img src="{{ $validator_signature_url }}" alt="Assinatura" class="signature-img">
                                        <div style="margin-top: 2px;" class="signature-line"></div>
                                    @else
                                        <div class="signature-line"></div>
                                    @endif
                                    <div class="signature-name">
                                        <strong>{{ mb_strtoupper($dailyRequest->validator?->name ?? '–') }}</strong>
                                        <div class="signature-cargo"><strong>{{ mb_strtoupper($dailyRequest->validator?->position?->name ?? 'SECRETÁRIO(A)') }}</strong></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- TABELA 3: SERVIDOR -->
    <table>
        <!-- Linha 1: Título -->
        <tr>
            <td colspan="4" class="section-title">SERVIDOR</td>
        </tr>
        <!-- Linha 2: Beneficiário, Cargo (do banco vinculado ao servidor), Matrícula -->
        <tr>
            <td colspan="2"><strong>Beneficiário:</strong><br><strong>{{ mb_strtoupper($dailyRequest->servant?->name ?? '–') }}</strong></td>
            <td><strong>Cargo/função:</strong><br><strong>{{ mb_strtoupper($dailyRequest->servant?->position?->name ?? '–') }}</strong></td>
            <td class="w-15"><strong>Matrícula:</strong><br>{{ $dailyRequest->servant?->matricula ?? '–' }}</td>
        </tr>
        <!-- Linha 3: CPF, Identidade, Dados bancários (com rowspan) -->
        <tr>
            <td><strong>CPF:</strong><br>{{ $dailyRequest->servant?->formatted_cpf ?? '–' }}</td>
            <td><strong>Identidade:</strong><br>{{ trim(($dailyRequest->servant?->rg ?? '') . ' ' . ($dailyRequest->servant?->organ_expeditor ?? '')) ?: '–' }}</td>
            <td><strong>E-mail:</strong><br>{{ $dailyRequest->servant?->email ?? '–' }}</td>
            <td class="w-25" style="vertical-align: top;"><strong>Dados bancários:</strong><br>{{ ($dailyRequest->servant?->agency_number ? 'AG. ' . $dailyRequest->servant->agency_number . ' / ' : '') . ($dailyRequest->servant?->account_number ? 'CC: ' . $dailyRequest->servant->account_number : '') ?: '–' }}</td>
        </tr>
    </table>

    <!-- TABELA 4: SOLICITAÇÃO -->
    <table>
        <!-- Linha 1: Título -->
        <tr>
            <td colspan="4" class="section-title">SOLICITAÇÃO:</td>
        </tr>
        <!-- Linha 2: Nº diárias, V. unitário, V. total, Finalidade (tudo inline) -->
        <tr>
            @php
                $qDays = (float) $dailyRequest->quantity_days;
                $quantityDaysDisplay = ($qDays == floor($qDays)) ? (string) (int) $qDays : number_format($qDays, 1, ',', '.');
            @endphp
            <td class="w-20" style="text-align: center;"><strong>Nº DE DIÁRIAS:</strong><br>{{ $quantityDaysDisplay }}</td>
            <td class="w-20" style="text-align: center;"><strong>V. UNITÁRIO:</strong><br>R$ {{ number_format(($dailyRequest->unit_value ?? 0) / 100, 2, ',', '.') }}</td>
            <td class="w-20" style="text-align: center;"><strong>V. TOTAL:</strong><br>R$ {{ number_format(($dailyRequest->total_value ?? 0) / 100, 2, ',', '.') }}</td>
            <td class="w-35"><strong>FINALIDADE:</strong> {{ $dailyRequest->purpose ?? 'Custeio de despesas com locomoção, hospedagem e alimentação.' }}</td>
        </tr>
    </table>

    <!-- TABELA 5: RELATÓRIO DE VIAGEM -->
    <table>
        <!-- Linha 1: Título -->
        <tr>
            <td colspan="3" class="section-title">RELATÓRIO DE VIAGEM</td>
        </tr>
        <!-- Linha 2: Localidade, Data partida, Data retorno (tudo inline) -->
        <tr>
            <td class="w-40"><strong>Localidade(s) destino:</strong> {{ mb_strtoupper($dailyRequest->destination_city ?? '–') }} - {{ mb_strtoupper($dailyRequest->destination_state ?? '–') }}</td>
            <td class="w-30"><strong>Data de partida:</strong> {{ $dailyRequest->departure_date?->format('d/m/Y') ?? '–' }}</td>
            <td class="w-30"><strong>Data de retorno:</strong> {{ $dailyRequest->return_date?->format('d/m/Y') ?? '–' }}</td>
        </tr>
        <!-- Linha 3: Motivo -->
        <tr>
            <td colspan="3">
                <strong>Motivo da viagem:</strong> {{ mb_strtoupper($dailyRequest->reason ?? '–') }}
            </td>
        </tr>
    </table>

    <!-- TABELA 6: AUTORIZAÇÃO E DECLARAÇÃO -->
    <table>
        <!-- Linha 1: Títulos -->
        <tr>
            <td class="w-50 section-title">AUTORIZAÇÃO DE PAGAMENTO</td>
            <td class="w-50 section-title">DECLARAÇÃO DO SERVIDOR</td>
        </tr>
        <!-- Linha 2: Conteúdo - 3ª assinatura Prefeito | 4ª assinatura Beneficiário (física no impresso) -->
        <tr>
            <td style="vertical-align: top; text-align: center;">
                <div style="margin-top: 10pt;">Autorizo o pagamento da(s) diária(s) acima mencionada(s).</div>
                <div style="margin-top: 4pt;">{{ mb_strtoupper($municipality?->display ?? 'MUNICÍPIO') }} – {{ mb_strtoupper($municipality?->state ?? 'ESTADO') }}, {{ $dailyRequest->authorized_at?->format('d/m/Y') ?? $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}.</div>
                <div style="margin-top: 6pt;">
                    @if(!empty($authorizer_signature_url))
                        <img src="{{ $authorizer_signature_url }}" alt="Assinatura" class="signature-img">
                        <div style="margin-top: 2px;" class="signature-line"></div>
                    @else
                        <div class="signature-line"></div>
                    @endif
                    <div class="signature-name">
                        <strong>{{ mb_strtoupper($dailyRequest->authorizer?->name ?? '–') }}</strong>
                        <div class="signature-cargo"><strong>
                            {{ mb_strtoupper($dailyRequest->authorizer?->position?->name ?? 'PREFEITO(A)') }}
                        </strong></div>
                    </div>
                </div>
            </td>
            <td style="vertical-align: top; text-align: center;">
                <div style="margin-top: 6pt;">Declaro para os devidos fins, que estarei afastado(a) do Município, em viagem a serviço/atividade de interesse da administração pública municipal, conforme consta no relatório de viagem.</div>
                <div class="signature-line"></div>
                <div class="signature-name">
                    <strong>{{ mb_strtoupper($dailyRequest->servant?->name ?? '–') }}</strong>
                    <div class="signature-cargo"><strong>
                        {{ mb_strtoupper($dailyRequest->servant?->position?->name ?? 'SERVIDOR(A)') }}
                    </strong></div>
                </div>
            </td>
        </tr>
    </table>

</div>
</body>
</html>