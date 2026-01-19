<?php

declare(strict_types=1);

namespace App\Services\Payment\Providers;

use App\Models\Sale;
use App\Services\Payment\PaymentGatewayInterface;

class DevPixProvider implements PaymentGatewayInterface
{
    /**
     * @return array{emv_payload: string, qrcode_base64: string, transaction_id: string}
     */
    public function generatePix(Sale $sale): array
    {
        $transactionId = 'DEV_' . uniqid('', true);

        $emvPayload = $this->generateFakeEmvPayload($sale, $transactionId);

        $qrcodeBase64 = $this->generateFakeQrCodeBase64();

        return [
            'emv_payload' => $emvPayload,
            'qrcode_base64' => $qrcodeBase64,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function processWebhook(array $payload): ?string
    {
        if (! isset($payload['transaction_id']) || ! isset($payload['status'])) {
            return null;
        }

        $status = $payload['status'];
        if (! in_array($status, ['paid', 'failed'], true)) {
            return null;
        }

        return (string) $payload['transaction_id'];
    }

    private function generateFakeEmvPayload(Sale $sale, string $transactionId): string
    {
        $amount = number_format((float) $sale->final_amount, 2, '', '');
        $merchantName = 'ADONAY SYSTEM';
        $merchantCity = 'SAO PAULO';

        return sprintf(
            '00020126580014BR.GOV.BCB.PIX01%02d%s52040000530398654%s5802BR59%02d%s6009%s62070503***6304%s',
            strlen($transactionId),
            $transactionId,
            $amount,
            strlen($merchantName),
            $merchantName,
            $merchantCity,
            $this->calculateCrc16(sprintf(
                '00020126580014BR.GOV.BCB.PIX01%02d%s52040000530398654%s5802BR59%02d%s6009%s62070503***',
                strlen($transactionId),
                $transactionId,
                $amount,
                strlen($merchantName),
                $merchantName,
                $merchantCity
            ))
        );
    }

    private function generateFakeQrCodeBase64(): string
    {
        $width = 200;
        $height = 200;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        for ($x = 0; $x < $width; $x += 20) {
            for ($y = 0; $y < $height; $y += 20) {
                if (rand(0, 1) === 1) {
                    imagefilledrectangle($image, $x, $y, $x + 18, $y + 18, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

        return base64_encode($imageData);
    }

    private function calculateCrc16(string $data): string
    {
        $crc = 0xFFFF;
        $polynomial = 0x1021;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ $polynomial;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(dechex($crc));
    }
}
