<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-slate-800">Cupons de Desconto</h1>
        <p class="text-sm text-slate-500">Gerencie cupons promocionais</p>
      </div>
      <button
        type="button"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
        @click="$router.push({ name: 'coupons.create' })"
      >
        Novo Cupom
      </button>
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-slate-500">Carregando...</div>
      <div v-else-if="!list.length" class="p-8 text-center text-slate-500">Nenhum cupom cadastrado</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Código</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tipo</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Valor</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Uso</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="item in list" :key="item.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ item.code }}</td>
              <td class="px-4 py-3 text-sm text-slate-600">
                {{ item.type === 'percentage' ? 'Porcentagem' : 'Fixo' }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-600">
                {{ item.type === 'percentage' ? `${item.value}%` : formatCurrency(item.value) }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-600">
                {{ item.usage_limit != null ? `${item.used_count ?? 0} / ${item.usage_limit}` : '—' }}
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'inline-flex px-2 py-0.5 rounded text-xs font-medium',
                    item.active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600',
                  ]"
                >
                  {{ item.active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right text-sm">
                <button
                  type="button"
                  class="text-blue-600 hover:text-blue-800"
                  @click="$router.push({ name: 'coupons.edit', params: { id: item.id } })"
                >
                  Editar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';

const loading = ref(true);
const list = ref([]);

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/coupons');
    list.value = data.data ?? data ?? [];
  } catch {
    list.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
