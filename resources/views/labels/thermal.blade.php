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
            width: 40mm !important;
            height: 21mm !important;
            max-height: 21mm !important;
            display: block;
            text-align: center;
            padding: 0;
            page-break-inside: avoid;
            overflow: hidden;
            box-sizing: border-box;
            margin: 0;
        }

        .spacer {
            height: 1mm;
            display: block;
        }

        .product-name {
            width: 100%;
            display: block;
            font-size: 8pt;
            font-weight: bold;
            line-height: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #000;
            margin-bottom: 1px;
            padding: 0 1mm;
        }

        .label-details {
            width: 100%;
            display: block;
            font-size: 7pt;
            font-weight: normal;
            color: #666;
            line-height: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 1px;
            padding: 0 1mm;
        }

        .barcode-image {
            display: block;
            width: 35mm;
            height: 6mm;
            margin: 0.5mm auto;
            object-fit: contain;
        }

        .barcode-value {
            width: 100%;
            display: block;
            font-size: 7pt;
            font-weight: normal;
            font-family: 'Courier New', monospace;
            color: #000;
            line-height: 1;
            letter-spacing: -0.5px;
            text-align: center;
            margin-bottom: 1px;
        }

        .price {
            width: 100%;
            display: block;
            font-size: 11pt;
            font-weight: 900;
            color: #000;
            line-height: 1;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $labelsArray = is_array($labels) ? $labels : $labels->toArray();
        $totalLabels = count($labelsArray);
    @endphp
    @foreach($labelsArray as $index => $label)
        <div class="label" @if($index < $totalLabels - 1) style="page-break-after: always !important;" @endif>
            <div class="spacer"></div>

            <div class="product-name">{{ Str::limit($label['product_name'], 25) }}</div>
            @if(!empty($label['label_details']))
                <div class="label-details">{{ $label['label_details'] }}</div>
            @endif

            <img src="data:image/png;base64,{{ $label['barcode_image'] }}" alt="Barcode" class="barcode-image">
            <div class="barcode-value">{{ $label['barcode_value'] }}</div>

            <div class="price">R$ {{ number_format($label['price'], 2, ',', '.') }}</div>
        </div>
    @endforeach
</body>
</html>
