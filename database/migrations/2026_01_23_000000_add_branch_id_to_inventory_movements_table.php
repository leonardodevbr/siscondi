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
        $mainBranchId = DB::table('branches')->where('is_main', true)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');

        if (! $mainBranchId) {
            throw new \RuntimeException('Nenhuma filial encontrada. Crie ao menos uma filial antes de rodar esta migration.');
        }

        Schema::table('inventory_movements', function (Blueprint $table) use ($mainBranchId): void {
            $table->unsignedBigInteger('branch_id')
                ->after('user_id')
                ->default($mainBranchId);
        });

        DB::table('inventory_movements')->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('branch_id');
        });
    }
};
