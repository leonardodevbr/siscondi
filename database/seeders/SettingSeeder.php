<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Settings::set('store_name', 'Adonai Boutique', 'string', 'general');
        Settings::set('store_email', 'venda@loja.local', 'string', 'general');
        Settings::set('payment_gateway', 'pix_dev', 'string', 'payment');
        Settings::set('enable_cash_register', true, 'boolean', 'general');
        
        Settings::set('sku_auto_generation', true, 'boolean', 'products');
        Settings::set('sku_pattern', '{NAME}-{VARIANTS}-{SEQ}', 'string', 'products');

        // Mercado Pago Point – Client ID/Secret; token gerado pelo sistema
        Settings::set('mp_client_id', '', 'string', 'integrations');
        Settings::set('mp_client_secret', '', 'string', 'integrations');
        Settings::set('mp_access_token', '', 'string', 'integrations');
        Settings::set('mp_token_expires_at', 0, 'integer', 'integrations');
        Settings::set('mp_user_id', '', 'string', 'integrations');
        Settings::set('mp_pos_id', '', 'string', 'integrations');

        // Parcelamento cartão de crédito (manual)
        Settings::set('cc_max_installments', 12, 'integer', 'payment');
        Settings::set('cc_no_interest_installments', 3, 'integer', 'payment');
        Settings::set('cc_interest_rate', '2.99', 'string', 'payment');
        Settings::set('cc_min_installment_value', '10.00', 'string', 'payment');
    }
}

