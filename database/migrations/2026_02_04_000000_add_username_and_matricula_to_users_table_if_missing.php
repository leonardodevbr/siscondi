<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona username e matricula à tabela users se ainda não existirem.
     * A migration principal (create_users_table) já pode conter essas colunas;
     * esta migration garante que instalações antigas também as tenham.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username')->unique()->nullable()->after('name')->comment('Nome de usuário para login');
            });
        }

        if (! Schema::hasColumn('users', 'matricula')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('matricula')->unique()->nullable()->after('username')->comment('Matrícula do servidor para login');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('username');
            });
        }

        if (Schema::hasColumn('users', 'matricula')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('matricula');
            });
        }
    }
};
