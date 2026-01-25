<template>
  <div
    class="space-y-4"
    @keydown="handleKeydown"
  >
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

    <!-- Passo 1: Escolha do tipo (igual ao fluxo de pagamento) -->
    <div v-if="step === 'choice'" class="rounded-lg border border-slate-200 bg-white p-4">
      <p class="mb-3 text-sm font-medium text-slate-700">Tipo de desconto:</p>
      <div class="space-y-2">
        <button
          type="button"
          :class="[
            'w-full rounded border px-3 py-3 text-left text-sm transition',
            selectedChoiceIndex === 0
              ? 'border-blue-500 bg-blue-50 font-medium text-blue-800'
              : 'border-slate-300 bg-white hover:border-blue-300 text-slate-700'
          ]"
          @click="chooseManual"
        >
          <span class="font-medium">1</span> – Desconto manual (% ou R$)
        </button>
        <button
          type="button"
          :class="[
            'w-full rounded border px-3 py-3 text-left text-sm transition',
            selectedChoiceIndex === 1
              ? 'border-blue-500 bg-blue-50 font-medium text-blue-800'
              : 'border-slate-300 bg-white hover:border-blue-300 text-slate-700'
          ]"
          @click="chooseCoupon"
        >
          <span class="font-medium">2</span> – Cupom promocional
        </button>
      </div>
      <p class="mt-2 text-xs text-slate-500">Pressione 1 ou 2 para escolher • ESC para fechar</p>
    </div>

    <!-- Passo 2a: Desconto manual -->
    <div v-if="step === 'manual'" class="space-y-3">
      <div>
        <label class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-700">
          Tipo de Desconto
          <span class="text-xs font-normal text-slate-500" aria-hidden="true">[F2]</span>
        </label>
        <select
          v-model="discountType"
          aria-label="Tipo de desconto: porcentagem ou valor fixo. Pressione F2 para alternar."
          class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          @keydown="(e) => e.key === 'F2' && onF2ToggleManual(e)"
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
          :aria-label="discountType === 'percentage' ? 'Porcentagem de desconto' : 'Valor do desconto em reais'"
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

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="backToChoice">
          Voltar <span class="ml-1 text-xs text-slate-400">[ESC]</span>
        </Button>
        <Button
          variant="primary"
          :disabled="!discountValue || discountValue <= 0"
          @click="applyManualDiscount"
        >
          Aplicar <span class="ml-1 text-xs opacity-90">[ENTER]</span>
        </Button>
      </div>
    </div>

    <!-- Passo 2b: Cupom promocional -->
    <div v-if="step === 'coupon'" class="space-y-3">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Código do cupom</label>
        <input
          ref="couponInputRef"
          v-model="couponCode"
          type="text"
          maxlength="50"
          aria-label="Código do cupom promocional"
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
        role="alert"
      >
        {{ couponFeedback.message }}
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="backToChoice">
          Voltar <span class="ml-1 text-xs text-slate-400">[ESC]</span>
        </Button>
        <Button
          variant="primary"
          :disabled="couponApplying || !couponCode.trim()"
          @click="applyCoupon"
        >
          <span v-if="couponApplying">Validando...</span>
          <span v-else>Validar / Aplicar <span class="ml-1 text-xs opacity-90">[ENTER]</span></span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['apply', 'close', 'coupon-applied']);

const cartStore = useCartStore();

/** 'choice' = tela inicial; 'manual' | 'coupon' = formulário da opção escolhida */
const step = ref('choice');
const selectedChoiceIndex = ref(0);

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

function focusAndSelectManualInput() {
  const el = manualInputRef.value;
  if (el && typeof el.focus === 'function') {
    el.focus();
    el.select?.();
  }
}

function onF2ToggleManual(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
  }
  toggleDiscountType();
  nextTick(focusAndSelectManualInput);
}

function applyManualDiscount() {
  if (!discountValue.value || discountValue.value <= 0) return;
  emit('apply', discountType.value, discountValue.value);
}

function chooseManual() {
  step.value = 'manual';
  selectedChoiceIndex.value = 0;
  nextTick(focusAndSelectManualInput);
}

function chooseCoupon() {
  step.value = 'coupon';
  selectedChoiceIndex.value = 1;
  couponFeedback.value = null;
  nextTick(() => couponInputRef.value?.focus());
}

function backToChoice() {
  step.value = 'choice';
  selectedChoiceIndex.value = 0;
}

async function applyCoupon() {
  const code = couponCode.value.trim();
  if (!code) return;
  couponFeedback.value = null;
  couponApplying.value = true;
  try {
    await cartStore.applyCoupon(code);
    couponFeedback.value = {
      type: 'success',
      message: `Cupom APLICADO! Desconto de ${formatCurrency(cartStore.discountAmount)}.`,
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
    if (step.value === 'choice') {
      emit('close');
    } else {
      backToChoice();
    }
    return;
  }

  if (step.value === 'choice') {
    if (key === '1') {
      e.preventDefault();
      chooseManual();
      return;
    }
    if (key === '2') {
      e.preventDefault();
      chooseCoupon();
      return;
    }
    return;
  }

  if (step.value === 'coupon') {
    if (key === 'Enter') {
      e.preventDefault();
      e.stopPropagation();
      if (couponCode.value.trim()) applyCoupon();
    }
    return;
  }

  if (step.value === 'manual') {
    if (key === 'Enter') {
      e.preventDefault();
      e.stopPropagation();
      if (discountValue.value && discountValue.value > 0) applyManualDiscount();
      return;
    }
    if (key === 'F2') {
      onF2ToggleManual(e);
      return;
    }
    if (key === 'ArrowUp' || key === 'ArrowDown') {
      const tag = document.activeElement?.tagName?.toLowerCase();
      if (tag === 'input') return;
      e.preventDefault();
      e.stopPropagation();
      onF2ToggleManual(e);
      return;
    }
  }
}

watch(() => props.isOpen, (open) => {
  if (open) {
    step.value = 'choice';
    selectedChoiceIndex.value = 0;
    discountValue.value = 0;
    couponCode.value = '';
    couponFeedback.value = null;
  }
});

watch(step, (s) => {
  nextTick(() => {
    if (s === 'manual') focusAndSelectManualInput();
    else if (s === 'coupon') couponInputRef.value?.focus();
  });
});
</script>
