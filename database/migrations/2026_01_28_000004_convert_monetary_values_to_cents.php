<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converte valores monetários de reais para centavos no banco.
     * Valores já em centavos não são alterados (idempotente: só multiplica se valor < 10000).
     */
    public function up(): void
    {
        if (Schema::hasTable('legislation_items')) {
            DB::table('legislation_items')->orderBy('id')->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach (['value_up_to_200km', 'value_above_200km', 'value_state_capital', 'value_other_capitals_df', 'value_exterior'] as $col) {
                        $v = (float) $row->{$col};
                        if ($v > 0 && $v < 10000) {
                            $updates[$col] = (int) round($v * 100);
                        }
                    }
                    if ($updates !== []) {
                        DB::table('legislation_items')->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        if (Schema::hasTable('daily_requests')) {
            DB::table('daily_requests')->orderBy('id')->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $uv = (float) $row->unit_value;
                    $tv = (float) $row->total_value;
                    if ($uv <= 0 && $tv <= 0) {
                        continue;
                    }
                    $newUv = ($uv > 0 && $uv < 10000) ? (int) round($uv * 100) : (int) round($uv);
                    $newTv = ($tv > 0 && $tv < 10000) ? (int) round($tv * 100) : (int) round($tv);
                    DB::table('daily_requests')->where('id', $row->id)->update([
                        'unit_value' => $newUv,
                        'total_value' => $newTv,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legislation_items')) {
            DB::table('legislation_items')->orderBy('id')->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach (['value_up_to_200km', 'value_above_200km', 'value_state_capital', 'value_other_capitals_df', 'value_exterior'] as $col) {
                        $v = (float) $row->{$col};
                        if ($v >= 100) {
                            $updates[$col] = round($v / 100, 2);
                        }
                    }
                    if ($updates !== []) {
                        DB::table('legislation_items')->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        if (Schema::hasTable('daily_requests')) {
            DB::table('daily_requests')->orderBy('id')->chunk(100, function ($rows): void {
                foreach ($rows as $row) {
                    $uv = (float) $row->unit_value;
                    $tv = (float) $row->total_value;
                    if ($uv >= 100 || $tv >= 100) {
                        DB::table('daily_requests')->where('id', $row->id)->update([
                            'unit_value' => round($uv / 100, 2),
                            'total_value' => round($tv / 100, 2),
                        ]);
                    }
                }
            });
        }
    }
};
