<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de ações do PDV autorizadas por gerente (cancelar item, remover desconto, visualizar saldo, remover pagamento).
     */
    public function up(): void
    {
        Schema::create('manager_authorization_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('authorized_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 64);
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Índices com nomes curtos para evitar limite de 64 caracteres do MySQL
            $table->index(['sale_id', 'action'], 'mgr_auth_sale_action_idx');
            $table->index(['authorized_by_user_id', 'created_at'], 'mgr_auth_user_date_idx');
            $table->index('created_at', 'mgr_auth_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_authorization_logs');
    }
};
