<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migra dados existentes de users.branch_id para a tabela pivot
        DB::statement("
            INSERT INTO branch_user (user_id, branch_id, is_primary, created_at, updated_at)
            SELECT 
                id as user_id,
                branch_id,
                true as is_primary,
                NOW() as created_at,
                NOW() as updated_at
            FROM users
            WHERE branch_id IS NOT NULL
            ON DUPLICATE KEY UPDATE is_primary = true
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove apenas os registros migrados
        DB::statement("
            DELETE bu FROM branch_user bu
            INNER JOIN users u ON bu.user_id = u.id
            WHERE bu.branch_id = u.branch_id AND bu.is_primary = true
        ");
    }
};
