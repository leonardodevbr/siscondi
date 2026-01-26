<template>
  <Modal :is-open="isOpen" :title="pixStep === 'qr' ? 'Pagamento PIX' : 'Finalizar Venda'" @close="pixStep === 'qr' ? cancelPixFlow() : handleModalClose()">
    <div v-if="pixStep === 'qr'" class="space-y-4">
      <p class="text-sm text-slate-600">Escaneie o QR Code ou copie o código Pix Copia e Cola para pagar.</p>
      <div class="flex flex-col items-center gap-4">
        <img
          v-if="pixQrCodeBase64"
          :src="`data:image/png;base64,${pixQrCodeBase64}`"
          alt="QR Code PIX"
          class="h-48 w-48 rounded-lg border border-slate-200 object-contain bg-white"
        >
        <div class="w-full space-y-2">
          <label class="block text-xs font-medium text-slate-700">Pix Copia e Cola</label>
          <div class="flex gap-2">
            <input
              :value="pixQrCode"
              type="text"
              readonly
              class="flex-1 rounded border border-slate-300 bg-slate-50 px-3 py-2 text-xs"
            >
            <Button variant="outline" size="sm" @click="copyPixCode">Copiar</Button>
          </div>
        </div>
        <p class="text-xs text-slate-500">Aguardando confirmação do pagamento...</p>
        <Button variant="outline" @click="cancelPixFlow">Fechar</Button>
      </div>
    </div>
    <div v-else class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-700">Total Venda:</span>
          <span class="text-xl font-bold text-slate-900">{{ formatCurrency(saleSubtotal) }}</span>
        </div>
        <div v-if="cartStore.discountAmount > 0" class="mt-2 flex items-center justify-between" :class="cartStore.coupon ? 'text-green-700' : 'text-blue-700'">
          <span class="text-sm font-medium">{{ cartStore.coupon ? `Cupom ${cartStore.coupon.code}` : 'Desconto' }}:</span>
          <span class="text-sm font-semibold">- {{ formatCurrency(cartStore.discountAmount) }}</span>
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

      <div
        v-if="cartStore.coupon && !isFinishing"
        class="rounded-lg border border-green-200 bg-green-50 p-3"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-green-800">Cupom {{ cartStore.coupon.code }} aplicado</p>
            <ul v-if="cartStore.coupon.rules_summary && cartStore.coupon.rules_summary.length" class="mt-1 space-y-0.5 text-xs text-green-700">
              <li v-for="(rule, i) in cartStore.coupon.rules_summary" :key="i">{{ rule }}</li>
            </ul>
          </div>
          <button
            type="button"
            class="shrink-0 text-xs font-medium text-red-600 hover:text-red-800 underline"
            @click="handleRemoveCouponInCheckout"
          >
            Remover cupom
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="font-medium text-slate-700">Pagamentos:</span>
          <span v-if="!isFinishing && !showAddPayment && remainingAmount > 0" class="text-xs text-slate-500">Pressione P para adicionar pagamento</span>
        </div>

        <div v-if="!isFinishing && payments.length === 0" class="rounded border border-slate-200 bg-slate-50 p-3 text-center text-sm text-slate-500">
          Nenhum pagamento adicionado
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(payment, index) in (isFinishing ? paymentsSnapshotForFinishing : payments)"
            :key="payment.id || index"
            :class="[
              'flex items-center justify-between rounded border p-3 transition',
              !isFinishing && selectedPaymentIndex === index
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
              <span v-if="!isFinishing && selectedPaymentIndex === index && paymentRemovalAuthorized" class="text-xs font-medium text-orange-600">ENTER para remover • ESC para desativar</span>
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
          <p v-if="couponPaymentRestriction" class="mb-2 text-xs text-amber-700">
            Este cupom aceita apenas: {{ couponPaymentRestriction }}
          </p>
          <div v-if="effectivePaymentMethods.length === 0" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Nenhum método de pagamento compatível com o cupom. Remova o cupom acima para continuar.
          </div>
          <div v-else class="space-y-1">
            <button
              v-for="(method, index) in effectivePaymentMethods"
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
          <div v-if="newPayment.method === 'credit_card' && !cardTypeSelected" @keydown.esc.stop="goBackToMethodList">
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

          <div v-if="newPayment.method === 'credit_card' && newPayment.cardType === 'credit' && cardTypeSelected && !installmentsSelected" @keydown.esc.stop="goBackToMethodList">
            <label class="mb-1 block text-xs font-medium text-slate-700">Parcelas</label>
            <div v-if="loadingInstallments" class="flex items-center justify-center py-8">
              <div class="h-10 w-10 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
            </div>
            <div v-else ref="installmentsListRef" class="space-y-1 max-h-60 overflow-y-auto">
              <button
                v-for="(opt, index) in installmentsFromApi"
                :key="opt.installment"
                type="button"
                :class="[
                  'w-full rounded border px-3 py-2 text-left text-sm transition',
                  selectedInstallmentIndex === index
                    ? 'border-blue-500 bg-blue-100 font-medium'
                    : 'border-slate-300 bg-white hover:border-blue-300'
                ]"
                @click="selectInstallmentFromApi(opt)"
              >
                {{ opt.installment }}x de {{ formatCurrency(opt.amount) }} {{ opt.interest_free ? '(sem juros)' : '' }}
              </button>
            </div>
          </div>

          <div v-if="installmentsSelected || (newPayment.method === 'cash' || newPayment.method === 'pix' || newPayment.method === 'point') || (newPayment.method === 'debit_card')" class="space-y-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
              <p class="text-sm font-medium text-slate-700">Resumo:</p>
              <p class="mt-1 text-base font-semibold text-slate-900">{{ paymentSummary }}</p>
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" @click="goBackToMethodList">Voltar (ESC)</Button>
              <Button variant="primary" size="sm" @click="confirmAddPayment">Adicionar (ENTER)</Button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="pointStep === 'select_device'" class="border-t border-slate-200 pt-4 space-y-4">
        <p class="text-sm font-medium text-slate-700">Selecione a maquininha para enviar o pagamento:</p>
        <div v-if="pointLoadingDevices" class="flex items-center justify-center py-4">
          <div class="h-8 w-8 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
        </div>
        <template v-else>
          <select
            v-model="pointSelectedDeviceId"
            class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">-- Selecione --</option>
            <option v-for="d in pointDevices" :key="d.id" :value="d.id">
              {{ d.external_pos_id || d.id }}
            </option>
          </select>
          <div class="flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="cancelPointFlow">Cancelar</Button>
            <Button variant="primary" size="sm" :disabled="!pointSelectedDeviceId || pointSendingToDevice" @click="sendToPointDevice">
              {{ pointSendingToDevice ? 'Enviando...' : 'Enviar para maquininha' }}
            </Button>
          </div>
        </template>
      </div>
      <div v-else-if="pointStep === 'waiting'" class="border-t border-slate-200 pt-4">
        <div class="flex flex-col items-center justify-center space-y-4 py-8">
          <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
          <p class="text-sm font-medium text-slate-700">Aguardando pagamento na maquininha... A venda só será finalizada após a confirmação.</p>
        </div>
      </div>
      <div v-else-if="pointStep === 'partial_done'" class="border-t border-slate-200 pt-4 space-y-4">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
          <p class="text-sm font-medium text-emerald-800">Pagamento aprovado na maquininha.</p>
          <p class="mt-1 text-sm text-emerald-700">Falta pagar {{ formatCurrency(remainingAmount) }}.</p>
        </div>
        <div class="flex justify-end">
          <Button variant="primary" size="sm" @click="continueAfterPartialPoint">Adicionar pagamento</Button>
        </div>
      </div>
      <div v-else-if="isFinishing" class="border-t border-slate-200 pt-4">
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
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useSettingsStore } from '@/stores/settings';
import { formatCurrency } from '@/utils/format';
import api from '@/services/api';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';

const toast = useToast();
const authStore = useAuthStore();

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(['close', 'finish']);

const cartStore = useCartStore();
const settingsStore = useSettingsStore();

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
const authorizedByUserIdForRemoval = ref(null);
const paymentsSnapshotForFinishing = ref([]);
const pointStep = ref(null);
const pointDevices = ref([]);
const pointSelectedDeviceId = ref('');
const pointPendingSale = ref(null);
const pointIntentId = ref('');
const pointPollingInterval = ref(null);
const pointLoadingDevices = ref(false);
const pointSendingToDevice = ref(false);
const installmentsFromApi = ref([]);
const loadingInstallments = ref(false);
const pixStep = ref(null);
const pixPendingSaleId = ref(null);
const pixQrCode = ref('');
const pixQrCodeBase64 = ref('');
const pixPollingInterval = ref(null);
const pixGenerating = ref(false);

const amountInputRef = ref(null);
const installmentsListRef = ref(null);
const amountFormatted = ref('');

const paymentMethods = [
  { value: 'cash', label: 'Dinheiro', apiValues: ['money'] },
  { value: 'pix', label: 'PIX', apiValues: ['pix'] },
  { value: 'credit_card', label: 'Cartão de Crédito', apiValues: ['credit_card', 'point'] },
  { value: 'debit_card', label: 'Cartão de Débito', apiValues: ['debit_card', 'point'] },
];

const effectivePaymentMethods = computed(() => {
  const allowed = cartStore.coupon?.allowed_payment_methods;
  if (!Array.isArray(allowed) || allowed.length === 0) {
    return paymentMethods;
  }
  return paymentMethods.filter((m) =>
    m.apiValues.some((av) => allowed.includes(av))
  );
});

const couponPaymentRestriction = computed(() => {
  const allowed = cartStore.coupon?.allowed_payment_methods;
  if (!Array.isArray(allowed) || allowed.length === 0) return '';
  const labels = {
    money: 'Dinheiro',
    pix: 'PIX',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    store_credit: 'Crédito Loja',
    point: 'Cartão (Maquininha)',
  };
  return allowed.map((a) => labels[a] ?? a).join(', ');
});

const newPayment = ref({
  method: 'cash',
  amount: 0,
  installments: 1,
  cardType: null,
});

const saleSubtotal = computed(() => {
  const val = cartStore.totalAmount;
  return typeof val === 'string' ? parseFloat(val) : (val ?? 0);
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
  if (newPayment.value.method === 'point') {
    return `R$ ${formatCurrency(amount)} na Maquininha`;
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
    nextTick(() => {
      nextTick(() => {
        checkAndShowPaymentInput();
      });
    });
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

watch(effectivePaymentMethods, (methods) => {
  const n = methods.length;
  if (n > 0 && selectedMethodIndex.value >= n) {
    selectedMethodIndex.value = n - 1;
  }
}, { deep: true });

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
  installmentsFromApi.value = [];
  loadingInstallments.value = false;
  amountFormatted.value = '';
  isFinishing.value = false;
  paymentsSnapshotForFinishing.value = [];
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
  paymentsSnapshotForFinishing.value = (cartStore.payments || []).map((p) => ({ ...p }));

  try {
    const result = await cartStore.finish();
    if (result?.pending_point && result?.sale?.id) {
      pointPendingSale.value = result.sale;
      pointStep.value = 'select_device';
      pointDevices.value = [];
      pointSelectedDeviceId.value = '';
      pointLoadingDevices.value = true;
      try {
        const { data } = await api.get('mp-point/devices');
        pointDevices.value = data?.devices ?? [];
        if (pointDevices.value.length === 0) {
          toast.warning('Nenhuma maquininha encontrada. Verifique o token em Configurações > Integrações.');
        } else if (pointDevices.value.length === 1) {
          pointSelectedDeviceId.value = pointDevices.value[0].id ?? '';
        }
      } catch (err) {
        toast.error(err?.response?.data?.message || err?.message || 'Erro ao listar maquininhas.');
        pointStep.value = null;
        pointPendingSale.value = null;
      } finally {
        pointLoadingDevices.value = false;
        isFinishing.value = false;
      }
      return;
    }
    if (result?.sale?.id && (result.sale.status === 'pending_payment' || result.sale.status === 'PENDING_PAYMENT')) {
      const hadPix = (result.sale.payments || result.sale.sale_payments || []).some((p) => (p.method || '').toLowerCase() === 'pix');
      if (hadPix) {
        pixGenerating.value = true;
        try {
          const { data } = await api.post('pos/pix/generate', { sale_id: result.sale.id });
          pixPendingSaleId.value = result.sale.id;
          pixQrCode.value = data.qr_code || '';
          pixQrCodeBase64.value = data.qr_code_base64 || '';
          pixStep.value = 'qr';
          startPixPolling();
        } catch (e) {
          toast.warning(e?.response?.data?.message || 'PIX não disponível. Venda registrada.');
          cartStore.resetState();
          emit('finish', result?.sale ?? null);
          emit('close');
        } finally {
          pixGenerating.value = false;
          isFinishing.value = false;
        }
        return;
      }
    }
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

function startPixPolling() {
  stopPixPolling();
  pixPollingInterval.value = setInterval(async () => {
    if (!pixPendingSaleId.value) return;
    try {
      const { data } = await api.get('pos/pix/status', { params: { sale_id: pixPendingSaleId.value } });
      if (data.paid) {
        const sid = pixPendingSaleId.value;
        stopPixPolling();
        pixStep.value = null;
        pixPendingSaleId.value = null;
        pixQrCode.value = '';
        pixQrCodeBase64.value = '';
        cartStore.resetState();
        emit('finish', { id: sid, status: 'completed' });
        emit('close');
        toast.success('Pagamento PIX confirmado!');
      }
    } catch (_) {}
  }, 3000);
}

function stopPixPolling() {
  if (pixPollingInterval.value) {
    clearInterval(pixPollingInterval.value);
    pixPollingInterval.value = null;
  }
}

function cancelPixFlow() {
  stopPixPolling();
  pixStep.value = null;
  pixPendingSaleId.value = null;
  pixQrCode.value = '';
  pixQrCodeBase64.value = '';
  cartStore.resetState();
  emit('close');
}

function copyPixCode() {
  if (!pixQrCode.value) return;
  navigator.clipboard.writeText(pixQrCode.value).then(() => {
    toast.success('Código PIX copiado!');
  }).catch(() => {
    toast.error('Não foi possível copiar.');
  });
}

function stopPointPolling() {
  if (pointPollingInterval.value) {
    clearInterval(pointPollingInterval.value);
    pointPollingInterval.value = null;
  }
}

async function sendToPointDevice() {
  if (!pointPendingSale.value?.id || !pointSelectedDeviceId.value) {
    toast.warning('Selecione uma maquininha.');
    return;
  }
  pointSendingToDevice.value = true;
  pointIntentId.value = '';
  try {
    const { data } = await api.post('mp-point/process-payment', {
      sale_id: pointPendingSale.value.id,
      device_id: pointSelectedDeviceId.value,
    });
    pointIntentId.value = data?.intent_id ?? '';
    pointStep.value = 'waiting';
    pointSendingToDevice.value = false;
    pointPollingInterval.value = setInterval(async () => {
      try {
        const { data: saleResp } = await api.get(`sales/${pointPendingSale.value.id}`);
        const sale = saleResp?.data ?? saleResp;
        const status = sale?.status ?? sale?.attributes?.status;
        const totalPayments = sale?.total_payments ?? (sale?.payments ?? []).reduce((s, p) => s + (p?.amount ?? 0), 0);
        const finalAmount = sale?.final_amount ?? pointPendingSale.value?.final_amount ?? 0;

        if (status === 'completed') {
          stopPointPolling();
          pointStep.value = null;
          pointPendingSale.value = null;
          pointIntentId.value = '';
          cartStore.resetState();
          emit('finish', sale);
          emit('close');
          toast.success('Pagamento aprovado na maquininha.');
          return;
        }

        if (status === 'pending_payment' && pointIntentId.value) {
          try {
            const { data: intentResp } = await api.get(`mp-point/check-status/${pointIntentId.value}`);
            const state = intentResp?.state ?? null;
            if (state === 'FINISHED' || state === 'CONFIRMED') {
              if (totalPayments < finalAmount - 0.005) {
                stopPointPolling();
                await cartStore.init();
                pointStep.value = 'partial_done';
                pointPendingSale.value = null;
                pointIntentId.value = '';
                toast.success('Pagamento aprovado na maquininha. Adicione o restante.');
              }
            }
          } catch (_) {
            // check-status pode falhar; seguir no próximo poll
          }
        }
      } catch (_) {
        // ignore poll errors
      }
    }, 3000);
  } catch (err) {
    pointSendingToDevice.value = false;
    toast.error(err?.response?.data?.message || err?.message || 'Erro ao enviar para a maquininha.');
  }
}

function cancelPointFlow() {
  stopPointPolling();
  pointStep.value = null;
  pointPendingSale.value = null;
  pointIntentId.value = '';
  pointSelectedDeviceId.value = '';
  pointDevices.value = [];
  isFinishing.value = false;
}

function continueAfterPartialPoint() {
  pointStep.value = null;
  pointPendingSale.value = null;
  pointIntentId.value = '';
  nextTick(() => {
    checkAndShowPaymentInput();
  });
}

function formatPaymentMethod(method) {
  const methods = {
    cash: 'Dinheiro',
    money: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    pix: 'PIX',
    point: 'Cartão (Maquininha)',
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
  methodSelected.value = true;
  const gateway = settingsStore.activePaymentGateway;

  if (method === 'cash' || method === 'pix') {
    newPayment.value.method = method;
    cardTypeSelected.value = true;
    installmentsSelected.value = true;
    return;
  }

  if (method === 'credit_card') {
    if (gateway === 'mercadopago_point') {
      newPayment.value.method = 'point';
      newPayment.value.cardType = null;
      cardTypeSelected.value = true;
      installmentsSelected.value = true;
    } else {
      newPayment.value.method = 'credit_card';
      newPayment.value.cardType = 'credit';
      cardTypeSelected.value = true;
      installmentsSelected.value = false;
      nextTick(() => fetchInstallmentOptions());
    }
    return;
  }

  if (method === 'debit_card') {
    if (gateway === 'mercadopago_point') {
      newPayment.value.method = 'point';
      newPayment.value.cardType = null;
    } else {
      newPayment.value.method = 'debit_card';
    }
    cardTypeSelected.value = true;
    installmentsSelected.value = true;
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

async function fetchInstallmentOptions() {
  const amount = newPayment.value.amount || parseFloat(remainingAmount.value);
  if (!amount || amount <= 0) {
    installmentsFromApi.value = [];
    return;
  }
  loadingInstallments.value = true;
  installmentsFromApi.value = [];
  try {
    const { data } = await api.get('sales/simulate-installments', { params: { amount } });
    installmentsFromApi.value = data.installments || [];
  } catch (err) {
    toast.error(err?.response?.data?.message || err?.message || 'Erro ao buscar parcelas.');
    installmentsFromApi.value = [];
  } finally {
    loadingInstallments.value = false;
  }
}

function selectInstallmentFromApi(option) {
  newPayment.value.installments = option.installment;
  installmentsSelected.value = true;
  selectedInstallmentIndex.value = option.installment - 1;
  nextTick(() => {
    confirmAddPayment();
  });
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
    nextTick(() => {
      const el = amountInputRef.value;
      if (el) {
        el.focus();
        el.select();
      }
    });
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
    } else if (newPayment.value.method === 'point') {
      method = 'point';
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

function goBackToMethodList() {
  methodSelected.value = false;
  cardTypeSelected.value = false;
  installmentsSelected.value = false;
  selectedMethodIndex.value = 0;
  selectedCardTypeIndex.value = 0;
  selectedInstallmentIndex.value = 0;
  installmentsFromApi.value = [];
  loadingInstallments.value = false;
  const amount = newPayment.value.amount || parseFloat(remainingAmount.value);
  newPayment.value = {
    method: 'cash',
    amount: Number(amount) || 0,
    installments: 1,
    cardType: null,
  };
}

function cancelAddPayment(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }
  
  // Se ainda há valor pendente, apenas limpa o formulário mas mantém o input visível
  if (remainingAmount.value > 0.01) {
    // Reseta apenas os campos do formulário, mas mantém showAddPayment aberto
    methodSelected.value = false;
    cardTypeSelected.value = false;
    installmentsSelected.value = false;
    amountConfirmed.value = false;
    selectedMethodIndex.value = 0;
    selectedCardTypeIndex.value = 0;
    selectedInstallmentIndex.value = 0;
    selectedPaymentIndex.value = -1;
    paymentRemovalAuthorized.value = false;
    amountFormatted.value = formatAmountInput(parseFloat(remainingAmount.value));
    newPayment.value = {
      method: 'cash',
      amount: parseFloat(remainingAmount.value),
      installments: 1,
      cardType: null,
    };
    
    // Refoca no input de valor
    nextTick(() => {
      focusAmountInput();
    });
  } else {
    // Se não há valor pendente, pode fechar normalmente
    resetPaymentForm();
  }
}

async function handleRemoveCouponInCheckout() {
  try {
    await cartStore.removeCoupon();
    toast.success('Cupom removido.');
    selectedMethodIndex.value = 0;
  } catch (err) {
    toast.error(err?.message ?? 'Erro ao remover cupom.');
  }
}

async function authorizePaymentRemoval() {
  const result = await Swal.fire({
    title: 'Remover Pagamento',
    html: `<div style="margin-bottom: 1rem; text-align: center;">
        <span style="font-size: 1rem; color: #374151;">Esta ação requer autorização de gerente.</span>
      </div>
      <div style="text-align: left;">
        <div style="margin-bottom: 1rem;">
          <label for="swal-op-pin" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem;">PIN do gerente</label>
          <input
            type="text"
            id="swal-op-pin"
            name="manager_pin_input"
            placeholder="Ex: 1234"
            inputmode="numeric"
            maxlength="10"
            autocomplete="off"
            spellcheck="false"
            style="width: 100%; padding: 0.625rem 0.75rem; font-size: 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);"
            onfocus="this.style.borderColor='#3b82f6'; this.style.outline='none'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)';"
            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 1px 2px 0 rgba(0, 0, 0, 0.05)';"
          />
        </div>
        <div style="margin-bottom: 0;">
          <label for="swal-op-password" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem;">Senha de operação</label>
          <input
            type="password"
            id="swal-op-password"
            name="manager_auth_password"
            placeholder="Senha"
            autocomplete="new-password"
            spellcheck="false"
            style="width: 100%; padding: 0.625rem 0.75rem; font-size: 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);"
            onfocus="this.style.borderColor='#3b82f6'; this.style.outline='none'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)';"
            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 1px 2px 0 rgba(0, 0, 0, 0.05)';"
          />
        </div>
      </div>`,
    icon: 'warning',
    width: '400px',
    showCancelButton: true,
    confirmButtonText: 'Confirmar (ENTER)',
    cancelButtonText: 'Cancelar (ESC)',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    focusConfirm: false,
    allowOutsideClick: false,
    customClass: {
      popup: 'swal-compact',
      title: 'swal-compact-title',
      htmlContainer: 'swal-compact-content',
      actions: 'swal-compact-actions'
    },
    preConfirm: async () => {
      const pin = (document.getElementById('swal-op-pin')?.value ?? '').trim();
      const password = document.getElementById('swal-op-password')?.value ?? '';
      if (!pin || !password) {
        Swal.showValidationMessage('Informe PIN e senha de operação.');
        return false;
      }
      const res = await authStore.validateOperationPassword({ pin, password });
      if (!res.valid) {
        Swal.showValidationMessage('PIN ou senha incorretos.');
        return false;
      }
      return res;
    },
  });
  if (!result.isConfirmed) return;
  authorizedByUserIdForRemoval.value = result.value?.authorized_by_user_id;
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
    await cartStore.removePayment(id, authorizedByUserIdForRemoval.value);
    toast.success('Pagamento removido.');
    if (payments.value.length === 0) {
      selectedPaymentIndex.value = -1;
      paymentRemovalAuthorized.value = false;
      authorizedByUserIdForRemoval.value = null;
    } else {
      selectedPaymentIndex.value = 0;
    }
  } catch (err) {
    const msg = err?.message || err?.response?.data?.message || 'Erro ao remover pagamento.';
    toast.error(msg);
  }
}

function handleModalClose() {
  stopPixPolling();
  pixStep.value = null;
  pixPendingSaleId.value = null;
  pixQrCode.value = '';
  pixQrCodeBase64.value = '';
  if (pointStep.value) {
    cancelPointFlow();
  }
  emit('close');
}

async function handleKeydown(e) {
  if (!props.isOpen) return;

  if (e.key === 'Escape') {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if (showAddPayment.value) {
      if (methodSelected.value) {
        goBackToMethodList();
        return;
      }
      if (payments.value.length > 0) {
        cancelAddPayment(e);
        return;
      }
      if (pointStep.value) {
        cancelPointFlow();
      }
      emit('close');
      return;
    }
    if (selectedPaymentIndex.value >= 0 && payments.value.length > 0) {
      selectedPaymentIndex.value = -1;
      paymentRemovalAuthorized.value = false;
      authorizedByUserIdForRemoval.value = null;
      return;
    }
    if (pointStep.value) {
      cancelPointFlow();
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
      selectedMethodIndex.value = Math.min(effectivePaymentMethods.value.length - 1, selectedMethodIndex.value + 1);
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      selectMethod(effectivePaymentMethods.value[selectedMethodIndex.value].value);
      return;
    }
    const num = parseInt(e.key);
    if (num >= 1 && num <= effectivePaymentMethods.value.length) {
      e.preventDefault();
      selectMethod(effectivePaymentMethods.value[num - 1].value);
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

<style scoped>
/* Estilos globais para modais SweetAlert2 compactos */
:deep(.swal-compact) {
  padding: 1.25rem !important;
}

:deep(.swal-compact-title) {
  font-size: 1.125rem !important;
  font-weight: 600 !important;
  margin-bottom: 0.75rem !important;
  padding: 0 !important;
}

:deep(.swal-compact-content) {
  margin: 0 !important;
  padding: 0 !important;
}

:deep(.swal-compact-actions) {
  margin-top: 1rem !important;
  gap: 0.5rem !important;
}

:deep(.swal2-input) {
  height: 2.5rem !important;
  border-radius: 0.375rem !important;
  border: 1px solid #cbd5e1 !important;
}

:deep(.swal2-input:focus) {
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
}

:deep(.swal2-validation-message) {
  font-size: 0.813rem !important;
  padding: 0.5rem !important;
  margin: 0.5rem 0 0 0 !important;
}
</style>
