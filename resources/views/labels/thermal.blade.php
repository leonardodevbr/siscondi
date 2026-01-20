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
            page-break-after: always;
            page-break-inside: avoid;
        }

        .product-name {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 1mm;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .variant-description {
            font-size: 7pt;
            color: #666;
            margin-bottom: 2mm;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .barcode-container {
            margin: 1mm 0;
        }

        .barcode-image {
            max-width: 100%;
            height: auto;
        }

        .barcode-value {
            font-size: 6pt;
            font-family: 'Courier New', monospace;
            margin-top: 1mm;
        }

        .price {
            font-size: 10pt;
            font-weight: bold;
            color: #000;
            margin-top: 2mm;
        }
    </style>
</head>
<body>
    @foreach($labels as $label)
        <div class="label">
            <div class="product-name">{{ Str::limit($label['product_name'], 25) }}</div>
            @if($label['variant_description'])
                <div class="variant-description">{{ Str::limit($label['variant_description'], 30) }}</div>
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
