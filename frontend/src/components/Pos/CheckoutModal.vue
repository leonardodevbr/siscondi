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
          <span v-if="!showAddPayment && remainingAmount > 0" class="text-xs text-slate-500">Pressione P para adicionar pagamento</span>
        </div>

        <div v-if="payments.length === 0" class="rounded border border-slate-200 bg-slate-50 p-3 text-center text-sm text-slate-500">
          Nenhum pagamento adicionado
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(payment, index) in payments"
            :key="payment.id || index"
            :class="[
              'flex items-center justify-between rounded border p-3 transition',
              selectedPaymentIndex === index
                ? 'border-orange-500 bg-orange-50'
                : 'border-slate-200 bg-white'
            ]"
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
              <span v-if="selectedPaymentIndex === index && paymentRemovalAuthorized" class="text-xs font-medium text-orange-600">ENTER para remover • ESC para desativar</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showAddPayment" class="rounded-lg border border-blue-200 bg-blue-50 p-4" @keydown.esc.stop.prevent="cancelAddPayment">
        <h4 class="mb-3 text-sm font-semibold text-slate-800">Adicionar Pagamento</h4>
        
        <div v-if="!amountConfirmed" class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Valor</label>
            <input
              ref="amountInputRef"
              v-model="amountFormatted"
              type="text"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="0,00"
              @input="handleAmountInput"
              @keydown.enter="confirmAmountAndShowMethods"
              @keydown.esc.stop="cancelAddPayment"
            >
            <p class="mt-1 text-xs text-slate-500">Pressione ENTER para continuar</p>
          </div>
        </div>

        <div v-else-if="!methodSelected" class="space-y-2">
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
              {{ index + 1 }} - {{ method.label }}
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
            <div ref="installmentsListRef" class="space-y-1 max-h-60 overflow-y-auto">
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
                {{ n }} - {{ n }}x de {{ formatCurrency(installmentPreview(n)) }}
              </button>
            </div>
          </div>

          <div v-if="installmentsSelected || (newPayment.method === 'cash' || newPayment.method === 'pix') || (newPayment.method === 'debit_card')" class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
              <p class="text-sm font-medium text-slate-700">Resumo:</p>
              <p class="mt-1 text-base font-semibold text-slate-900">{{ paymentSummary }}</p>
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" @click="cancelAddPayment">Cancelar (ESC)</Button>
              <Button variant="primary" size="sm" @click="confirmAddPayment">Adicionar (ENTER)</Button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isFinishing" class="border-t border-slate-200 pt-4">
        <div class="flex flex-col items-center justify-center space-y-4 py-8">
          <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
          <p class="text-sm font-medium text-slate-700">Processando a compra...</p>
        </div>
      </div>
      <div v-else class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button
          v-if="!canFinish"
          variant="outline"
          @click="$emit('close')"
        >
          Voltar (ESC)
        </Button>
        <Button
          variant="primary"
          :disabled="!canFinish"
          @click="onFinalizeClick"
        >
          Finalizar Venda (F10)
        </Button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import { useToast } from 'vue-toastification';
import { useCartStore } from '@/stores/cart';
import { formatCurrency } from '@/utils/format';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';

const toast = useToast();

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
const amountConfirmed = ref(false);
const selectedMethodIndex = ref(0);
const selectedCardTypeIndex = ref(0);
const selectedInstallmentIndex = ref(0);
const selectedPaymentIndex = ref(-1);
const isFinishing = ref(false);
const paymentRemovalAuthorized = ref(false);

const amountInputRef = ref(null);
const installmentsListRef = ref(null);
const amountFormatted = ref('');

const paymentMethods = [
  { value: 'cash', label: 'Dinheiro' },
  { value: 'card', label: 'Cartão' },
  { value: 'pix', label: 'PIX' },
];

const newPayment = ref({
  method: 'cash',
  amount: 0,
  installments: 1,
  cardType: null,
});

const finalAmount = computed(() => {
  const val = cartStore.finalAmount;
  return typeof val === 'string' ? parseFloat(val) : val;
});
const totalPayments = computed(() => {
  const val = cartStore.totalPayments;
  return typeof val === 'string' ? parseFloat(val) : val;
});
const remainingAmount = computed(() => {
  const val = cartStore.remainingAmount;
  return typeof val === 'string' ? parseFloat(val) : val;
});
const canFinish = computed(() => cartStore.canFinish);
const payments = computed(() => cartStore.payments);

const change = computed(() => {
  if (newPayment.value.method === 'cash' && newPayment.value.amount > 0) {
    const total = totalPayments.value + newPayment.value.amount;
    return Math.max(0, total - finalAmount.value);
  }
  return 0;
});

const paymentSummary = computed(() => {
  const amount = newPayment.value.amount || 0;
  if (amount <= 0) return '';
  
  if (newPayment.value.method === 'cash' || newPayment.value.method === 'money') {
    return `R$ ${formatCurrency(amount)} em Espécie`;
  }
  
  if (newPayment.value.method === 'pix') {
    return `R$ ${formatCurrency(amount)} no PIX`;
  }
  
  if (newPayment.value.method === 'debit_card') {
    return `R$ ${formatCurrency(amount)} no Cartão (Débito)`;
  }
  
  if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit') {
    const installments = newPayment.value.installments || 1;
    if (installments > 1) {
      return `R$ ${formatCurrency(amount)} em ${installments}x no Cartão`;
    }
    return `R$ ${formatCurrency(amount)} no Cartão (Crédito)`;
  }
  
  return `R$ ${formatCurrency(amount)}`;
});

watch(() => props.isOpen, (open) => {
  if (open) {
    resetPaymentForm();
    checkAndShowPaymentInput();
  } else {
    resetPaymentForm();
  }
});

watch(remainingAmount, (newVal) => {
  if (props.isOpen && !showAddPayment.value && !paymentRemovalAuthorized.value && newVal > 0.01) {
    checkAndShowPaymentInput();
  } else if (props.isOpen && showAddPayment.value && newVal > 0.01 && !amountConfirmed.value) {
    const remaining = parseFloat(newVal);
    amountFormatted.value = formatAmountInput(remaining);
    newPayment.value.amount = remaining;
    nextTick(() => {
      focusAmountInput();
    });
  }
});

function checkAndShowPaymentInput() {
  const remaining = parseFloat(remainingAmount.value);
  if (remaining > 0.01) {
    showAddPayment.value = true;
    amountInputVisible.value = true;
    amountFormatted.value = formatAmountInput(remaining);
    newPayment.value.amount = remaining;
    amountConfirmed.value = false;
    nextTick(() => {
      focusAmountInput();
    });
  } else {
    showAddPayment.value = false;
  }
}

function resetPaymentForm() {
  showAddPayment.value = false;
  methodSelected.value = false;
  cardTypeSelected.value = false;
  installmentsSelected.value = false;
  amountInputVisible.value = false;
  amountConfirmed.value = false;
  selectedMethodIndex.value = 0;
  selectedCardTypeIndex.value = 0;
  selectedInstallmentIndex.value = 0;
  selectedPaymentIndex.value = -1;
  paymentRemovalAuthorized.value = false;
  amountFormatted.value = '';
  isFinishing.value = false;
  newPayment.value = {
    method: 'cash',
    amount: 0,
    installments: 1,
    cardType: null,
  };
}

function onFinalizeClick(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }
  handleFinish();
}

async function handleFinish() {
  if (!canFinish.value || isFinishing.value) return;

  isFinishing.value = true;

  try {
    const result = await cartStore.finish();
    await new Promise((resolve) => setTimeout(resolve, 2000));
    emit('finish', result?.sale ?? null);
    emit('close');
  } catch (error) {
    console.error('Erro ao finalizar venda:', error);
    toast.error(error?.message ?? 'Erro ao finalizar venda.');
  } finally {
    isFinishing.value = false;
  }
}

function formatPaymentMethod(method) {
  const methods = {
    cash: 'Dinheiro',
    money: 'Dinheiro',
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
  let num;
  if (typeof value === 'string') {
    num = parseFloat(value.replace(/\./g, '').replace(',', '.'));
  } else {
    num = typeof value === 'number' ? value : parseFloat(value);
  }
  if (isNaN(num) || num === 0) return '';
  const fixed = num.toFixed(2);
  const parts = fixed.split('.');
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

function confirmAmountAndShowMethods() {
  const amount = parseAmount(amountFormatted.value);
  if (!amount || amount <= 0) {
    return;
  }
  
  newPayment.value.amount = amount;
  amountConfirmed.value = true;
  methodSelected.value = false;
  selectedMethodIndex.value = 0;
}

function selectMethod(method) {
  newPayment.value.method = method;
  methodSelected.value = true;
  
  if (method === 'cash' || method === 'pix') {
    cardTypeSelected.value = true;
    installmentsSelected.value = true;
  } else if (method === 'card') {
    cardTypeSelected.value = false;
    installmentsSelected.value = false;
    newPayment.value.cardType = null;
    newPayment.value.method = 'credit_card';
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
      const remaining = parseFloat(remainingAmount.value);
      amountFormatted.value = formatAmountInput(remaining);
      newPayment.value.amount = remaining;
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

function handleAmountConfirm() {
  const amount = parseAmount(amountFormatted.value);
  if (!amount || amount <= 0) {
    return;
  }
  
  if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit' && !amountConfirmed.value) {
    amountConfirmed.value = true;
    newPayment.value.amount = amount;
    return;
  }
  
  confirmAddPayment();
}

function selectInstallments(n) {
  newPayment.value.installments = n;
  installmentsSelected.value = true;
  selectedInstallmentIndex.value = n - 1;
  nextTick(() => {
    confirmAddPayment();
  });
}

function installmentPreview(n) {
  const amount = newPayment.value.amount || parseFloat(remainingAmount.value);
  const singleValue = amount / n;
  return Math.round((singleValue * 100)) / 100;
}

function scrollSelectedInstallmentIntoView() {
  const list = installmentsListRef.value;
  const idx = selectedInstallmentIndex.value;
  const child = list?.children?.[idx];
  if (child instanceof HTMLElement) {
    child.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
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
  let finalAmount = newPayment.value.amount;
  
  if (!finalAmount || finalAmount <= 0) {
    return;
  }

  if (finalAmount > remainingAmount.value + 0.01) {
    return;
  }

  try {
    let method;
    if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'debit') {
      method = 'debit_card';
    } else if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit') {
      method = 'credit_card';
    } else if (newPayment.value.method === 'pix') {
      method = 'pix';
    } else if (newPayment.value.method === 'cash') {
      method = 'money';
    } else {
      method = newPayment.value.method;
    }

    await cartStore.addPayment(
      method,
      finalAmount,
      newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit' ? newPayment.value.installments : 1
    );

    resetPaymentForm();
    const stillRemaining = parseFloat(remainingAmount.value);
    if (stillRemaining > 0.01) {
      nextTick(() => {
        checkAndShowPaymentInput();
      });
    }
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

async function authorizePaymentRemoval() {
  const result = await Swal.fire({
    title: 'Remover Pagamento',
    html: 'Insira a senha de gerente para autorizar a remoção de pagamentos:',
    icon: 'warning',
    input: 'text',
    inputPlaceholder: 'Senha de gerente',
    customClass: {
      input: 'swal-manager-auth-input',
    },
    inputAttributes: {
      autocomplete: 'off',
      autocapitalize: 'off',
      autocorrect: 'off',
      spellcheck: 'false',
      name: 'manager-auth-removal',
      'data-lpignore': 'true',
      'data-1p-ignore': 'true',
      'data-bwignore': 'true',
      'data-form-type': 'other',
    },
    showCancelButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#ea580c',
    focusConfirm: false,
    allowOutsideClick: false,
    inputValidator: (value) => (value ? null : 'Informe a senha.'),
  });
  if (!result.isConfirmed) return;
  const ok = result.value === 'admin123';
  if (!ok) {
    toast.error('Senha incorreta.');
    return;
  }
  paymentRemovalAuthorized.value = true;
  selectedPaymentIndex.value = 0;
}

async function removeSelectedPayment() {
  const idx = selectedPaymentIndex.value;
  if (idx < 0 || idx >= payments.value.length) return;
  const payment = payments.value[idx];
  const id = payment.id;
  if (!id) return;
  try {
    await cartStore.removePayment(id);
    toast.success('Pagamento removido.');
    if (payments.value.length === 0) {
      selectedPaymentIndex.value = -1;
      paymentRemovalAuthorized.value = false;
    } else {
      selectedPaymentIndex.value = 0;
    }
  } catch (err) {
    const msg = err?.message || err?.response?.data?.message || 'Erro ao remover pagamento.';
    toast.error(msg);
  }
}

function handleModalClose() {
  if (!showAddPayment.value) {
    emit('close');
  }
}

function handleKeydown(e) {
  if (!props.isOpen) return;

  if (e.key === 'Escape') {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if (showAddPayment.value) {
      cancelAddPayment(e);
      return;
    }
    if (selectedPaymentIndex.value >= 0 && payments.value.length > 0) {
      selectedPaymentIndex.value = -1;
      paymentRemovalAuthorized.value = false;
      return;
    }
    emit('close');
    return;
  }

  if (e.key === 'F9' && (e.metaKey || e.ctrlKey) && !showAddPayment.value && payments.value.length > 0) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    authorizePaymentRemoval();
    return;
  }

  if (e.key === 'F10' && canFinish.value && !isFinishing.value) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    handleFinish();
    return;
  }

  if (selectedPaymentIndex.value >= 0 && !showAddPayment.value && paymentRemovalAuthorized.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedPaymentIndex.value = Math.max(0, selectedPaymentIndex.value - 1);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedPaymentIndex.value = Math.min(payments.value.length - 1, selectedPaymentIndex.value + 1);
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      removeSelectedPayment();
      return;
    }
  }

  if (e.key === 'p' || e.key === 'P') {
    if (!showAddPayment.value && remainingAmount.value > 0.01) {
      e.preventDefault();
      checkAndShowPaymentInput();
    }
    return;
  }

  if (!showAddPayment.value) return;

  if (!amountConfirmed.value) {
    if (e.key === 'Enter') {
      e.preventDefault();
      confirmAmountAndShowMethods();
      return;
    }
    return;
  }

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
    const num = parseInt(e.key);
    if (num >= 1 && num <= paymentMethods.length) {
      e.preventDefault();
      selectMethod(paymentMethods[num - 1].value);
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

  if (newPayment.value.method === 'credit_card' && newPayment.value.cardType === 'credit' && cardTypeSelected.value && !installmentsSelected.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.max(0, selectedInstallmentIndex.value - 1);
      newPayment.value.installments = selectedInstallmentIndex.value + 1;
      nextTick(() => scrollSelectedInstallmentIntoView());
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.min(11, selectedInstallmentIndex.value + 1);
      newPayment.value.installments = selectedInstallmentIndex.value + 1;
      nextTick(() => scrollSelectedInstallmentIntoView());
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      selectInstallments(selectedInstallmentIndex.value + 1);
      return;
    }
    const num = parseInt(e.key);
    if (num >= 1 && num <= 12) {
      e.preventDefault();
      selectInstallments(num);
      return;
    }
    return;
  }

  if (installmentsSelected.value || (newPayment.value.method === 'cash' || newPayment.value.method === 'pix') || (newPayment.value.method === 'debit_card')) {
    if (e.key === 'Enter') {
      e.preventDefault();
      confirmAddPayment();
      return;
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown, true);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown, true);
});
</script>
