<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn('max_discount_amount');
        });
    }
};
