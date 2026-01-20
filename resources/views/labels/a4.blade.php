<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas A4 - Pimaco 6180</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 12mm 4mm;
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
            height: 25.4mm;
        }

        .labels-table td {
            width: 33.33%;
            height: 25.4mm;
            padding: 2mm;
            vertical-align: middle;
            text-align: center;
            page-break-inside: avoid;
            border: 1px dotted #ccc;
        }

        .product-name {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1mm;
            line-height: 1.2;
            max-height: 2.4em;
            overflow: hidden;
            word-wrap: break-word;
        }

        .variant-description {
            font-size: 7pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 1mm;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .barcode-container {
            margin: 1mm 0;
        }

        .barcode-image {
            max-width: 90%;
            max-height: 8mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .barcode-value {
            font-size: 6pt;
            font-family: 'Courier New', monospace;
            margin-top: 0.5mm;
        }

        .price {
            font-size: 10pt;
            font-weight: bold;
            color: #000;
            margin-top: 1mm;
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
                            <div class="product-name">{{ Str::limit($label['product_name'], 35) }}</div>
                            @if(!empty($label['variant_description']))
                                <div class="variant-description">{{ Str::limit($label['variant_description'], 40) }}</div>
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
