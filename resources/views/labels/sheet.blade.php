<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas Pimaco 6180 - Carta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .labels-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .labels-table tr {
            height: 35mm;
        }

        .labels-table td {
            width: 25%;
            height: 35mm;
            padding: 1mm;
            vertical-align: middle;
            text-align: center;
            page-break-inside: avoid;
            border: 1px dashed #e5e7eb;
        }

        .store-name {
            font-size: 6pt;
            font-weight: normal;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 0.3mm;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 7pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
            line-height: 1.1;
            max-height: 1.1em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #111827;
            width: 100%;
        }

        .label-details {
            font-size: 6.5pt;
            font-weight: 600;
            color: #000;
            margin-bottom: 0.5mm;
            letter-spacing: 0.2px;
            line-height: 1;
        }

        .barcode-container {
            margin: 0.5mm 0;
        }

        .barcode-image {
            max-width: 90%;
            max-height: 6mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .barcode-value {
            font-size: 6pt;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-top: 0.2mm;
            color: #000;
            letter-spacing: 0.2px;
        }

        .price {
            font-size: 9pt;
            font-weight: 900;
            color: #000;
            margin-top: 0.5mm;
            letter-spacing: 0.2px;
        }
    </style>
</head>
<body>
    @php
        $labelsArray = is_array($labels) ? $labels : $labels->toArray();
        $totalLabels = count($labelsArray);
        $labelsPerRow = 4;
        $rowsPerPage = 8;
        $labelsPerPage = $labelsPerRow * $rowsPerPage;
        $currentIndex = 0;
    @endphp

    @while($currentIndex < $totalLabels)
        <table class="labels-table">
            @for($row = 0; $row < $rowsPerPage && $currentIndex < $totalLabels; $row++)
                <tr>
                    @for($col = 0; $col < $labelsPerRow && $currentIndex < $totalLabels; $col++)
                        <td>
                            @php $label = $labelsArray[$currentIndex]; $currentIndex++; @endphp
                            <div class="store-name">{{ Str::upper($storeName) }}</div>
                            <div class="product-name">{{ Str::limit($label['product_name'], 20) }}</div>
                            @if(!empty($label['label_details']))
                                <div class="label-details">{{ $label['label_details'] }}</div>
                            @endif
                            <div class="barcode-container">
                                <img src="data:image/png;base64,{{ $label['barcode_image'] }}" alt="Barcode" class="barcode-image">
                                <div class="barcode-value">{{ $label['barcode_value'] }}</div>
                            </div>
                            <div class="price">R$ {{ number_format($label['price'], 2, ',', '.') }}</div>
                        </td>
                    @endfor
                    @if($col < $labelsPerRow)
                        @for($emptyCol = $col; $emptyCol < $labelsPerRow; $emptyCol++)
                            <td></td>
                        @endfor
                    @endif
                </tr>
            @endfor
        </table>

        @if($currentIndex < $totalLabels)
            <div style="page-break-after: always;"></div>
        @endif
    @endwhile
</body>
</html>
