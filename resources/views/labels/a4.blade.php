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

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .labels-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(10, 1fr);
            width: 210mm;
            height: 297mm;
            gap: 0;
            padding: 10mm 7mm;
        }

        .label {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border: 1px solid #ddd;
            padding: 3mm;
            page-break-inside: avoid;
        }

        .product-name {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 1mm;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .variant-description {
            font-size: 8pt;
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
            font-size: 7pt;
            font-family: 'Courier New', monospace;
            margin-top: 1mm;
        }

        .price {
            font-size: 11pt;
            font-weight: bold;
            color: #000;
            margin-top: 2mm;
        }
    </style>
</head>
<body>
    <div class="labels-grid">
        @foreach($labels as $label)
            <div class="label">
                <div class="product-name">{{ Str::limit($label['product_name'], 30) }}</div>
                @if($label['variant_description'])
                    <div class="variant-description">{{ Str::limit($label['variant_description'], 35) }}</div>
                @endif
                <div class="barcode-container">
                    <img src="data:image/png;base64,{{ $label['barcode_image'] }}" alt="Barcode" class="barcode-image">
                    <div class="barcode-value">{{ $label['barcode_value'] }}</div>
                </div>
                <div class="price">R$ {{ number_format($label['price'], 2, ',', '.') }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
