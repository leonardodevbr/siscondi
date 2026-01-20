<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Refatorar tabela customers
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('address');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('zip_code', 10)->nullable()->after('birth_date');
            $table->string('street')->nullable()->after('zip_code');
            $table->string('number')->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('neighborhood')->nullable()->after('complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->char('state', 2)->nullable()->after('city');
        });

        // Refatorar tabela suppliers (se tiver address)
        if (Schema::hasColumn('suppliers', 'address')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'zip_code')) {
                $table->string('zip_code', 10)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('suppliers', 'street')) {
                $table->string('street')->nullable()->after('zip_code');
            }
            if (!Schema::hasColumn('suppliers', 'number')) {
                $table->string('number')->nullable()->after('street');
            }
            if (!Schema::hasColumn('suppliers', 'complement')) {
                $table->string('complement')->nullable()->after('number');
            }
            if (!Schema::hasColumn('suppliers', 'neighborhood')) {
                $table->string('neighborhood')->nullable()->after('complement');
            }
            if (!Schema::hasColumn('suppliers', 'city')) {
                $table->string('city')->nullable()->after('neighborhood');
            }
            if (!Schema::hasColumn('suppliers', 'state')) {
                $table->char('state', 2)->nullable()->after('city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter customers
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->text('address')->nullable()->after('birth_date');
        });

        // Reverter suppliers
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state']);
        });

        if (!Schema::hasColumn('suppliers', 'address')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->text('address')->nullable()->after('phone');
            });
        }
    }
};
