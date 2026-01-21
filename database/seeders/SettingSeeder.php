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
        Settings::set('payment_gateway', 'pix_dev', 'string', 'payment');
        Settings::set('enable_cash_register', true, 'boolean', 'general');
        
        Settings::set('sku_auto_generation', true, 'boolean', 'products');
        Settings::set('sku_pattern', '{NAME}-{VARIANTS}-{SEQ}', 'string', 'products');
    }
}

