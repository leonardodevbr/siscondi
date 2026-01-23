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
        </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="font-medium text-slate-700">Pagamentos:</span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="showAddPayment = true"
          >
            + Adicionar Pagamento
          </Button>
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
                  ({{ payment.installments }}x)
                </span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-slate-700">{{ formatCurrency(payment.amount) }}</span>
              <button
                type="button"
                class="text-red-600 hover:text-red-700"
                @click="removePayment(index)"
              >
                <XCircleIcon class="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showAddPayment" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
        <h4 class="mb-3 text-sm font-semibold text-slate-800">Adicionar Pagamento</h4>
        <div class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Método</label>
            <select
              v-model="newPayment.method"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="cash">Dinheiro</option>
              <option value="credit_card">Cartão de Crédito</option>
              <option value="debit_card">Cartão de Débito</option>
              <option value="pix">PIX</option>
            </select>
          </div>
          <div v-if="newPayment.method === 'credit_card'">
            <label class="mb-1 block text-xs font-medium text-slate-700">Parcelas</label>
            <input
              v-model.number="newPayment.installments"
              type="number"
              min="1"
              max="12"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Valor</label>
            <input
              v-model.number="newPayment.amount"
              type="number"
              step="0.01"
              min="0.01"
              :max="remainingAmount"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="0.00"
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="cancelAddPayment">Cancelar</Button>
            <Button variant="primary" size="sm" @click="addPayment">Adicionar</Button>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
        <Button variant="outline" @click="$emit('close')">Voltar</Button>
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
import { ref, computed, watch } from 'vue';
import { useCartStore } from '@/stores/cart';
import { useToast } from 'vue-toastification';
import { formatCurrency } from '@/utils/format';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';
import { XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(['close', 'finish']);

const cartStore = useCartStore();
const toast = useToast();

const showAddPayment = ref(false);
const newPayment = ref({
  method: 'cash',
  amount: 0,
  installments: 1,
});

const finalAmount = computed(() => cartStore.finalAmount);
const totalPayments = computed(() => cartStore.totalPayments);
const remainingAmount = computed(() => cartStore.remainingAmount);
const canFinish = computed(() => cartStore.canFinish);
const payments = computed(() => cartStore.payments);

watch(() => props.isOpen, (open) => {
  if (open) {
    newPayment.value.amount = remainingAmount.value;
    showAddPayment.value = false;
  }
});

function formatPaymentMethod(method) {
  const methods = {
    cash: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    pix: 'PIX',
  };
  return methods[method] || method;
}

async function addPayment() {
  if (!newPayment.value.amount || newPayment.value.amount <= 0) {
    toast.error('Informe um valor válido.');
    return;
  }

  if (newPayment.value.amount > remainingAmount.value) {
    toast.error('Valor excede o restante a pagar.');
    return;
  }

  try {
    await cartStore.addPayment(
      newPayment.value.method,
      newPayment.value.amount,
      newPayment.value.installments
    );
    newPayment.value.amount = remainingAmount.value;
    showAddPayment.value = false;
    toast.success('Pagamento adicionado.');
  } catch (error) {
    toast.error(error.message || 'Erro ao adicionar pagamento.');
  }
}

function cancelAddPayment() {
  showAddPayment.value = false;
  newPayment.value = {
    method: 'cash',
    amount: remainingAmount.value,
    installments: 1,
  };
}

function removePayment(index) {
  // Note: Backend não tem endpoint para remover pagamento individual
  // Por enquanto, apenas resetamos a venda ou recriamos
  toast.info('Para remover pagamento, cancele e reinicie a venda.');
}
</script>
