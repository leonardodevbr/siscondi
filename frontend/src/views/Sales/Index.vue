<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-slate-800">
        Vendas Realizadas
      </h1>
    </div>

    <div v-if="loading && !sales.length" class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
        <p class="mt-4 text-sm text-slate-600">Carregando vendas...</p>
      </div>
    </div>

    <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4">
      <p class="text-sm font-medium text-red-800">{{ error }}</p>
    </div>

    <div v-else class="space-y-4">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                  ID
                </th>
                <th v-if="authStore.isSuperAdmin" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                  Filial
                </th>
                <th v-if="authStore.isManager || authStore.isSuperAdmin" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                  Vendedor
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                  Data
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-600">
                  Cliente
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-600">
                  Valor Total
                </th>
                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-600">
                  Status
                </th>
                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-600">
                  Ações
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-if="!sales.length">
                <td :colspan="getColspan()" class="px-4 py-8 text-center text-sm text-slate-500">
                  Nenhuma venda encontrada.
                </td>
              </tr>
              <tr v-for="sale in sales" :key="sale.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-3 text-sm font-medium text-slate-900">
                  #{{ sale.id }}
                </td>
                <td v-if="authStore.isSuperAdmin" class="px-4 py-3 text-sm text-slate-700">
                  {{ sale.branch_name || '-' }}
                </td>
                <td v-if="authStore.isManager || authStore.isSuperAdmin" class="px-4 py-3 text-sm text-slate-700">
                  {{ sale.user_name }}
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  {{ formatDate(sale.created_at) }}
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">
                  {{ sale.customer_name }}
                </td>
                <td class="px-4 py-3 text-right text-sm font-medium text-slate-900">
                  {{ formatCurrency(sale.final_amount) }}
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="getStatusBadgeClass(sale.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                    {{ getStatusLabel(sale.status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="viewSale(sale)"
                    class="text-blue-600 hover:text-blue-800 text-sm font-medium transition-colors"
                  >
                    Ver Detalhes
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="pagination.total > pagination.per_page" class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-lg">
        <div class="flex flex-1 justify-between sm:hidden">
          <button
            @click="goToPage(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Anterior
          </button>
          <button
            @click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Próxima
          </button>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-slate-700">
              Mostrando
              <span class="font-medium">{{ pagination.from }}</span>
              até
              <span class="font-medium">{{ pagination.to }}</span>
              de
              <span class="font-medium">{{ pagination.total }}</span>
              resultados
            </p>
          </div>
          <div>
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
              <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span class="sr-only">Anterior</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="goToPage(page)"
                :class="[
                  page === pagination.current_page
                    ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                    : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:outline-offset-0',
                  'relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20'
                ]"
              >
                {{ page }}
              </button>
              <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span class="sr-only">Próxima</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                </svg>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

const toast = useToast();
const authStore = useAuthStore();

const sales = ref([]);
const loading = ref(false);
const error = ref(null);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
});

const visiblePages = computed(() => {
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  const delta = 2;
  const range = [];
  const rangeWithDots = [];

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }

  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }

  rangeWithDots.push(...range);

  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }

  return rangeWithDots.filter((v, i, a) => a.indexOf(v) === i && v !== '...' || v === '...');
});

function getColspan() {
  let cols = 6; // ID, Data, Cliente, Valor, Status, Ações
  if (authStore.isSuperAdmin) cols += 2; // Filial + Vendedor
  else if (authStore.isManager) cols += 1; // Vendedor
  return cols;
}

async function fetchSales(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await api.get('/sales', { params: { page } });
    sales.value = data.data || [];
    pagination.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      per_page: data.per_page || 15,
      total: data.total || 0,
      from: data.from || 0,
      to: data.to || 0,
    };
  } catch (e) {
    error.value = e?.response?.data?.message || 'Erro ao carregar vendas.';
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.last_page) return;
  fetchSales(page);
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

function viewSale(sale) {
  toast.info(`Visualizar detalhes da venda #${sale.id} (em desenvolvimento)`);
  // TODO: Implementar modal ou navegação para detalhes da venda
}

onMounted(() => {
  fetchSales();
});
</script>
