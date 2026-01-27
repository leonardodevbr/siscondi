<template>
  <Modal :is-open="isOpen" title="Detalhes da Venda" size="xl" @close="$emit('close')">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
        <p class="mt-4 text-sm text-slate-600">Carregando detalhes...</p>
      </div>
    </div>

    <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4">
      <p class="text-sm font-medium text-red-800">{{ error }}</p>
    </div>

    <div v-else-if="sale" class="space-y-6">
      <!-- Cabeçalho -->
      <div class="flex items-start justify-between border-b border-slate-200 pb-4">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Venda #{{ sale.id }}</h3>
          <p class="mt-1 text-sm text-slate-600">{{ formatDate(sale.created_at) }}</p>
        </div>
        <span :class="getStatusBadgeClass(sale.status)" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium">
          {{ getStatusLabel(sale.status) }}
        </span>
      </div>

      <!-- Informações Gerais -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600">Cliente</h4>
          <p class="mt-2 text-sm font-medium text-slate-900">{{ sale.customer_name }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600">Vendedor</h4>
          <p class="mt-2 text-sm font-medium text-slate-900">{{ sale.user_name }}</p>
        </div>
        <div v-if="sale.branch_name" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600">Filial</h4>
          <p class="mt-2 text-sm font-medium text-slate-900">{{ sale.branch_name }}</p>
        </div>
        <div v-if="sale.coupon" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600">Cupom</h4>
          <p class="mt-2 text-sm font-medium text-slate-900">{{ sale.coupon.code }}</p>
        </div>
      </div>

      <!-- Itens da Venda -->
      <div>
        <h4 class="mb-3 text-sm font-semibold text-slate-900">Itens da Venda</h4>
        <div class="overflow-hidden rounded-lg border border-slate-200">
          <table class="w-full">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">Produto</th>
                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-600">Qtd</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">Preço Unit.</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="item in sale.items" :key="item.id">
                <td class="px-4 py-3 text-sm text-slate-900">
                  <div class="font-medium">{{ item.product.name }}</div>
                  <div class="text-xs text-slate-500">SKU: {{ item.product.sku }}</div>
                </td>
                <td class="px-4 py-3 text-center text-sm text-slate-700">{{ item.quantity }}</td>
                <td class="px-4 py-3 text-right text-sm text-slate-700">{{ formatCurrency(item.unit_price) }}</td>
                <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">{{ formatCurrency(item.total_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagamentos -->
      <div>
        <h4 class="mb-3 text-sm font-semibold text-slate-900">Pagamentos</h4>
        <div class="space-y-2">
          <div v-for="payment in sale.payments" :key="payment.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center gap-3">
              <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                {{ getPaymentMethodLabel(payment.method) }}
              </span>
              <span v-if="payment.installments > 1" class="text-xs text-slate-600">
                {{ payment.installments }}x
              </span>
            </div>
            <span class="text-sm font-medium text-slate-900">{{ formatCurrency(payment.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Totais -->
      <div class="space-y-2 border-t border-slate-200 pt-4">
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-600">Subtotal:</span>
          <span class="font-medium text-slate-900">{{ formatCurrency(sale.total_amount) }}</span>
        </div>
        <div v-if="sale.discount_amount > 0" class="flex items-center justify-between text-sm">
          <span class="text-slate-600">Desconto:</span>
          <span class="font-medium text-red-600">- {{ formatCurrency(sale.discount_amount) }}</span>
        </div>
        <div class="flex items-center justify-between border-t border-slate-200 pt-2">
          <span class="text-base font-semibold text-slate-900">Total:</span>
          <span class="text-xl font-bold text-slate-900">{{ formatCurrency(sale.final_amount) }}</span>
        </div>
      </div>

      <!-- Observações -->
      <div v-if="sale.note" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600">Observações</h4>
        <p class="mt-2 text-sm text-slate-700">{{ sale.note }}</p>
      </div>

      <!-- Ações -->
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <Button v-if="canCancel" variant="danger" @click="$emit('cancel', sale)">
          Cancelar Venda
        </Button>
        <Button variant="outline" @click="$emit('close')">
          Fechar
        </Button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import Modal from '@/components/Common/Modal.vue';
import Button from '@/components/Common/Button.vue';
import api from '@/services/api';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  saleId: {
    type: Number,
    default: null,
  },
});

const emit = defineEmits(['close', 'cancel']);

const authStore = useAuthStore();
const sale = ref(null);
const loading = ref(false);
const error = ref(null);

const canCancel = computed(() => {
  if (!sale.value || sale.value.status === 'canceled') return false;
  return authStore.hasRole(['super-admin', 'manager']);
});

watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.saleId) {
    fetchSaleDetails();
  } else {
    sale.value = null;
    error.value = null;
  }
});

async function fetchSaleDetails() {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get(`/sales/${props.saleId}`);
    sale.value = data;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erro ao carregar detalhes da venda.';
  } finally {
    loading.value = false;
  }
}

function formatDate(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function formatCurrency(value) {
  if (value == null) return 'R$ 0,00';
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value);
}

function getStatusLabel(status) {
  const labels = {
    completed: 'Concluída',
    pending_payment: 'Aguardando Pagamento',
    canceled: 'Cancelada',
  };
  return labels[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    completed: 'bg-green-100 text-green-800',
    pending_payment: 'bg-yellow-100 text-yellow-800',
    canceled: 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-slate-100 text-slate-800';
}

function getPaymentMethodLabel(method) {
  const labels = {
    money: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    pix: 'PIX',
    point: 'Mercado Pago Point',
  };
  return labels[method] || method;
}
</script>
