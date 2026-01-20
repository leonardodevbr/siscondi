<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id', 'created_at']);
            $table->dropColumn('product_id');
            
            $table->foreignId('product_variant_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
            
            $table->foreignId('branch_id')
                ->after('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();
            
            $table->index(['product_variant_id', 'branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['product_variant_id', 'branch_id', 'created_at']);
            $table->dropColumn(['product_variant_id', 'branch_id']);
            
            $table->foreignId('product_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
            
            $table->index(['product_id', 'created_at']);
        });
    }
};
