<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adiciona configurações de gateway de pagamento na tabela settings
        $settings = [
            [
                'key' => 'payment_gateway',
                'value' => 'mercadopago',
                'group' => 'payments',
                'type' => 'string',
            ],
            [
                'key' => 'pagbank_token',
                'value' => null,
                'group' => 'payments',
                'type' => 'string',
            ],
            [
                'key' => 'pagbank_sandbox',
                'value' => '0',
                'group' => 'payments',
                'type' => 'boolean',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'payment_gateway',
            'pagbank_token',
            'pagbank_sandbox',
        ])->delete();
    }
};
