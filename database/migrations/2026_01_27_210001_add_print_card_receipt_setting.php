<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTINGS_CACHE_KEY = 'settings.all';

    public function up(): void
    {
        if (DB::table('settings')->where('key', 'print_card_receipt')->exists()) {
            Cache::forget(self::SETTINGS_CACHE_KEY);
            return;
        }
        DB::table('settings')->insert([
            'key' => 'print_card_receipt',
            'value' => '0',
            'group' => 'sales',
            'type' => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'print_card_receipt')->delete();
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }
};
