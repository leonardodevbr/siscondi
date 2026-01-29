<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_requests', function (Blueprint $table): void {
            $table->id();
            
            // Relacionamentos principais
            $table->foreignId('servant_id')->constrained('servants')->cascadeOnDelete();
            $table->string('destination_type', 255)->comment('Label do destino conforme a legislação (ex: Até 200 km, Capital Estado)');
            $table->foreignId('legislation_item_snapshot_id')->nullable()->constrained('legislation_items')->nullOnDelete()->comment('Item da legislação no momento da solicitação');
            
            // Informações da Viagem
            $table->string('destination_city');
            $table->string('destination_state', 2);
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('reason')->comment('Motivo da viagem');
            
            // Cálculo Financeiro (valores em centavos)
            $table->decimal('quantity_days', 8, 1)->comment('Quantidade de diárias (aceita meia diária)');
            $table->unsignedBigInteger('unit_value')->comment('Valor unitário da diária em centavos');
            $table->unsignedBigInteger('total_value')->comment('Valor total calculado em centavos');
            
            // Fluxo de Aprovação
            $table->enum('status', [
                'draft',        // Rascunho
                'requested',    // Solicitado
                'validated',    // Validado pelo Secretário
                'authorized',   // Concedido pelo Prefeito
                'paid',         // Pago pela Tesouraria
                'cancelled'     // Cancelado
            ])->default('draft');
            
            // Auditoria - Quem executou cada ação
            $table->foreignId('requester_id')->nullable()->constrained('users')->comment('Quem criou a solicitação');
            $table->foreignId('validator_id')->nullable()->constrained('users')->comment('Secretário que validou');
            $table->foreignId('authorizer_id')->nullable()->constrained('users')->comment('Prefeito que concedeu');
            $table->foreignId('payer_id')->nullable()->constrained('users')->comment('Tesoureiro que pagou');
            
            // Timestamps de cada ação
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_requests');
    }
};
