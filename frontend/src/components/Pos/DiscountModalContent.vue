<template>
  <div class="space-y-4" @keydown="handleKeydown">
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

    <!-- Tabs -->
    <div class="flex border-b border-slate-200">
      <button
        type="button"
        :class="[
          'flex-1 py-2 px-3 text-sm font-medium border-b-2 transition-colors',
          activeTab === 'manual'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-slate-500 hover:text-slate-700',
        ]"
        @click="activeTab = 'manual'"
      >
        Desconto manual
      </button>
      <button
        type="button"
        :class="[
          'flex-1 py-2 px-3 text-sm font-medium border-b-2 transition-colors',
          activeTab === 'coupon'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-slate-500 hover:text-slate-700',
        ]"
        @click="switchToCouponTab"
      >
        Cupom promocional
      </button>
    </div>

    <!-- Aba 1: Desconto Manual -->
    <div v-show="activeTab === 'manual'" class="space-y-3">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">
          Tipo de Desconto
          <span class="ml-1 text-xs font-normal text-slate-500">[F2] Alternar</span>
        </label>
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
          ref="manualInputRef"
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

      <p class="text-xs text-slate-500 border-t border-slate-200 pt-2">
        ENTER aplicar · ESC fechar · F2 alternar tipo
      </p>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="$emit('close')">Fechar (ESC)</Button>
        <Button
          variant="primary"
          :disabled="!discountValue || discountValue <= 0"
          @click="applyManualDiscount"
        >
          Aplicar (ENTER)
        </Button>
      </div>
    </div>

    <!-- Aba 2: Cupom Promocional -->
    <div v-show="activeTab === 'coupon'" class="space-y-3">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Código do cupom</label>
        <input
          ref="couponInputRef"
          v-model="couponCode"
          type="text"
          maxlength="50"
          class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
          placeholder="Ex: VERÃO10"
          @input="couponCode = ($event.target.value || '').toUpperCase()"
        >
      </div>

      <div
        v-if="couponFeedback"
        :class="[
          'rounded-lg border p-3 text-sm',
          couponFeedback.type === 'success'
            ? 'border-green-200 bg-green-50 text-green-800'
            : 'border-red-200 bg-red-50 text-red-800',
        ]"
      >
        {{ couponFeedback.message }}
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="$emit('close')">Fechar (ESC)</Button>
        <Button
          variant="primary"
          :disabled="couponApplying || !couponCode.trim()"
          @click="applyCoupon"
        >
          <span v-if="couponApplying">Validando...</span>
          <span v-else>Validar / Aplicar</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';

const emit = defineEmits(['apply', 'close', 'coupon-applied']);

const cartStore = useCartStore();

const activeTab = ref('manual');
const manualInputRef = ref(null);
const couponInputRef = ref(null);

const discountType = ref('percentage');
const discountValue = ref(0);
const couponCode = ref('');
const couponApplying = ref(false);
const couponFeedback = ref(null);

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

function toggleDiscountType() {
  discountType.value = discountType.value === 'percentage' ? 'fixed' : 'percentage';
}

function applyManualDiscount() {
  if (!discountValue.value || discountValue.value <= 0) return;
  emit('apply', discountType.value, discountValue.value);
}

function switchToCouponTab() {
  activeTab.value = 'coupon';
  couponFeedback.value = null;
  nextTick(() => {
    couponInputRef.value?.focus();
  });
}

async function applyCoupon() {
  const code = couponCode.value.trim();
  if (!code) return;

  couponFeedback.value = null;
  couponApplying.value = true;
  try {
    await cartStore.applyCoupon(code);
    const amount = cartStore.discountAmount;
    couponFeedback.value = {
      type: 'success',
      message: `Cupom APLICADO! Desconto de ${formatCurrency(amount)}.`,
    };
    emit('coupon-applied');
  } catch (err) {
    couponFeedback.value = {
      type: 'error',
      message: err?.message ?? 'Erro ao aplicar cupom.',
    };
  } finally {
    couponApplying.value = false;
  }
}

function handleKeydown(e) {
  const key = e.key;

  if (key === 'Escape') {
    e.preventDefault();
    e.stopPropagation();
    emit('close');
    return;
  }

  if (activeTab.value === 'coupon') {
    if (key === 'Enter') {
      e.preventDefault();
      e.stopPropagation();
      if (couponCode.value.trim()) applyCoupon();
    }
    return;
  }

  if (key === 'Enter') {
    e.preventDefault();
    e.stopPropagation();
    if (discountValue.value && discountValue.value > 0) {
      applyManualDiscount();
    }
    return;
  }

  if (key === 'F2') {
    e.preventDefault();
    e.stopPropagation();
    toggleDiscountType();
    return;
  }

  if (key === 'ArrowUp' || key === 'ArrowDown') {
    const tag = document.activeElement?.tagName?.toLowerCase();
    if (tag === 'input') return;
    e.preventDefault();
    e.stopPropagation();
    toggleDiscountType();
    return;
  }
}

watch(activeTab, (tab) => {
  if (tab === 'coupon') {
    nextTick(() => couponInputRef.value?.focus());
  }
});

onMounted(() => {
  nextTick(() => {
    if (activeTab.value === 'manual') manualInputRef.value?.focus();
  });
});
</script>
