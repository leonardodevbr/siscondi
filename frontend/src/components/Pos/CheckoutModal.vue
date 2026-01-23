<template>
  <Modal :is-open="isOpen" title="Finalizar Venda" @close="$emit('close')">
    <div class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-700">Total Venda:</span>
          <span class="text-xl font-bold text-slate-900">{{ formatCurrency(finalAmount) }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between">
          <span class="text-sm font-medium text-slate-700">Total Pago:</span>
          <span class="text-lg font-semibold text-green-600">{{ formatCurrency(totalPayments) }}</span>
        </div>
        <div class="mt-2 flex items-center justify-between border-t border-slate-300 pt-2">
          <span class="text-sm font-medium text-slate-700">Falta Pagar:</span>
          <span :class="[
            'text-lg font-bold',
            remainingAmount <= 0 ? 'text-green-600' : 'text-red-600'
          ]">
            {{ formatCurrency(remainingAmount) }}
          </span>
        </div>
        <div v-if="change > 0" class="mt-2 flex items-center justify-between border-t border-slate-300 pt-2">
          <span class="text-sm font-medium text-slate-700">Troco:</span>
          <span class="text-lg font-bold text-blue-600">{{ formatCurrency(change) }}</span>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="font-medium text-slate-700">Pagamentos:</span>
          <span class="text-xs text-slate-500">Pressione P para adicionar pagamento</span>
        </div>

        <div v-if="payments.length === 0" class="rounded border border-slate-200 bg-slate-50 p-3 text-center text-sm text-slate-500">
          Nenhum pagamento adicionado
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(payment, index) in payments"
            :key="index"
            class="flex items-center justify-between rounded border border-slate-200 bg-white p-3"
          >
            <div class="flex-1">
              <p class="text-sm font-medium text-slate-800">
                {{ formatPaymentMethod(payment.method) }}
                <span v-if="payment.installments > 1" class="text-xs text-slate-500">
                  ({{ payment.installments }}x de {{ formatCurrency(payment.amount / payment.installments) }})
                </span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-slate-700">{{ formatCurrency(payment.amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showAddPayment" class="rounded-lg border border-blue-200 bg-blue-50 p-4" @keydown.esc.stop.prevent="cancelAddPayment">
        <h4 class="mb-3 text-sm font-semibold text-slate-800">Adicionar Pagamento</h4>
        
        <div v-if="!methodSelected" class="space-y-2">
          <p class="text-xs text-slate-600 mb-2">Selecione o método de pagamento:</p>
          <div class="space-y-1">
            <button
              v-for="(method, index) in paymentMethods"
              :key="method.value"
              type="button"
              :class="[
                'w-full rounded border px-3 py-2 text-left text-sm transition',
                selectedMethodIndex === index
                  ? 'border-blue-500 bg-blue-100 font-medium'
                  : 'border-slate-300 bg-white hover:border-blue-300'
              ]"
              @click="selectMethod(method.value)"
            >
              {{ method.label }}
            </button>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div v-if="newPayment.method === 'credit_card' && !cardTypeSelected" @keydown.esc.stop="cancelAddPayment">
            <p class="text-xs text-slate-600 mb-2">Tipo de cartão:</p>
            <div class="space-y-1">
              <button
                type="button"
                :class="[
                  'w-full rounded border px-3 py-2 text-left text-sm transition',
                  selectedCardTypeIndex === 0
                    ? 'border-blue-500 bg-blue-100 font-medium'
                    : 'border-slate-300 bg-white hover:border-blue-300'
                ]"
                @click="selectCardType('debit')"
              >
                1 - Débito
              </button>
              <button
                type="button"
                :class="[
                  'w-full rounded border px-3 py-2 text-left text-sm transition',
                  selectedCardTypeIndex === 1
                    ? 'border-blue-500 bg-blue-100 font-medium'
                    : 'border-slate-300 bg-white hover:border-blue-300'
                ]"
                @click="selectCardType('credit')"
              >
                2 - Crédito
              </button>
            </div>
          </div>

          <div v-if="newPayment.method === 'credit_card' && newPayment.cardType === 'credit' && cardTypeSelected && !installmentsSelected" @keydown.esc.stop="cancelAddPayment">
            <label class="mb-1 block text-xs font-medium text-slate-700">Parcelas</label>
            <div class="space-y-1 max-h-60 overflow-y-auto">
              <button
                v-for="(n, index) in Array.from({ length: 12 }, (_, i) => i + 1)"
                :key="n"
                type="button"
                :class="[
                  'w-full rounded border px-3 py-2 text-left text-sm transition',
                  selectedInstallmentIndex === index
                    ? 'border-blue-500 bg-blue-100 font-medium'
                    : 'border-slate-300 bg-white hover:border-blue-300'
                ]"
                @click="selectInstallments(n)"
              >
                {{ n }}x de {{ formatCurrency(installmentPreview(n)) }}
              </button>
            </div>
          </div>

          <div v-if="amountInputVisible">
            <label class="mb-1 block text-xs font-medium text-slate-700">Valor</label>
            <input
              ref="amountInputRef"
              v-model="amountFormatted"
              type="text"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="0,00"
              @input="handleAmountInput"
              @keydown.enter="confirmAddPayment"
              @keydown.esc.stop="cancelAddPayment"
            >
            <p v-if="newPayment.method === 'credit_card' && newPayment.cardType === 'credit' && installmentsSelected" class="mt-1 text-xs text-slate-500">
              Valor por parcela: {{ formatCurrency(parseAmount(amountFormatted) || 0) }} | Total: {{ formatCurrency((parseAmount(amountFormatted) || 0) * newPayment.installments) }}
            </p>
          </div>

          <div class="flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="cancelAddPayment">Cancelar (ESC)</Button>
            <Button variant="primary" size="sm" @click="confirmAddPayment">Adicionar (ENTER)</Button>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="$emit('close')">Voltar (ESC)</Button>
        <Button
          variant="primary"
          :disabled="!canFinish"
          @click="$emit('finish')"
        >
          Finalizar Venda (F10)
        </Button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@/utils/format';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(['close', 'finish']);

const cartStore = useCartStore();

const showAddPayment = ref(false);
const methodSelected = ref(false);
const cardTypeSelected = ref(false);
const installmentsSelected = ref(false);
const amountInputVisible = ref(false);
const selectedMethodIndex = ref(0);
const selectedCardTypeIndex = ref(0);
const selectedInstallmentIndex = ref(0);

const amountInputRef = ref(null);
const amountFormatted = ref('');

const paymentMethods = [
  { value: 'cash', label: 'Dinheiro' },
  { value: 'credit_card', label: 'Cartão de Crédito' },
  { value: 'debit_card', label: 'Cartão de Débito' },
  { value: 'pix', label: 'PIX' },
];

const newPayment = ref({
  method: 'cash',
  amount: 0,
  installments: 1,
  cardType: null,
});

const finalAmount = computed(() => cartStore.finalAmount);
const totalPayments = computed(() => cartStore.totalPayments);
const remainingAmount = computed(() => cartStore.remainingAmount);
const canFinish = computed(() => cartStore.canFinish);
const payments = computed(() => cartStore.payments);

const change = computed(() => {
  if (newPayment.value.method === 'cash' && newPayment.value.amount > 0) {
    const total = totalPayments.value + newPayment.value.amount;
    return Math.max(0, total - finalAmount.value);
  }
  return 0;
});

watch(() => props.isOpen, (open) => {
  if (open) {
    resetPaymentForm();
    showAddPayment.value = false;
  }
});

function resetPaymentForm() {
  showAddPayment.value = false;
  methodSelected.value = false;
  cardTypeSelected.value = false;
  installmentsSelected.value = false;
  amountInputVisible.value = false;
  selectedMethodIndex.value = 0;
  selectedCardTypeIndex.value = 0;
  selectedInstallmentIndex.value = 0;
  amountFormatted.value = '';
  newPayment.value = {
    method: 'cash',
    amount: 0,
    installments: 1,
    cardType: null,
  };
}

function formatPaymentMethod(method) {
  const methods = {
    cash: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    pix: 'PIX',
  };
  return methods[method] || method;
}

function parseAmount(value) {
  if (!value) return 0;
  const cleaned = value.replace(/\./g, '').replace(',', '.');
  const num = parseFloat(cleaned);
  return isNaN(num) ? 0 : num;
}

function formatAmountInput(value) {
  const num = typeof value === 'string' ? parseFloat(value.replace(/\./g, '').replace(',', '.')) : value;
  if (isNaN(num) || num === 0) return '';
  const parts = num.toFixed(2).split('.');
  return `${parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${parts[1]}`;
}

function handleAmountInput(e) {
  const input = e.target.value;
  const cleaned = input.replace(/[^\d,]/g, '');
  const parts = cleaned.split(',');
  
  if (parts.length > 2) {
    e.target.value = amountFormatted.value;
    return;
  }
  
  if (parts.length === 2 && parts[1].length > 2) {
    parts[1] = parts[1].substring(0, 2);
  }
  
  const formatted = parts.length === 2 
    ? `${parts[0]},${parts[1]}`
    : parts[0];
  
  amountFormatted.value = formatted;
  newPayment.value.amount = parseAmount(formatted);
}

function selectMethod(method) {
  newPayment.value.method = method;
  methodSelected.value = true;
  
  if (method === 'cash' || method === 'pix' || method === 'debit_card') {
    cardTypeSelected.value = true;
    installmentsSelected.value = true;
    nextTick(() => {
      amountInputVisible.value = true;
      amountFormatted.value = formatAmountInput(String(remainingAmount.value));
      newPayment.value.amount = remainingAmount.value;
      focusAmountInput();
    });
  } else if (method === 'credit_card') {
    cardTypeSelected.value = false;
    installmentsSelected.value = false;
    newPayment.value.cardType = null;
  }
}

function selectCardType(type) {
  newPayment.value.cardType = type;
  if (type === 'debit') {
    newPayment.value.method = 'debit_card';
    cardTypeSelected.value = true;
    installmentsSelected.value = true;
    selectedCardTypeIndex.value = 0;
    nextTick(() => {
      amountInputVisible.value = true;
      amountFormatted.value = formatAmountInput(String(remainingAmount.value));
      newPayment.value.amount = remainingAmount.value;
      focusAmountInput();
    });
  } else {
    cardTypeSelected.value = true;
    installmentsSelected.value = false;
    selectedCardTypeIndex.value = 1;
    newPayment.value.installments = 1;
    selectedInstallmentIndex.value = 0;
  }
}

function selectInstallments(n) {
  newPayment.value.installments = n;
  installmentsSelected.value = true;
  selectedInstallmentIndex.value = n - 1;
  nextTick(() => {
    amountInputVisible.value = true;
    const remaining = Math.max(0, remainingAmount.value);
    const singleValue = remaining / n;
    amountFormatted.value = formatAmountInput(String(singleValue));
    newPayment.value.amount = singleValue;
    focusAmountInput();
  });
}

function installmentPreview(n) {
  if (amountFormatted.value) {
    const amount = parseAmount(amountFormatted.value);
    return amount / n;
  }
  const remaining = Math.max(0, remainingAmount.value);
  return remaining / n;
}

function focusAmountInput() {
  nextTick(() => {
    if (amountInputRef.value) {
      amountInputRef.value.focus();
      amountInputRef.value.select();
    }
  });
}

async function confirmAddPayment() {
  const amount = parseAmount(amountFormatted.value);
  
  if (!amount || amount <= 0) {
    return;
  }

  const finalAmount = newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit'
    ? amount * newPayment.installments
    : amount;

  if (finalAmount > remainingAmount.value + 0.01) {
    return;
  }

  try {
    const method = newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'debit'
      ? 'debit_card'
      : newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit'
      ? 'credit_card'
      : newPayment.value.method;

    await cartStore.addPayment(
      method,
      finalAmount,
      newPayment.value.installments
    );
    
    resetPaymentForm();
  } catch (error) {
    console.error('Erro ao adicionar pagamento:', error);
  }
}

function cancelAddPayment(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }
  resetPaymentForm();
}

function handleModalClose() {
  if (!showAddPayment.value) {
    emit('close');
  }
}

function handleKeydown(e) {
  if (!props.isOpen) return;

  if (e.key === 'Escape') {
    if (showAddPayment.value) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      cancelAddPayment(e);
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    emit('close');
    return;
  }

  if (e.key === 'F10' && canFinish.value) {
    e.preventDefault();
    emit('finish');
    return;
  }

  if (e.key === 'p' || e.key === 'P') {
    if (!showAddPayment.value) {
      e.preventDefault();
      showAddPayment.value = true;
      methodSelected.value = false;
      selectedMethodIndex.value = 0;
    }
    return;
  }

  if (!showAddPayment.value) return;

  if (!methodSelected.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedMethodIndex.value = Math.max(0, selectedMethodIndex.value - 1);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedMethodIndex.value = Math.min(paymentMethods.length - 1, selectedMethodIndex.value + 1);
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      selectMethod(paymentMethods[selectedMethodIndex.value].value);
      return;
    }
    return;
  }

  if (newPayment.value.method === 'credit_card' && !cardTypeSelected.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedCardTypeIndex.value = Math.max(0, selectedCardTypeIndex.value - 1);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedCardTypeIndex.value = Math.min(1, selectedCardTypeIndex.value + 1);
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedCardTypeIndex.value === 0) {
        selectCardType('debit');
      } else {
        selectCardType('credit');
      }
      return;
    }
    if (e.key === '1') {
      e.preventDefault();
      selectCardType('debit');
      return;
    }
    if (e.key === '2') {
      e.preventDefault();
      selectCardType('credit');
      return;
    }
    return;
  }

  if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit' && !installmentsSelected.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.max(0, selectedInstallmentIndex.value - 1);
      newPayment.value.installments = selectedInstallmentIndex.value + 1;
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.min(11, selectedInstallmentIndex.value + 1);
      newPayment.value.installments = selectedInstallmentIndex.value + 1;
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      selectInstallments(selectedInstallmentIndex.value + 1);
      return;
    }
    const num = parseInt(e.key);
    if (num >= 1 && num <= 9) {
      e.preventDefault();
      selectInstallments(num);
      return;
    }
    return;
  }

  if (amountInputVisible.value && e.key === 'Enter' && document.activeElement !== amountInputRef.value) {
    e.preventDefault();
    confirmAddPayment();
    return;
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown, true);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown, true);
});
</script>
