<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas Pimaco 6181</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 0;
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
            padding: 15mm 7.2mm;
        }

        .labels-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .labels-table tr {
            height: 38.1mm;
        }

        .labels-table td {
            width: 33.33%;
            height: 38.1mm;
            padding: 2.5mm;
            vertical-align: middle;
            text-align: center;
            page-break-inside: avoid;
            border: 1px dotted #ccc;
        }

        .store-name {
            font-size: 8pt;
            font-weight: normal;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 1mm;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 10pt;
            font-weight: normal;
            margin-bottom: 1.5mm;
            line-height: 1.2;
            max-height: 1.2em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #333;
        }

        .label-details {
            font-size: 9pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 1.5mm;
            letter-spacing: 0.5px;
        }

        .barcode-container {
            margin: 1.5mm 0;
        }

        .barcode-image {
            max-width: 90%;
            max-height: 10mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .barcode-value {
            font-size: 9pt;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-top: 0.5mm;
            color: #000;
            letter-spacing: 0.5px;
        }

        .price {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            margin-top: 1.5mm;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    @php
        $labelsArray = is_array($labels) ? $labels : $labels->toArray();
        $totalLabels = count($labelsArray);
        $labelsPerRow = 3;
        $rowsPerPage = 10;
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
                            <div class="product-name">{{ Str::limit($label['product_name'], 30) }}</div>
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
