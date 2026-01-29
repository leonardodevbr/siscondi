<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos', function (Blueprint $table): void {
            $table->string('role', 50)->nullable()->after('name')->comment('Perfil Spatie vinculado a este cargo (admin, requester, validator, etc.)');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('cargo_id')->nullable()->after('primary_department_id')->constrained('cargos')->nullOnDelete()->comment('Cargo do usuário (define o perfil/role)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['cargo_id']);
        });
        Schema::table('cargos', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
