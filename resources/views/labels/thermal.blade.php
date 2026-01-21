<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas Térmicas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: 40mm 40mm;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            width: 40mm;
            height: 40mm;
            margin: 0;
            padding: 0;
        }

        .label {
            width: 40mm;
            height: 40mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2mm;
            page-break-inside: avoid;
        }

        .store-name {
            font-size: 6pt;
            font-weight: normal;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.5mm;
            letter-spacing: 0.3px;
        }

        .product-name {
            font-size: 7pt;
            font-weight: normal;
            margin-bottom: 0.8mm;
            line-height: 1.1;
            max-height: 1.1em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #333;
        }

        .label-details {
            font-size: 8pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 0.8mm;
            letter-spacing: 0.3px;
        }

        .barcode-container {
            margin: 0.8mm 0;
        }

        .barcode-image {
            max-width: 85%;
            max-height: 7mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .barcode-value {
            font-size: 6pt;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-top: 0.3mm;
            color: #000;
            letter-spacing: 0.3px;
        }

        .price {
            font-size: 9pt;
            font-weight: bold;
            color: #000;
            margin-top: 0.8mm;
            letter-spacing: 0.3px;
        }
    </style>
</head>
<body>
    @php
        $labelsArray = is_array($labels) ? $labels : $labels->toArray();
        $totalLabels = count($labelsArray);
    @endphp
    @foreach($labelsArray as $index => $label)
        <div class="label" @if($index < $totalLabels - 1) style="page-break-after: always;" @endif>
            <div class="store-name">{{ Str::upper($storeName) }}</div>
            <div class="product-name">{{ Str::limit($label['product_name'], 22) }}</div>
            @if(!empty($label['label_details']))
                <div class="label-details">{{ $label['label_details'] }}</div>
            @endif
            <div class="barcode-container">
                <img src="data:image/png;base64,{{ $label['barcode_image'] }}" alt="Barcode" class="barcode-image">
                <div class="barcode-value">{{ $label['barcode_value'] }}</div>
            </div>
            <div class="price">R$ {{ number_format($label['price'], 2, ',', '.') }}</div>
        </div>
    @endforeach
</body>
</html>
