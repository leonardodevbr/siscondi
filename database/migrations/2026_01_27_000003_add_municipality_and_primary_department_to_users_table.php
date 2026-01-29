<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('municipality_id')->nullable()->after('remember_token')->constrained('municipalities')->nullOnDelete();
            $table->foreignId('primary_department_id')->nullable()->after('municipality_id')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['municipality_id']);
            $table->dropForeign(['primary_department_id']);
        });
    }
};
