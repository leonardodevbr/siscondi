<template>
  <Teleport to="body">
    <Transition name="drawer" :duration="{ enter: 300, leave: 250 }">
      <div v-if="isOpen" class="fixed inset-0 z-50 flex" @keydown.esc="$emit('close')">
        <div
          class="drawer-overlay fixed inset-0 bg-black/50 z-40"
          aria-hidden="true"
        />
        <div
          class="drawer-panel fixed inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl z-50 flex flex-col"
          role="dialog"
          aria-label="Detalhes da Venda"
          tabindex="-1"
        >
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div class="flex-1">
              <div class="flex items-center gap-4">
                <div>
                  <h2 class="text-lg font-semibold text-slate-800">
                    Venda #{{ sale?.id }}
                  </h2>
                  <p v-if="sale" class="text-sm text-slate-500">{{ formatDate(sale.created_at) }}</p>
                </div>
                <div v-if="showBranchInfo && sale?.branch_name" class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200">
                  <span class="text-xs font-medium uppercase tracking-wider text-slate-600">Filial:</span>
                  <span class="text-sm font-medium text-slate-900">{{ sale.branch_name }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="sale" :class="getStatusBadgeClass(sale.status)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">
                {{ getStatusLabel(sale.status) }}
              </span>
              <button
                type="button"
                class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                aria-label="Fechar"
                @click="$emit('close')"
              >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto">
            <div v-if="loading" class="flex items-center justify-center py-12">
              <div class="text-center">
                <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                <p class="mt-4 text-sm text-slate-600">Carregando detalhes...</p>
              </div>
            </div>

            <div v-else-if="error" class="m-6 rounded-lg border border-red-200 bg-red-50 p-4">
              <p class="text-sm font-medium text-red-800">{{ error }}</p>
            </div>

            <div v-else-if="sale" class="p-6 space-y-6">
              <!-- Informações Gerais -->
              <div class="grid grid-cols-2 gap-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                  <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600 mb-2">Cliente</h4>
                  <p class="text-sm font-medium text-slate-900">{{ sale.customer_name }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                  <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600 mb-2">Vendedor</h4>
                  <p class="text-sm font-medium text-slate-900">{{ sale.user_name }}</p>
                </div>
                <div v-if="sale.coupon" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                  <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600 mb-2">Cupom</h4>
                  <p class="text-sm font-medium text-slate-900">{{ sale.coupon.code }}</p>
                </div>
              </div>

              <!-- Itens da Venda -->
              <div>
                <h4 class="mb-3 text-sm font-semibold text-slate-900">Itens da Venda</h4>
                <div class="overflow-hidden rounded-lg border border-slate-200">
                  <div class="overflow-y-auto" style="max-height: calc(100vh - 600px); min-height: 200px;">
                    <table class="w-full">
                      <thead class="bg-slate-50 sticky top-0 z-10">
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
                            <div class="flex items-center gap-2 mt-1">
                              <span class="text-xs text-slate-500">SKU: {{ item.product.sku }}</span>
                              <span v-if="item.variant?.size || item.variant?.color" class="text-xs text-slate-400">|</span>
                              <span v-if="item.variant?.size" class="text-xs font-medium text-slate-600">{{ item.variant.size }}</span>
                              <span v-if="item.variant?.color" class="text-xs font-medium text-slate-600">{{ item.variant.color }}</span>
                            </div>
                          </td>
                          <td class="px-4 py-3 text-center text-sm text-slate-700">{{ item.quantity }}</td>
                          <td class="px-4 py-3 text-right text-sm text-slate-700">{{ formatCurrency(item.unit_price) }}</td>
                          <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">{{ formatCurrency(item.total_price) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Observações -->
              <div v-if="sale.note" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h4 class="text-xs font-medium uppercase tracking-wider text-slate-600 mb-2">Observações</h4>
                <p class="text-sm text-slate-700">{{ sale.note }}</p>
              </div>
            </div>
          </div>

          <!-- Footer Fixo com Pagamentos e Totais -->
          <div v-if="sale && !loading && !error" class="border-t border-slate-200 bg-white">
            <!-- Pagamentos -->
            <div class="px-6 py-4 border-b border-slate-200">
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
            <div class="px-6 py-4 bg-slate-50">
              <div class="space-y-2">
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
            </div>

            <!-- Botões de Ação -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
              <Button v-if="canCancel" variant="danger" @click="$emit('cancel', sale)">
                Cancelar Venda
              </Button>
              <Button variant="outline" @click="$emit('close')">
                Fechar
              </Button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
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
const appStore = useAppStore();
const sale = ref(null);
const loading = ref(false);
const error = ref(null);

const canCancel = computed(() => {
  if (!sale.value || sale.value.status === 'canceled') return false;
  return authStore.hasRole(['super-admin', 'manager']);
});

const showBranchInfo = computed(() => {
  // Mostrar apenas para Gerente ou Super Admin
  if (!authStore.hasRole(['super-admin', 'manager'])) {
    return false;
  }
  // Mostrar apenas se tiver mais de 1 filial
  return appStore.branches && appStore.branches.length > 1;
});

watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.saleId) {
    // Garantir que as filiais estão carregadas
    if (appStore.branches.length === 0) {
      appStore.fetchBranches();
    }
    fetchSaleDetails();
  } else {
    sale.value = null;
    error.value = null;
  }
});

function handleEscKey(event) {
  if (event.key === 'Escape' && props.isOpen) {
    emit('close');
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleEscKey);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscKey);
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

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-leave-active {
  transition: all 250ms cubic-bezier(0.4, 0, 0.6, 1);
}

.drawer-enter-from .drawer-overlay,
.drawer-leave-to .drawer-overlay {
  opacity: 0;
}

.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
  transform: translateX(100%);
}

.drawer-enter-to .drawer-overlay,
.drawer-leave-from .drawer-overlay {
  opacity: 1;
}

.drawer-enter-to .drawer-panel,
.drawer-leave-from .drawer-panel {
  transform: translateX(0);
}
</style>
