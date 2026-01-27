<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-slate-800">
        Vendas Realizadas
      </h1>
      <Button v-if="authStore.hasRole(['super-admin', 'manager'])" variant="primary" @click="exportToExcel" :disabled="exporting">
        {{ exporting ? 'Exportando...' : 'Exportar Excel' }}
      </Button>
    </div>

    <!-- Filtros e Busca -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Busca -->
        <div class="lg:col-span-2">
          <label class="block text-xs font-medium text-slate-700 mb-1">Buscar</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="ID da venda ou nome do cliente..."
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            @input="debouncedSearch"
          >
        </div>

        <!-- Status -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            @change="applyFilters"
          >
            <option value="">Todos</option>
            <option value="completed">Concluída</option>
            <option value="pending_payment">Aguardando Pagamento</option>
            <option value="canceled">Cancelada</option>
          </select>
        </div>

        <!-- Data -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Data</label>
          <input
            v-model="filters.date"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            @change="applyFilters"
          >
        </div>
      </div>

      <!-- Botões de Ação -->
      <div class="mt-4 flex items-center gap-2">
        <Button variant="outline" size="sm" @click="clearFilters">
          Limpar Filtros
        </Button>
        <span v-if="hasActiveFilters" class="text-xs text-slate-600">
          {{ filteredCount }} resultado(s) encontrado(s)
        </span>
      </div>
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
                  <div class="flex items-center justify-center gap-2">
                    <button
                      @click="viewSale(sale)"
                      class="text-blue-600 hover:text-blue-800 text-sm font-medium transition-colors"
                    >
                      Ver Detalhes
                    </button>
                    <button
                      v-if="canCancelSale(sale)"
                      @click="confirmCancelSale(sale)"
                      class="text-red-600 hover:text-red-800 text-sm font-medium transition-colors"
                    >
                      Cancelar
                    </button>
                  </div>
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

    <!-- Modal de Detalhes -->
    <SaleDetailsModal
      :is-open="showDetailsModal"
      :sale-id="selectedSale"
      @close="handleModalClose"
      @cancel="handleCancelFromModal"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import Swal from 'sweetalert2';
import Button from '@/components/Common/Button.vue';
import SaleDetailsModal from '@/components/Sales/SaleDetailsModal.vue';

const toast = useToast();
const authStore = useAuthStore();

const sales = ref([]);
const loading = ref(false);
const error = ref(null);
const exporting = ref(false);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: 0,
  to: 0,
});

const filters = ref({
  search: '',
  status: '',
  date: '',
});

const selectedSale = ref(null);
const showDetailsModal = ref(false);

let searchTimeout = null;

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

const hasActiveFilters = computed(() => {
  return filters.value.search || filters.value.status || filters.value.date;
});

const filteredCount = computed(() => pagination.value.total);

async function fetchSales(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = { page };
    
    if (filters.value.search) {
      const searchValue = filters.value.search.trim();
      if (/^\d+$/.test(searchValue)) {
        params.id = searchValue;
      } else {
        params.customer_name = searchValue;
      }
    }
    
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    
    if (filters.value.date) {
      params.date = filters.value.date;
    }
    
    const { data } = await api.get('/sales', { params });
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

function debouncedSearch() {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
}

function applyFilters() {
  fetchSales(1);
}

function clearFilters() {
  filters.value = {
    search: '',
    status: '',
    date: '',
  };
  fetchSales(1);
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
  selectedSale.value = sale.id;
  showDetailsModal.value = true;
}

function canCancelSale(sale) {
  if (sale.status === 'canceled') return false;
  return authStore.hasRole(['super-admin', 'manager']);
}

async function confirmCancelSale(sale) {
  const result = await Swal.fire({
    title: 'Cancelar Venda?',
    html: `Tem certeza que deseja cancelar a venda <strong>#${sale.id}</strong>?<br><small class="text-slate-600">Esta ação não pode ser desfeita.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Sim, Cancelar',
    cancelButtonText: 'Não',
  });

  if (result.isConfirmed) {
    await cancelSale(sale);
  }
}

async function cancelSale(sale) {
  try {
    await api.delete(`/sales/${sale.id}`);
    toast.success('Venda cancelada com sucesso!');
    fetchSales(pagination.value.current_page);
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Erro ao cancelar venda.');
  }
}

async function exportToExcel() {
  exporting.value = true;
  try {
    const params = {};
    
    if (filters.value.search) {
      const searchValue = filters.value.search.trim();
      if (/^\d+$/.test(searchValue)) {
        params.id = searchValue;
      } else {
        params.customer_name = searchValue;
      }
    }
    
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    
    if (filters.value.date) {
      params.date = filters.value.date;
    }
    
    const response = await api.get('/sales/export', {
      params,
      responseType: 'blob',
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `vendas-${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    
    toast.success('Exportação concluída!');
  } catch (e) {
    toast.error(e?.response?.data?.message || 'Erro ao exportar vendas.');
  } finally {
    exporting.value = false;
  }
}

function handleModalClose() {
  showDetailsModal.value = false;
  selectedSale.value = null;
}

function handleCancelFromModal(sale) {
  showDetailsModal.value = false;
  selectedSale.value = null;
  confirmCancelSale(sale);
}

onMounted(() => {
  fetchSales();
});
</script>
