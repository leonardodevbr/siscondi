<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            
            $table->foreignId('product_variant_id')
                ->after('sale_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
            
            $table->foreignId('product_id')
                ->after('sale_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }
};
