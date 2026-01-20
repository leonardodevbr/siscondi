<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['sku']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['sku', 'barcode', 'stock_quantity', 'min_stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku')->unique()->after('description');
            $table->string('barcode', 13)->nullable()->unique()->after('sku');
            $table->unsignedInteger('stock_quantity')->default(0)->after('sell_price');
            $table->unsignedInteger('min_stock_quantity')->default(0)->after('stock_quantity');
        });
    }
};
