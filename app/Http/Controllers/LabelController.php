<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateLabelRequest;
use App\Models\ProductVariant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;

class LabelController extends Controller
{
    /**
     * Generate labels for product variants.
     */
    public function generate(GenerateLabelRequest $request): mixed
    {
        $items = $request->input('items', []);
        $layout = $request->input('layout', 'thermal_40x25');

        $variantIds = array_column($items, 'variant_id');
        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with('product')
            ->get()
            ->keyBy('id');

        $labelData = new Collection();

        foreach ($items as $item) {
            $variant = $variants->get($item['variant_id']);

            if (! $variant) {
                continue;
            }

            $barcodeValue = $variant->barcode;
            
            if (empty($barcodeValue)) {
                $barcodeValue = $variant->sku;
            }
            $quantity = (int) $item['quantity'];

            $barcodeGenerator = new BarcodeGeneratorPNG();
            $barcodeImage = $barcodeGenerator->getBarcode(
                $barcodeValue,
                $barcodeGenerator::TYPE_CODE_128,
                2,
                50
            );
            $barcodeBase64 = base64_encode($barcodeImage);

            $price = $variant->getEffectivePrice();
            $labelDetails = (string) $variant->label_details;

            for ($i = 0; $i < $quantity; $i++) {
                $labelData->push([
                    'product_name' => $variant->product->name,
                    'label_details' => $labelDetails,
                    'barcode_value' => $barcodeValue,
                    'barcode_image' => $barcodeBase64,
                    'price' => $price,
                    'sku' => $variant->sku,
                ]);
            }
        }

        $storeName = config('app.name', 'Loja');
        
        // Define qual view usar baseado no layout
        $view = match ($layout) {
            'thermal_40x25' => 'labels.thermal',
            'pimaco_6181' => 'labels.pimaco_6181',
            'a4_compact' => 'labels.sheet',
            default => 'labels.sheet',
        };
        
        $pdf = Pdf::loadView($view, [
            'labels' => $labelData,
            'storeName' => $storeName,
        ]);

        if ($layout === 'thermal_40x25') {
            $pdf->setPaper([0, 0, 113.386, 70.866], 'portrait');
            $pdf->setOption('margin-top', 0);
            $pdf->setOption('margin-bottom', 0);
            $pdf->setOption('margin-left', 0);
            $pdf->setOption('margin-right', 0);
            $pdf->setOption('enable-local-file-access', true);
        } elseif ($layout === 'pimaco_6181') {
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 0);
            $pdf->setOption('margin-bottom', 0);
            $pdf->setOption('margin-left', 0);
            $pdf->setOption('margin-right', 0);
            $pdf->setOption('enable-local-file-access', true);
        } else {
            // a4_compact
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 5);
            $pdf->setOption('margin-bottom', 5);
            $pdf->setOption('margin-left', 5);
            $pdf->setOption('margin-right', 5);
            $pdf->setOption('enable-local-file-access', true);
        }

        return $pdf->stream('etiquetas.pdf');
    }
}
