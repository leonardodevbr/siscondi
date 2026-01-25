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
        if (DB::table('settings')->where('key', 'sales.max_discount_percent')->exists()) {
            Cache::forget(self::SETTINGS_CACHE_KEY);
            return;
        }
        DB::table('settings')->insert([
            'key' => 'sales.max_discount_percent',
            'value' => '50',
            'group' => 'sales',
            'type' => 'integer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget(self::SETTINGS_CACHE_KEY);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'sales.max_discount_percent')->delete();
    }
};
