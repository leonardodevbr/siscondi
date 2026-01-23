<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('cash_register_id')
                ->nullable()
                ->after('branch_id')
                ->constrained()
                ->nullOnDelete();
            
            $table->string('coupon_code')
                ->nullable()
                ->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn(['cash_register_id', 'coupon_code']);
        });
    }
};
