<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN usado pelo gerente para autorizar ações no PDV (ex.: cancelar item, desconto).
     * O gerente digita PIN + senha de operação; 1 busca por PIN, 1 Hash::check.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('operation_pin', 10)->nullable()->after('operation_password');
            $table->unique('operation_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['operation_pin']);
            $table->dropColumn('operation_pin');
        });
    }
};
