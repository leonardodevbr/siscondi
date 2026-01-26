<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\MercadoPagoPointService;
use Illuminate\Console\Command;

class RefreshMercadoPagoToken extends Command
{
    protected $signature = 'mp:refresh-token';

    protected $description = 'Renova o Access Token do Mercado Pago Point (expira em 180 dias). Usa Client ID/Secret salvos em settings.';

    public function handle(MercadoPagoPointService $mercadoPagoPointService): int
    {
        if ($mercadoPagoPointService->refreshToken()) {
            $this->info('Token do Mercado Pago renovado com sucesso.');

            return self::SUCCESS;
        }

        $this->warn('Client ID/Secret não configurados ou falha na renovação. Nenhuma ação realizada.');

        return self::SUCCESS;
    }
}
