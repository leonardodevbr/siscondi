<template>
  <Modal :is-open="isOpen" title="Saldo do Caixa" @close="$emit('close')">
    <div class="space-y-4">
      <p v-if="loading" class="text-sm text-slate-500">Carregando...</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <tbody class="divide-y divide-slate-200">
            <tr>
              <td class="py-2 pr-4 font-medium text-slate-700">Saldo Inicial (Fundo de Troco)</td>
              <td class="py-2 text-right tabular-nums text-slate-900">
                {{ formatCurrency(s.initialBalance) }}
              </td>
            </tr>
            <tr>
              <td class="py-2 pr-4 text-slate-600">(+) Total Vendas Dinheiro</td>
              <td class="py-2 text-right tabular-nums text-slate-900">
                {{ formatCurrency(s.totalCash) }}
              </td>
            </tr>
            <tr>
              <td class="py-2 pr-4 text-slate-600">(+) Total Vendas PIX</td>
              <td class="py-2 text-right tabular-nums text-slate-900">
                {{ formatCurrency(s.totalPix) }}
              </td>
            </tr>
            <tr>
              <td class="py-2 pr-4 text-slate-600">(+) Total Vendas Cartão</td>
              <td class="py-2 text-right tabular-nums text-slate-900">
                {{ formatCurrency(s.totalCard) }}
              </td>
            </tr>
            <tr>
              <td class="py-2 pr-4 text-slate-600">(+) Suprimentos / (-) Sangrias</td>
              <td class="py-2 text-right tabular-nums text-slate-900">
                <span v-if="s.supply > 0 || s.bleed > 0">
                  {{ formatCurrency(s.supply - s.bleed) }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
            </tr>
            <tr class="border-t-2 border-slate-300 font-semibold">
              <td class="py-3 pr-4 text-slate-800">(=) Total em Gaveta (Dinheiro)</td>
              <td class="py-3 text-right tabular-nums text-blue-600">
                {{ formatCurrency(s.totalInDrawer) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex justify-end border-t border-slate-200 pt-4">
        <Button variant="outline" @click="$emit('close')">Fechar</Button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { formatCurrency } from '@/utils/format';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
});

defineEmits(['close']);

const cashRegisterStore = useCashRegisterStore();

const loading = computed(() => cashRegisterStore.loading);
const s = computed(() => cashRegisterStore.balanceSummary ?? {
  initialBalance: 0,
  totalCash: 0,
  totalPix: 0,
  totalCard: 0,
  supply: 0,
  bleed: 0,
  totalInDrawer: 0,
});

watch(() => props.isOpen, (open) => {
  if (open) {
    cashRegisterStore.fetchSummary();
  }
});
</script>
