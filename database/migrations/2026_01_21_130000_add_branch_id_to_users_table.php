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
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->nullOnDelete()
                    ->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};

