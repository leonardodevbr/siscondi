<template>
  <Modal :is-open="isOpen" :title="'Finalizar Venda'" :closable="false" @close="handleModalClose">
    <div class="space-y-4">
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

      <!-- Novo Fluxo: Método PRIMEIRO -->
      <div v-if="showAddPayment" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
        <h4 class="mb-3 text-sm font-semibold text-slate-800">Adicionar Pagamento</h4>
        
        <!-- PASSO 1: Selecionar Método -->
        <div v-if="!methodSelected" class="space-y-3">
          <div class="rounded-lg border border-slate-200 bg-white p-3 mb-3">
            <p class="text-sm font-medium text-slate-700">Falta pagar:</p>
            <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(remainingAmount) }}</p>
          </div>
          
          <p class="text-xs text-slate-600 mb-2">Selecione a forma de pagamento:</p>
          <p v-if="couponPaymentRestriction" class="mb-2 text-xs text-amber-700">
            Este cupom aceita apenas: {{ couponPaymentRestriction }}
          </p>
          
          <div v-if="effectivePaymentMethods.length === 0" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Nenhum método de pagamento compatível com o cupom. Remova o cupom acima para continuar.
          </div>
          
          <div v-else class="grid grid-cols-2 gap-2">
            <button
              v-for="(method, index) in effectivePaymentMethods"
              :key="method.value"
              type="button"
              :class="[
                'rounded border px-4 py-3 text-sm font-medium transition relative',
                selectedMethodIndex === index
                  ? 'border-blue-500 bg-blue-100 text-blue-700 ring-2 ring-blue-300'
                  : 'border-slate-300 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50'
              ]"
              @click="selectMethod(method.value)"
            >
              <span class="absolute top-1 left-2 text-xs font-bold opacity-50">{{ index + 1 }}</span>
              <span>{{ method.label }}</span>
            </button>
          </div>
        </div>

        <!-- PASSO 2: Confirmar Valor (editável para pagamento parcial) -->
        <div v-else-if="methodSelected && !isProcessingPayment && !showingPixQrCode" class="space-y-3">
          <div class="flex items-center justify-between mb-3">
            <h5 class="text-sm font-semibold text-slate-700">{{ formatPaymentMethod(newPayment.method) }}</h5>
            <button
              type="button"
              @click="goBackToMethodList"
              class="text-xs text-slate-500 hover:text-slate-700 underline"
            >
              Mudar método (ESC)
            </button>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Valor a pagar</label>
            <input
              ref="amountInputRef"
              v-model="amountFormatted"
              type="text"
              inputmode="decimal"
              class="h-12 w-full rounded border border-slate-300 px-3 text-lg font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="R$ 0,00"
              @input="handleAmountInput"
              @keydown.enter="confirmAmountAndProcess"
              @keydown.esc.stop.prevent="goBackToMethodList"
            >
            <p class="mt-1 text-xs text-slate-500">
              Pressione ENTER para confirmar ou edite para pagamento parcial
            </p>
          </div>

          <div class="flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="goBackToMethodList">Voltar (ESC)</Button>
            <Button variant="primary" size="sm" @click="confirmAmountAndProcess">Confirmar (ENTER)</Button>
          </div>
        </div>

        <!-- PASSO 3: Parcelamento (apenas CRÉDITO) -->
        <div v-else-if="methodSelected && newPayment.method === 'credit_card' && !installmentsSelected" class="space-y-3">
          <div class="flex items-center justify-between mb-3">
            <h5 class="text-sm font-semibold text-slate-700">Cartão de Crédito - {{ formatCurrency(newPayment.amount) }}</h5>
            <button
              type="button"
              @click="goBackToMethodList"
              class="text-xs text-slate-500 hover:text-slate-700 underline"
            >
              Voltar (ESC)
            </button>
          </div>

          <label class="mb-1 block text-xs font-medium text-slate-700">Selecione as parcelas:</label>
          
          <div v-if="loadingInstallments" class="flex items-center justify-center py-8">
            <div class="h-10 w-10 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
          </div>
          
          <div v-else ref="installmentsListRef" class="space-y-2 max-h-60 overflow-y-auto">
            <button
              v-for="(opt, index) in installmentsFromApi"
              :key="opt.installment"
              type="button"
              :class="[
                'w-full rounded border px-3 py-2 text-left text-sm transition relative',
                selectedInstallmentIndex === index
                  ? 'border-blue-500 bg-blue-100 font-medium text-blue-700 ring-2 ring-blue-300'
                  : 'border-slate-300 bg-white hover:border-blue-300'
              ]"
              @click="selectInstallmentFromApi(opt)"
            >
              <span v-if="index < 9" class="absolute top-1 left-2 text-xs font-bold opacity-50">{{ index + 1 }}</span>
              <span class="font-semibold ml-6">{{ opt.installment }}x de {{ formatCurrency(opt.amount) }}</span>
              <span v-if="opt.interest_free" class="ml-2 text-xs text-green-600">(sem juros)</span>
              <span v-else class="ml-2 text-xs text-slate-500">(total: {{ formatCurrency(opt.total) }})</span>
            </button>
          </div>
        </div>

        <!-- Estado de Processamento -->
        <div v-else-if="isProcessingPayment && !showingPixQrCode" class="flex flex-col items-center justify-center py-8">
          <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent mb-3"></div>
          <p class="text-sm font-medium text-slate-700">Processando pagamento...</p>
        </div>

        <!-- QR Code PIX (substitui apenas o bloco azul) -->
        <div v-else-if="showingPixQrCode" class="space-y-4">
          <div class="flex items-center justify-between mb-3">
            <h5 class="text-sm font-semibold text-slate-700">PIX - {{ formatCurrency(newPayment.amount) }}</h5>
            <button
              type="button"
              @click="cancelPixInFlow"
              class="text-xs text-slate-500 hover:text-slate-700 underline"
            >
              Cancelar (ESC)
            </button>
          </div>

          <div v-if="pixGenerating" class="flex flex-col items-center justify-center py-8">
            <div class="h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent mb-3"></div>
            <p class="text-sm font-medium text-slate-700">Gerando QR Code...</p>
          </div>

          <div v-else-if="pixStep === 'error'" class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800 mb-2">Não foi possível gerar o QR Code PIX.</p>
            <div class="flex justify-end gap-2 mt-3">
              <Button variant="outline" size="sm" @click="cancelPixInFlow">Voltar</Button>
              <Button variant="primary" size="sm" @click="retryPixGeneration" :disabled="pixGenerating">
                {{ pixGenerating ? 'Gerando...' : 'Tentar Novamente' }}
              </Button>
            </div>
          </div>

          <div v-else-if="pixQrCodeBase64" class="space-y-4">
            <div class="flex flex-col items-center gap-3">
              <img
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
            </div>

            <div class="flex items-center justify-center gap-2 text-sm" :class="pixLastStatus === 'paid' ? 'text-emerald-600 font-medium' : 'text-slate-600'">
              <div class="h-2 w-2 rounded-full shrink-0" :class="pixLastStatus === 'paid' ? 'bg-emerald-500' : 'bg-blue-500 animate-pulse'"></div>
              <span>{{ pixLastStatus === 'paid' ? 'Pagamento confirmado!' : 'Aguardando confirmação do pagamento...' }}</span>
              <span v-if="pixTimer > 0 && pixLastStatus !== 'paid'" class="font-mono text-xs text-slate-500">({{ formatTimer(pixTimer) }})</span>
            </div>

            <div class="flex justify-end">
              <Button variant="outline" size="sm" @click="cancelPixInFlow">Cancelar Pagamento (ESC)</Button>
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
          {{ isPixOnlyAndFullyPaid ? 'Gerar QR Code PIX (F10)' : 'Finalizar Venda (F10)' }}
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
const isProcessingPayment = ref(false);
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
const showingPixQrCode = ref(false);
const pixChargeId = ref(null);
const pixLastStatus = ref(null); // 'pending' | 'paid' — atualizado pelo polling para refletir na UI
const pixTimer = ref(0);
const pixTimerInterval = ref(null);

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

const isPixOnlyAndFullyPaid = computed(() => {
  if (!canFinish.value || !payments.value?.length) return false;
  const onlyPix = payments.value.every((p) => (String(p.method || '')).toLowerCase() === 'pix');
  return onlyPix && remainingAmount.value <= 0.01;
});

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
  isProcessingPayment.value = false;
  showingPixQrCode.value = false;
  pixChargeId.value = null;
  pixLastStatus.value = null;
  pixTimer.value = 0;
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
    // Legado: F10 com venda já contendo PIX (ex.: outro cliente/add-payment antigo) → gera QR e poll por status da venda
    if (result?.sale?.id && (result.sale.status === 'pending_payment' || result.sale.status === 'PENDING_PAYMENT')) {
      const hadPix = (result.sale.payments || result.sale.sale_payments || []).some((p) => (String(p.method || '')).toLowerCase() === 'pix');
      if (hadPix) {
        pixGenerating.value = true;
        showingPixQrCode.value = true;
        try {
          const { data } = await api.post('pos/pix/generate', { sale_id: result.sale.id });
          pixPendingSaleId.value = result.sale.id;
          pixQrCode.value = data.qr_code || '';
          pixQrCodeBase64.value = data.qr_code_base64 || '';
          pixStep.value = 'qr';
          startPixSaleStatusPolling();
        } catch (e) {
          toast.error(e?.response?.data?.message || 'Erro ao gerar PIX.');
          pixStep.value = 'error';
          pixPendingSaleId.value = result?.sale?.id ?? null;
        } finally {
          pixGenerating.value = false;
          isFinishing.value = false;
        }
        return;
      }
    }
    // Exibe o cupom em uma nova janela (simulação de impressora térmica)
    if (result?.sale) {
      openReceiptWindow(result.sale);
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

function startPixChargePolling() {
  stopPixPolling();
  const cid = pixChargeId.value;
  if (!cid) return;

  pixLastStatus.value = 'pending';
  pixTimer.value = 30 * 60;
  pixTimerInterval.value = setInterval(() => {
    if (pixTimer.value > 0) pixTimer.value--;
    else stopPixPolling();
  }, 1000);

  pixPollingInterval.value = setInterval(async () => {
    if (!pixChargeId.value) return;
    try {
      const { data } = await api.get('pos/pix/charge-status', {
        params: { charge_id: pixChargeId.value, _t: Date.now() },
        headers: { 'Cache-Control': 'no-cache', Pragma: 'no-cache' },
      });
      const paid = data.paid === true || data.status === 'paid';
      if (data.status != null) pixLastStatus.value = data.status;
      if (paid) {
        const sid = pixPendingSaleId.value;
        pixLastStatus.value = 'paid';
        stopPixPolling();
        pixStep.value = null;
        showingPixQrCode.value = false;
        pixChargeId.value = null;
        pixPendingSaleId.value = null;
        pixQrCode.value = '';
        pixQrCodeBase64.value = '';

        toast.success('Pagamento PIX confirmado!');

        await cartStore.init();
        try {
          const { data: saleData } = await api.get(`sales/${sid}`);
          const sale = saleData?.data ?? saleData?.sale ?? saleData;
          const isCompleted = sale?.status === 'completed' || sale?.status === 'COMPLETED';

          if (isCompleted && sale?.id) {
            openReceiptWindow(sale);
            await new Promise((r) => setTimeout(r, 1000));
            emit('finish', { id: sid, status: 'completed' });
            emit('close');
          } else {
            resetPaymentForm();
            nextTick(() => checkAndShowPaymentInput());
          }
        } catch (err) {
          console.error('Erro ao buscar venda para comprovante:', err);
          resetPaymentForm();
          nextTick(() => checkAndShowPaymentInput());
        }
      }
    } catch (_) {}
  }, 3000);
}

/** Polling por status da venda (fluxo legado: generatePix após finish). */
function startPixSaleStatusPolling() {
  stopPixPolling();
  if (!pixPendingSaleId.value) return;

  pixTimer.value = 30 * 60;
  pixTimerInterval.value = setInterval(() => {
    if (pixTimer.value > 0) pixTimer.value--;
    else stopPixPolling();
  }, 1000);

  pixPollingInterval.value = setInterval(async () => {
    if (!pixPendingSaleId.value) return;
    try {
      const { data } = await api.get('pos/pix/status', { params: { sale_id: pixPendingSaleId.value } });
      if (data.paid) {
        const sid = pixPendingSaleId.value;
        stopPixPolling();
        pixStep.value = null;
        showingPixQrCode.value = false;
        pixPendingSaleId.value = null;
        pixQrCode.value = '';
        pixQrCodeBase64.value = '';

        toast.success('Pagamento PIX confirmado!');
        try {
          const { data: saleData } = await api.get(`sales/${sid}`);
          const sale = saleData?.data ?? saleData?.sale ?? saleData;
          if (sale?.id) {
            openReceiptWindow(sale);
            await new Promise((r) => setTimeout(r, 1000));
          }
        } catch (err) {
          console.error('Erro ao buscar venda para comprovante:', err);
        }
        cartStore.resetState();
        emit('finish', { id: sid, status: 'completed' });
        emit('close');
      }
    } catch (_) {}
  }, 3000);
}

function stopPixPolling() {
  if (pixPollingInterval.value) {
    clearInterval(pixPollingInterval.value);
    pixPollingInterval.value = null;
  }
  if (pixTimerInterval.value) {
    clearInterval(pixTimerInterval.value);
    pixTimerInterval.value = null;
  }
}

function cancelPixFlow() {
  stopPixPolling();
  pixStep.value = null;
  pixPendingSaleId.value = null;
  pixLastStatus.value = null;
  pixQrCode.value = '';
  pixQrCodeBase64.value = '';
  cartStore.resetState();
  emit('close');
}

function cancelPixInFlow() {
  stopPixPolling();
  showingPixQrCode.value = false;
  pixStep.value = null;
  pixChargeId.value = null;
  pixPendingSaleId.value = null;
  pixLastStatus.value = null;
  pixQrCode.value = '';
  pixQrCodeBase64.value = '';
  pixTimer.value = 0;
  
  methodSelected.value = false;
  isProcessingPayment.value = false;
  selectedMethodIndex.value = 0;
  newPayment.value = {
    method: 'cash',
    amount: 0,
    installments: 1,
    cardType: null,
  };
  
  toast.info('Pagamento PIX cancelado. A venda permanece aguardando pagamento.');
}

function retryPixGeneration() {
  if (!pixPendingSaleId.value || !newPayment.value?.amount) return;
  runPixRequestFlow(pixPendingSaleId.value, newPayment.value.amount);
}

function formatTimer(seconds) {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
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
  if (!value || typeof value !== 'string') return 0;
  const cleaned = value.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.').trim();
  const num = parseFloat(cleaned);
  return isNaN(num) ? 0 : num;
}

/** Formata valor para exibição no input: R$ 0,00 (centavos à direita, pt-BR) */
function formatAmountInput(value) {
  let num;
  if (typeof value === 'string') {
    num = parseFloat(value.replace(/\./g, '').replace(',', '.'));
  } else {
    num = typeof value === 'number' ? value : (value ? parseFloat(value) : 0);
  }
  if (isNaN(num) || num < 0) num = 0;
  const fixed = num.toFixed(2);
  const [intPart, decPart] = fixed.split('.');
  const intFormatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `R$ ${intFormatted},${decPart}`;
}

/** Digitação da direita para a esquerda (centavos primeiro): só dígitos, valor = centavos/100 */
function handleAmountInput(e) {
  const digits = (e.target.value || '').replace(/\D/g, '');
  const capped = digits.slice(0, 12);
  const value = parseInt(capped || '0', 10) / 100;
  const formatted = formatAmountInput(value);
  amountFormatted.value = formatted;
  newPayment.value.amount = value;
  nextTick(() => {
    const el = amountInputRef.value;
    if (el && document.activeElement === el) {
      el.setSelectionRange(formatted.length, formatted.length);
    }
  });
}

async function confirmAmountAndProcess() {
  const amount = parseAmount(amountFormatted.value);
  if (!amount || amount <= 0) {
    toast.error('Informe um valor válido');
    return;
  }
  
  if (amount > remainingAmount.value + 0.01) {
    toast.error('Valor maior que o restante a pagar');
    return;
  }
  
  newPayment.value.amount = amount;
  
  // Se for CRÉDITO, busca parcelas antes de processar
  if (newPayment.value.method === 'credit_card') {
    loadingInstallments.value = true;
    try {
      const { data } = await api.get('payments/simulate-installments', { params: { amount } });
      installmentsFromApi.value = data.installments || [];
      
      if (installmentsFromApi.value.length === 0) {
        toast.error('Erro ao buscar opções de parcelamento');
        goBackToMethodList();
        return;
      }
      
      // Mostra seleção de parcelas
      installmentsSelected.value = false;
    } catch (err) {
      toast.error(err?.response?.data?.message || 'Erro ao buscar parcelas.');
      goBackToMethodList();
    } finally {
      loadingInstallments.value = false;
    }
    return;
  }
  
  // Para outros métodos (Dinheiro, PIX, Débito), executa imediatamente
  await confirmAddPayment();
}

async function selectMethod(method) {
  methodSelected.value = true;
  newPayment.value.method = method === 'cash' ? 'money' : method;
  
  // Preenche valor sugerido (restante)
  const remainingVal = parseFloat(remainingAmount.value);
  newPayment.value.amount = remainingVal;
  amountFormatted.value = formatAmountInput(remainingVal);
  
  // CRÉDITO: Busca parcelas após confirmar valor
  if (method === 'credit_card') {
    newPayment.value.cardType = 'credit';
    // Mostra input de valor primeiro, parcelas vem depois
  }
  
  // Foca no input de valor para permitir edição
  nextTick(() => {
    focusAmountInput();
  });
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
    // Usa a nova rota unificada de pagamentos
    const { data } = await api.get('payments/simulate-installments', { params: { amount } });
    installmentsFromApi.value = data.installments || [];
  } catch (err) {
    toast.error(err?.response?.data?.message || err?.message || 'Erro ao buscar parcelas.');
    installmentsFromApi.value = [];
  } finally {
    loadingInstallments.value = false;
  }
}

async function selectInstallmentFromApi(option) {
  newPayment.value.installments = option.installment;
  installmentsSelected.value = true;
  selectedInstallmentIndex.value = option.installment - 1;
  
  // Executa imediatamente sem esperar
  await confirmAddPayment();
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

/**
 * Solicita PIX sem adicionar pagamento. Gera QR e inicia polling por charge_id.
 * O pagamento só é criado no backend quando o webhook confirmar.
 */
async function runPixRequestFlow(saleId, amount) {
  pixGenerating.value = true;
  showingPixQrCode.value = true;
  pixPendingSaleId.value = saleId;
  try {
    const { data } = await api.post('pos/pix/request', { sale_id: saleId, amount });
    pixQrCode.value = data.qr_code || '';
    pixQrCodeBase64.value = data.qr_code_base64 || '';
    pixChargeId.value = data.charge_id ?? null;
    pixStep.value = 'qr';
    startPixChargePolling();
  } catch (e) {
    toast.error(e?.response?.data?.message || e?.message || 'Erro ao gerar PIX.');
    pixStep.value = 'error';
  } finally {
    pixGenerating.value = false;
  }
}

function openReceiptWindow(sale) {
  // Cria uma nova janela com o template do cupom
  const receiptWindow = window.open('', '_blank', 'width=350,height=600');
  
  if (!receiptWindow) {
    toast.warning('Popup bloqueado. Habilite popups para visualizar o cupom.');
    return;
  }
  
  // Constrói o HTML do cupom
  const html = `
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Cupom - Venda #${sale.id}</title>
      <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
          font-family: 'Courier New', monospace; 
          font-size: 12px; 
          line-height: 1.4; 
          padding: 1rem;
          background: white;
        }
        .receipt { width: 80mm; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 1rem; }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 0.5rem; }
        .divider { border-top: 1px dashed #000; margin: 0.75rem 0; }
        .info p, .totals .row, .payments .row { margin: 0.25rem 0; }
        .items table { width: 100%; border-collapse: collapse; margin: 0.5rem 0; }
        .items th, .items td { padding: 0.25rem 0; font-size: 11px; }
        .items thead th { border-bottom: 1px solid #000; font-weight: bold; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals .row, .payments .row { display: flex; justify-content: space-between; }
        .totals .row.total { font-size: 14px; margin-top: 0.5rem; border-top: 1px solid #000; padding-top: 0.5rem; }
        .footer { text-align: center; margin-top: 1rem; }
        .footer .barcode { font-size: 16px; font-weight: bold; letter-spacing: 2px; margin-top: 0.5rem; }
        .actions { margin-top: 2rem; text-align: center; }
        .btn { padding: 0.75rem 2rem; font-size: 14px; margin: 0.5rem; border: none; border-radius: 0.375rem; cursor: pointer; }
        .btn-print { background: #3b82f6; color: white; }
        .btn-close { background: #6b7280; color: white; }
        @media print {
          .actions { display: none; }
          @page { size: 80mm auto; margin: 0; }
        }
      </style>
    </head>
    <body>
      <div class="receipt">
        <div class="header">
          <h1>ADONAI PDV</h1>
          <p>Cupom Não Fiscal</p>
        </div>
        <div class="divider"></div>
        <div class="info">
          <p><strong>Venda #${sale.id}</strong></p>
          <p>Data: ${new Date(sale.created_at || new Date()).toLocaleString('pt-BR')}</p>
          ${sale.customer ? `<p>Cliente: ${sale.customer.name}</p>` : ''}
          <p>Operador: ${authStore.user?.name || 'Operador'}</p>
        </div>
        <div class="divider"></div>
        <div class="items">
          <table>
            <thead>
              <tr>
                <th class="text-left">Item</th>
                <th class="text-center">Qtd</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              ${(sale.items || []).map(item => `
                <tr>
                  <td class="text-left">${item.product_name}</td>
                  <td class="text-center">${item.quantity}</td>
                  <td class="text-right">R$ ${parseFloat(item.total_price).toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        <div class="divider"></div>
        <div class="totals">
          <div class="row">
            <span>Subtotal:</span>
            <span>R$ ${parseFloat(sale.total_amount).toFixed(2)}</span>
          </div>
          ${sale.discount_amount > 0 ? `
            <div class="row">
              <span>Desconto:</span>
              <span>- R$ ${parseFloat(sale.discount_amount).toFixed(2)}</span>
            </div>
          ` : ''}
          <div class="row total">
            <span><strong>TOTAL:</strong></span>
            <span><strong>R$ ${parseFloat(sale.final_amount).toFixed(2)}</strong></span>
          </div>
        </div>
        <div class="divider"></div>
        <div class="payments">
          <p><strong>Formas de Pagamento:</strong></p>
          ${(sale.payments || []).map(p => `
            <div class="row">
              <span>${formatPaymentMethod(p.method)}</span>
              <span>R$ ${parseFloat(p.amount).toFixed(2)}</span>
            </div>
          `).join('')}
        </div>
        <div class="divider"></div>
        <div class="footer">
          <p>Obrigado pela preferência!</p>
          <p>Volte sempre!</p>
          <p class="barcode">*${sale.id}*</p>
        </div>
      </div>
      <div class="actions">
        <button class="btn btn-print" onclick="window.print()">Imprimir (Ctrl+P)</button>
        <button class="btn btn-close" onclick="window.close()">Fechar</button>
      </div>
    </body>
    </html>
  `;
  
  receiptWindow.document.write(html);
  receiptWindow.document.close();
}

async function confirmAddPayment() {
  let finalAmount = newPayment.value.amount;
  
  if (!finalAmount || finalAmount <= 0) {
    return;
  }

  if (finalAmount > remainingAmount.value + 0.01) {
    return;
  }

  isProcessingPayment.value = true;
  
  try {
    const method = newPayment.value.method;
    const installments = newPayment.value.method === 'credit_card' ? newPayment.value.installments : 1;

    // PIX: não chama addPayment; gera QR e só registra pagamento após confirmação (webhook)
    if (method === 'pix') {
      const saleId = cartStore.saleId;
      if (!saleId) {
        toast.error('Venda não iniciada. Inicie uma venda primeiro.');
        return;
      }
      await runPixRequestFlow(saleId, finalAmount);
      return;
    }

    // Adiciona o pagamento ao carrinho (dinheiro, crédito, débito)
    const addResult = await cartStore.addPayment(method, finalAmount, installments);

    // FLUXO: DINHEIRO
    if (method === 'money') {
      toast.success('Pagamento em dinheiro adicionado');
      
      // Se completou o pagamento, finaliza e emite comprovante
      if (addResult?.can_finish) {
        resetPaymentForm();
        const result = await cartStore.finish();
        if (result?.sale) {
          openReceiptWindow(result.sale);
          await new Promise((resolve) => setTimeout(resolve, 1000));
          emit('finish', result.sale);
          emit('close');
        }
        return;
      }
      
      // Se ainda falta pagar, volta para selecionar outro método
      resetPaymentForm();
      nextTick(() => checkAndShowPaymentInput());
      return;
    }

    // FLUXO: CRÉDITO (com parcelas)
    if (method === 'credit_card') {
      toast.success(`Pagamento em ${installments}x adicionado`);
      
      // Se completou o pagamento, finaliza e aguarda confirmação na maquininha
      if (addResult?.can_finish) {
        resetPaymentForm();
        const result = await cartStore.finish();
        
        // Verifica se tem Point pendente
        if (result?.pending_point && result?.sale?.id) {
          pointPendingSale.value = result.sale;
          pointStep.value = 'select_device';
          // ... lógica Point existente
          return;
        }
        
        // Se não é Point, emite comprovante
        if (result?.sale) {
          openReceiptWindow(result.sale);
          await new Promise((resolve) => setTimeout(resolve, 1000));
          emit('finish', result.sale);
          emit('close');
        }
        return;
      }
      
      resetPaymentForm();
      nextTick(() => checkAndShowPaymentInput());
      return;
    }

    // FLUXO: DÉBITO
    if (method === 'debit_card') {
      toast.success('Pagamento em débito adicionado');
      
      if (addResult?.can_finish) {
        resetPaymentForm();
        const result = await cartStore.finish();
        
        // Verifica se tem Point pendente
        if (result?.pending_point && result?.sale?.id) {
          pointPendingSale.value = result.sale;
          pointStep.value = 'select_device';
          // ... lógica Point existente
          return;
        }
        
        if (result?.sale) {
          openReceiptWindow(result.sale);
          await new Promise((resolve) => setTimeout(resolve, 1000));
          emit('finish', result.sale);
          emit('close');
        }
        return;
      }
      
      resetPaymentForm();
      nextTick(() => checkAndShowPaymentInput());
      return;
    }

    // Outros métodos
    resetPaymentForm();
    const stillRemaining = parseFloat(remainingAmount.value);
    if (stillRemaining > 0.01) {
      nextTick(() => checkAndShowPaymentInput());
    }
  } catch (error) {
    console.error('Erro ao adicionar pagamento:', error);
    toast.error(error?.message ?? 'Erro ao adicionar pagamento.');
  } finally {
    isProcessingPayment.value = false;
  }
}

function goBackToMethodList() {
  // Se está nas parcelas, volta para valor
  if (installmentsFromApi.value.length > 0 && !installmentsSelected.value) {
    installmentsFromApi.value = [];
    loadingInstallments.value = false;
    nextTick(() => focusAmountInput());
    return;
  }
  
  // Se está no valor, volta para métodos
  methodSelected.value = false;
  cardTypeSelected.value = false;
  installmentsSelected.value = false;
  selectedMethodIndex.value = 0;
  selectedCardTypeIndex.value = 0;
  selectedInstallmentIndex.value = 0;
  installmentsFromApi.value = [];
  loadingInstallments.value = false;
  amountFormatted.value = '';
  
  newPayment.value = {
    method: 'cash',
    amount: 0,
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
          <input type="text" autocomplete="off" style="position: absolute; opacity: 0; pointer-events: none; height: 0; width: 0;" tabindex="-1" />
          <input
            type="password"
            id="swal-op-password"
            name="manager_auth_password"
            placeholder="Senha"
            autocomplete="off"
            readonly
            onfocus="this.removeAttribute('readonly'); this.style.borderColor='#3b82f6'; this.style.outline='none'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)';"
            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='0 1px 2px 0 rgba(0, 0, 0, 0.05)';"
            spellcheck="false"
            style="width: 100%; padding: 0.625rem 0.75rem; font-size: 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);"
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
  showingPixQrCode.value = false;
  pixChargeId.value = null;
  pixPendingSaleId.value = null;
  pixLastStatus.value = null;
  pixQrCode.value = '';
  pixQrCodeBase64.value = '';
  pixTimer.value = 0;
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
    
    // ESC durante processo de Point
    if (pointStep.value) {
      cancelPointFlow();
      return;
    }
    
    // ESC durante exibição de QR Code PIX - volta para seleção de método
    if (showingPixQrCode.value) {
      cancelPixInFlow();
      return;
    }
    
    // ESC durante adição de pagamento
    if (showAddPayment.value) {
      // Se está nas parcelas, volta para valor
      if (installmentsFromApi.value.length > 0 && !installmentsSelected.value) {
        goBackToMethodList();
        return;
      }
      
      // Se está no valor (método selecionado), volta para métodos
      if (methodSelected.value) {
        goBackToMethodList();
        return;
      }
      
      // Se está nos métodos mas já tem pagamentos adicionados, só fecha o form
      if (payments.value.length > 0) {
        showAddPayment.value = false;
        resetPaymentForm();
        return;
      }
      
      // Se não tem nada, fecha o modal
      emit('close');
      return;
    }
    
    // ESC durante seleção de pagamento para remoção
    if (selectedPaymentIndex.value >= 0 && payments.value.length > 0) {
      selectedPaymentIndex.value = -1;
      paymentRemovalAuthorized.value = false;
      authorizedByUserIdForRemoval.value = null;
      return;
    }
    
    // ESC sem nenhum passo executado - fecha o modal
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

  // PASSO 1: Navegação nos MÉTODOS (grid 2 colunas: ↑↓ muda linha, ←→ muda coluna)
  if (!methodSelected.value && effectivePaymentMethods.value.length > 0) {
    const cols = 2;
    const len = effectivePaymentMethods.value.length;
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedMethodIndex.value = Math.max(0, selectedMethodIndex.value - cols);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedMethodIndex.value = Math.min(len - 1, selectedMethodIndex.value + cols);
      return;
    }
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      selectedMethodIndex.value = Math.max(0, selectedMethodIndex.value - 1);
      return;
    }
    if (e.key === 'ArrowRight') {
      e.preventDefault();
      selectedMethodIndex.value = Math.min(len - 1, selectedMethodIndex.value + 1);
      return;
    }
    
    // ENTER: Selecionar método atual
    if (e.key === 'Enter') {
      e.preventDefault();
      selectMethod(effectivePaymentMethods.value[selectedMethodIndex.value].value);
      return;
    }
    
    // Teclas 1-9: Atalho direto
    const num = parseInt(e.key);
    if (num >= 1 && num <= effectivePaymentMethods.value.length) {
      e.preventDefault();
      selectedMethodIndex.value = num - 1;
      selectMethod(effectivePaymentMethods.value[num - 1].value);
      return;
    }
    return;
  }

  // PASSO 2: Confirmar VALOR (já tem ENTER no input)
  // Sem navegação aqui, apenas digitação

  // PASSO 3: Navegação nas PARCELAS (crédito)
  if (methodSelected.value && newPayment.value.method === 'credit_card' && installmentsFromApi.value.length > 0 && !installmentsSelected.value) {
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.max(0, selectedInstallmentIndex.value - 1);
      nextTick(() => {
        const el = installmentsListRef.value?.children[selectedInstallmentIndex.value];
        el?.scrollIntoView({ block: 'nearest' });
      });
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedInstallmentIndex.value = Math.min(installmentsFromApi.value.length - 1, selectedInstallmentIndex.value + 1);
      nextTick(() => {
        const el = installmentsListRef.value?.children[selectedInstallmentIndex.value];
        el?.scrollIntoView({ block: 'nearest' });
      });
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      const opt = installmentsFromApi.value[selectedInstallmentIndex.value];
      selectInstallmentFromApi(opt);
      return;
    }
    
    // Teclas 1-9: Atalho direto para parcelas
    const num = parseInt(e.key);
    if (num >= 1 && num <= Math.min(9, installmentsFromApi.value.length)) {
      e.preventDefault();
      selectedInstallmentIndex.value = num - 1;
      const opt = installmentsFromApi.value[num - 1];
      selectInstallmentFromApi(opt);
      return;
    }
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
