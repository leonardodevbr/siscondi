<template>
  <div class="space-y-4">
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-slate-700">Subtotal:</span>
        <span class="text-lg font-semibold text-slate-900">{{ formatCurrency(totalAmount) }}</span>
      </div>
      <div v-if="discountAmount > 0" class="mt-2 flex items-center justify-between">
        <span class="text-sm font-medium text-slate-700">Desconto:</span>
        <span class="text-lg font-semibold text-red-600">- {{ formatCurrency(discountAmount) }}</span>
      </div>
      <div class="mt-2 flex items-center justify-between border-t border-slate-300 pt-2">
        <span class="text-sm font-medium text-slate-700">Total:</span>
        <span class="text-xl font-bold text-blue-600">{{ formatCurrency(finalAmount) }}</span>
      </div>
    </div>

    <div class="space-y-3">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de Desconto</label>
        <select
          v-model="discountType"
          class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="percentage">Porcentagem (%)</option>
          <option value="fixed">Valor Fixo (R$)</option>
        </select>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">
          {{ discountType === 'percentage' ? 'Porcentagem' : 'Valor' }}
        </label>
        <input
          v-model.number="discountValue"
          type="number"
          :step="discountType === 'percentage' ? 1 : 0.01"
          :min="0"
          :max="discountType === 'percentage' ? 100 : totalAmount"
          class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          :placeholder="discountType === 'percentage' ? 'Ex: 10' : 'Ex: 5.50'"
        >
      </div>

      <div v-if="discountValue > 0" class="rounded-lg border border-blue-200 bg-blue-50 p-3">
        <p class="text-xs text-slate-600">
          Desconto calculado:
          <span class="font-semibold text-blue-700">
            {{ formatCurrency(calculatedDiscount) }}
          </span>
        </p>
      </div>
    </div>

    <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
      <Button variant="outline" @click="$emit('close')">Cancelar</Button>
      <Button
        variant="primary"
        :disabled="!discountValue || discountValue <= 0"
        @click="applyDiscount"
      >
        Aplicar Desconto
      </Button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';

const emit = defineEmits(['apply', 'close']);

const cartStore = useCartStore();

const discountType = ref('percentage');
const discountValue = ref(0);

const totalAmount = computed(() => cartStore.totalAmount);
const discountAmount = computed(() => cartStore.discountAmount);
const finalAmount = computed(() => cartStore.finalAmount);

const calculatedDiscount = computed(() => {
  if (!discountValue.value || discountValue.value <= 0) return 0;
  
  if (discountType.value === 'percentage') {
    return (totalAmount.value * discountValue.value) / 100;
  }
  
  return Math.min(discountValue.value, totalAmount.value);
});

function applyDiscount() {
  if (!discountValue.value || discountValue.value <= 0) return;
  
  emit('apply', discountType.value, discountValue.value);
}
</script>
