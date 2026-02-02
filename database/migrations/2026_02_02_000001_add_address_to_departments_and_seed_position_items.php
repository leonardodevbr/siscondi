<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove endereço de municipalities (se existirem)
        if (Schema::hasColumn('municipalities', 'address')) {
            Schema::table('municipalities', function (Blueprint $table): void {
                $table->dropColumn('address');
            });
        }
        
        if (Schema::hasColumn('municipalities', 'email')) {
            Schema::table('municipalities', function (Blueprint $table): void {
                $table->dropColumn('email');
            });
        }

        // Adiciona endereço a departments (se não existirem)
        Schema::table('departments', function (Blueprint $table): void {
            if (!Schema::hasColumn('departments', 'address')) {
                $table->string('address')->nullable()->comment('Endereço completo')->after('logo_path');
            }
            if (!Schema::hasColumn('departments', 'neighborhood')) {
                $table->string('neighborhood')->nullable()->comment('Bairro')->after('address');
            }
            if (!Schema::hasColumn('departments', 'zip_code')) {
                $table->string('zip_code', 10)->nullable()->comment('CEP')->after('neighborhood');
            }
            if (!Schema::hasColumn('departments', 'phone')) {
                $table->string('phone', 20)->nullable()->comment('Telefone')->after('zip_code');
            }
            if (!Schema::hasColumn('departments', 'email')) {
                $table->string('email')->nullable()->comment('E-mail')->after('phone');
            }
        });

        // Insere dados na tabela pivot legislation_item_position apenas se os legislation_items existirem
        $legislationItemsExist = DB::table('legislation_items')->whereIn('id', [1, 2, 3, 4, 5])->count() >= 5;
        $positionsExist = DB::table('positions')->whereIn('id', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19])->count() >= 19;

        if ($legislationItemsExist && $positionsExist) {
            $pivotData = [
                ['position_id' => 18, 'legislation_item_id' => 1],
                ['position_id' => 19, 'legislation_item_id' => 1],
                ['position_id' => 1, 'legislation_item_id' => 2],
                ['position_id' => 4, 'legislation_item_id' => 2],
                ['position_id' => 2, 'legislation_item_id' => 2],
                ['position_id' => 3, 'legislation_item_id' => 2],
                ['position_id' => 5, 'legislation_item_id' => 2],
                ['position_id' => 6, 'legislation_item_id' => 3],
                ['position_id' => 8, 'legislation_item_id' => 3],
                ['position_id' => 11, 'legislation_item_id' => 3],
                ['position_id' => 7, 'legislation_item_id' => 3],
                ['position_id' => 9, 'legislation_item_id' => 3],
                ['position_id' => 10, 'legislation_item_id' => 3],
                ['position_id' => 11, 'legislation_item_id' => 4],
                ['position_id' => 12, 'legislation_item_id' => 4],
                ['position_id' => 13, 'legislation_item_id' => 4],
                ['position_id' => 14, 'legislation_item_id' => 4],
                ['position_id' => 15, 'legislation_item_id' => 4],
                ['position_id' => 16, 'legislation_item_id' => 5],
                ['position_id' => 17, 'legislation_item_id' => 5],
            ];

            $timestamp = now();
            foreach ($pivotData as &$item) {
                $item['created_at'] = $timestamp;
                $item['updated_at'] = $timestamp;
            }

            // Insere apenas registros que ainda não existem
            foreach ($pivotData as $item) {
                DB::table('legislation_item_position')->insertOrIgnore($item);
            }
        }
    }

    public function down(): void
    {
        // Restaura endereço em municipalities
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->string('address')->nullable()->comment('Endereço')->after('display_state');
            $table->string('email')->nullable()->comment('E-mail')->after('address');
        });

        // Remove endereço de departments
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['address', 'neighborhood', 'zip_code', 'phone', 'email']);
        });

        // Remove dados da pivot
        DB::table('legislation_item_position')->whereIn('position_id', [
            18, 19, 1, 4, 2, 3, 5, 6, 8, 11, 7, 9, 10, 12, 13, 14, 15, 16, 17
        ])->delete();
    }
};
