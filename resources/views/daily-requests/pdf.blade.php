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
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            max-height: 297mm;
            margin: 0 auto;
            padding: 10mm 12mm;
            background: #fff;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 4pt;
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
            width: 20%;
            padding: 8pt;
        }
        
        .header-logo img {
            max-height: 65px;
            max-width: 100%;
        }
        
        .header-text {
            width: 60%;
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
            width: 20%;
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
            max-height: 65px;
            max-width: 100%;
            float: right;
        }
        
        /* Título do documento - Linha 2 da Tabela 1 */
        .doc-title {
            width: 80%;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            padding: 6pt;
        }
        
        .doc-year {
            width: 20%;
            text-align: right;
            font-size: 9pt;
            padding: 6pt;
            vertical-align: top;
        }
        
        /* Títulos de seção */
        .section-title {
            background: #d0d0d0;
            color: #000;
            font-weight: bold;
            font-size: 10pt;
            padding: 4pt 8pt;
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
            margin-top: 10pt;
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
            max-height: 28px; 
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
        
        @media print {
            .page {
                margin: 0;
                padding: 15mm;
            }
        }
    </style>
</head>
<body>
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
                <div>ESTADO DA BAHIA</div>
                <div class="title-line">PREFEITURA MUNICIPAL DE {{ strtoupper($municipality?->name ?? 'CAFARNAUM') }}</div>
                <div class="title-line">{{ strtoupper($department?->name ?? 'FUNDO MUNICIPAL DE ASSISTÊNCIA SOCIAL') }}</div>
                <div>CNPJ: {{ $department?->cnpj ?? '17.622.151/0001-84' }}</div>
                <div>{{ strtoupper($department?->address ?? 'AVENIDA JOÃO COSTA BRASIL, 315 – CENTRO – CAFARNAUM - BAHIA') }}</div>
                <div>EMAIL: {{ $department?->email ?? 'social@cafarnaum.ba.gov.br' }}</div>
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
            <td class="w-50" style="vertical-align: top;">
                <div><strong>Setor Solicitante:</strong> {{ strtoupper($department?->name ?? '–') }}</div>
                <div style="margin-top: 6pt;"><strong>Data da solicitação:</strong> {{ $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}</div>
            </td>
            <td class="w-50" style="vertical-align: top;">
                <div><strong>Autorização de concessão:</strong></div>
                <div style="margin-top: 6pt;">Autorizo a concessão da(s) diária(s) abaixo solicitada(s).</div>
                <div style="margin-top: 4pt;">{{ $municipality?->name ?? 'Cafarnaum' }} - Ba, {{ $dailyRequest->authorization_date?->format('d/m/Y') ?? $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}.</div>
            </td>
        </tr>
        <!-- 1ª assinatura: Responsável pela solicitação (quem REQUEREU) — uma linha -->
        <tr>
            <td class="signature-label">Responsável pela solicitação:</td>
            <td style="vertical-align: middle; padding: 2pt 6pt;">
                @if(!empty($requester_signature_url))
                    <div class="signature-line"><img src="{{ $requester_signature_url }}" alt="Assinatura" class="signature-img"></div>
                @else
                    <div class="signature-line">{{ strtoupper($dailyRequest->requester?->name ?? '–') }}</div>
                @endif
                <div class="signature-name">{{ strtoupper($dailyRequest->requester?->cargo?->name ?? $dailyRequest->requester?->role ?? 'REQUERENTE') }}</div>
            </td>
        </tr>
        <!-- 2ª assinatura: Secretário (Autorização de concessão) -->
        <tr>
            <td class="signature-label">Autorização de concessão:</td>
            <td style="vertical-align: middle; padding: 2pt 6pt;">
                @if(!empty($validator_signature_url))
                    <div class="signature-line"><img src="{{ $validator_signature_url }}" alt="Assinatura" class="signature-img"></div>
                @else
                    <div class="signature-line">{{ strtoupper($dailyRequest->validator?->name ?? '–') }}</div>
                @endif
                <div class="signature-name">{{ strtoupper($dailyRequest->validator?->cargo?->name ?? $dailyRequest->validator?->role ?? 'SECRETÁRIO') }}</div>
            </td>
        </tr>
    </table>

    <!-- TABELA 3: SERVIDOR -->
    <table>
        <!-- Linha 1: Título -->
        <tr>
            <td colspan="5" class="section-title">SERVIDOR</td>
        </tr>
        <!-- Linha 2: Beneficiário, Cargo, Matrícula -->
        <tr>
            <td class="w-20"><strong>Beneficiário:</strong></td>
            <td class="w-30">{{ strtoupper($dailyRequest->servant?->name ?? '–') }}</td>
            <td class="w-20"><strong>Cargo/função:</strong></td>
            <td class="w-15">{{ strtoupper($dailyRequest->servant?->role ?? 'SECRETÁRIO') }}</td>
            <td class="w-15"><strong>Matrícula:</strong><br>{{ $dailyRequest->servant?->registration_number ?? '–' }}</td>
        </tr>
        <!-- Linha 3: CPF, Identidade, Dados bancários (com rowspan) -->
        <tr>
            <td><strong>CPF:</strong></td>
            <td>{{ $dailyRequest->servant?->formatted_cpf ?? '–' }}</td>
            <td><strong>Identidade:</strong></td>
            <td>{{ trim(($dailyRequest->servant?->rg ?? '') . ' ' . ($dailyRequest->servant?->organ_expeditor ?? '')) ?: '–' }}</td>
            <td rowspan="2" style="vertical-align: top;"><strong>Dados bancários:</strong><br>{{ ($dailyRequest->servant?->agency_number ? 'AG. ' . $dailyRequest->servant->agency_number . ' / ' : '') . ($dailyRequest->servant?->account_number ? 'CC: ' . $dailyRequest->servant->account_number : '') ?: '–' }}</td>
        </tr>
        <!-- Linha 4: E-mail -->
        <tr>
            <td><strong>E-mail:</strong></td>
            <td colspan="3">{{ $dailyRequest->servant?->email ?? '–' }}</td>
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
            <td class="w-15"><strong>Nº DE DIÁRIAS:</strong><br>{{ number_format((float) $dailyRequest->quantity_days, 1, ',', '.') }}</td>
            <td class="w-20"><strong>V. UNITÁRIO R$:</strong><br>{{ number_format(($dailyRequest->unit_value ?? 0) / 100, 2, ',', '.') }}</td>
            <td class="w-20"><strong>V. TOTAL R$:</strong><br>{{ number_format(($dailyRequest->total_value ?? 0) / 100, 2, ',', '.') }}</td>
            <td class="w-45"><strong>FINALIDADE:</strong> Custeio de despesas com locomoção, hospedagem e alimentação.</td>
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
            <td class="w-40"><strong>Localidade(s) destino:</strong> {{ strtoupper($dailyRequest->destination_city ?? '–') }} - {{ strtoupper($dailyRequest->destination_state ?? '–') }}</td>
            <td class="w-30"><strong>Data de partida:</strong> {{ $dailyRequest->departure_date?->format('d/m/Y') ?? '–' }}</td>
            <td class="w-30"><strong>Data de retorno:</strong> {{ $dailyRequest->return_date?->format('d/m/Y') ?? '–' }}</td>
        </tr>
        <!-- Linha 3: Motivo -->
        <tr>
            <td colspan="3">
                <strong>Motivo da viagem:</strong> {{ strtoupper($dailyRequest->reason ?? '–') }}
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
            <td style="vertical-align: top;">
                <div>Autorizo o pagamento da(s) diária(s) acima mencionada(s).</div>
                <div style="margin-top: 4pt;">{{ $municipality?->name ?? 'Cafarnaum' }} – Ba, {{ $dailyRequest->payment_authorization_date?->format('d/m/Y') ?? $dailyRequest->created_at?->format('d/m/Y') ?? '–' }}.</div>
                <div style="margin-top: 10pt;">
                    @if(!empty($authorizer_signature_url))
                        <div class="signature-line"><img src="{{ $authorizer_signature_url }}" alt="Assinatura" class="signature-img"></div>
                    @else
                        <div class="signature-line">{{ strtoupper($dailyRequest->authorizer?->name ?? '–') }}</div>
                    @endif
                    <div class="signature-name">PREFEITO</div>
                </div>
            </td>
            <td style="vertical-align: top;">
                <div>Declaro para os devidos fins, que estarei afastado(a) do Município de {{ $municipality?->name ?? 'Cafarnaum' }}, em viagem a serviço/atividade de interesse da administração pública municipal, conforme consta no relatório de viagem.</div>
                <div style="margin-top: 10pt;">
                    <div class="signature-line"></div>
                    <div class="signature-name">{{ strtoupper($dailyRequest->servant?->name ?? '–') }} — SERVIDOR(A) (assinatura física no impresso)</div>
                </div>
            </td>
        </tr>
    </table>

</div>
</body>
</html>