<script setup>
import { formatCurrency } from '@/utils/format';
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Painel</h2>
        <p class="text-xs text-slate-500">
          Visão geral rápida das operações da loja.
        </p>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
          Faturamento de hoje
        </p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">
          {{ formatCurrency(0) }}
        </p>
        <p class="mt-1 text-xs text-slate-400">
          Integração futura com endpoint /api/dashboard
        </p>
      </div>

      <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
          Vendas
        </p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">
          0
        </p>
      </div>

      <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
          Itens em baixo estoque
        </p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">
          0
        </p>
      </div>
    </div>
  </div>
</template>

