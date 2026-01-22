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
            size: 40mm 25mm;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            width: 40mm;
            height: 25mm;
            margin: 0;
            padding: 0;
        }

        .label {
            width: 40mm;
            height: 25mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1mm;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .product-name {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 0.5mm;
            line-height: 1;
            max-height: 1em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #000;
            width: 100%;
        }

        .label-details {
            font-size: 7pt;
            font-weight: normal;
            color: #666;
            margin-bottom: 0.5mm;
            line-height: 1;
            max-height: 1em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }

        .barcode-container {
            margin: 0.5mm 0;
            width: 100%;
        }

        .barcode-image {
            max-width: 100%;
            max-height: 12mm;
            height: 12mm;
            width: auto;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }

        .barcode-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            padding: 0 1mm;
            margin-top: -1mm;
        }

        .barcode-value {
            font-size: 7pt;
            font-weight: normal;
            font-family: 'Courier New', monospace;
            color: #000;
            line-height: 1;
        }

        .price {
            font-size: 10pt;
            font-weight: 900;
            color: #000;
            line-height: 1;
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
            <div class="product-name">{{ Str::limit($label['product_name'], 25) }}</div>
            @if(!empty($label['label_details']))
                <div class="label-details">{{ $label['label_details'] }}</div>
            @endif
            <div class="barcode-container">
                <img src="data:image/png;base64,{{ $label['barcode_image'] }}" alt="Barcode" class="barcode-image">
            </div>
            <div class="barcode-footer">
                <span class="barcode-value">{{ $label['barcode_value'] }}</span>
                <span class="price">R$ {{ number_format($label['price'], 2, ',', '.') }}</span>
            </div>
        </div>
    @endforeach
</body>
</html>
