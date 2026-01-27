<template>
  <div class="space-y-4">
    <!-- Cabeçalho -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Vendas Realizadas</h2>
        <p class="text-xs text-slate-500">
          Visualize e gerencie todas as vendas
        </p>
      </div>
      
      <div v-if="authStore.hasRole(['super-admin', 'manager'])" class="flex gap-2">
        <button
          @click="exportToExcel"
          :disabled="exporting"
          class="bg-green-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-green-700 transition-colors flex items-center gap-2"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          {{ exporting ? 'Exportando...' : 'Exportar Excel' }}
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
      <!-- Busca -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Buscar</label>
        <input
          v-model="filters.search"
          type="text"
          placeholder="ID da venda ou nome do cliente..."
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          @input="debouncedSearch"
        >
      </div>

      <!-- Status -->
      <div>
        <SearchableSelect
          v-model="filters.status"
          label="Status"
          :options="statusOptions"
          placeholder="Todos"
          @update:model-value="applyFilters"
        />
      </div>

      <!-- Data -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Data</label>
        <input
          v-model="filters.date"
          type="date"
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          @change="applyFilters"
        >
      </div>

      <!-- Ações -->
      <div class="flex items-end">
        <Button variant="outline" size="sm" @click="clearFilters">
          Limpar Filtros
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading && !sales.length" class="flex items-center justify-center py-12">
      <div class="text-center">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
        <p class="mt-4 text-sm text-slate-600">Carregando vendas...</p>
      </div>
    </div>

    <!-- Erro -->
    <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-4">
      <p class="text-sm font-medium text-red-800">{{ error }}</p>
    </div>

    <!-- Tabela -->
    <div v-else class="overflow-x-auto bg-white border border-slate-200 rounded-md">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
              ID
            </th>
            <th v-if="authStore.isSuperAdmin" scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
              Filial
            </th>
            <th v-if="authStore.isManager || authStore.isSuperAdmin" scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
              Vendedor
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
              Data
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wider">
              Cliente
            </th>
            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-700 uppercase tracking-wider">
              Valor Total
            </th>
            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-700 uppercase tracking-wider">
              Status
            </th>
            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-700 uppercase tracking-wider">
              Ações
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-slate-200">
          <tr v-if="!sales.length">
            <td :colspan="getColspan()" class="px-4 py-8 text-center text-sm text-slate-500">
              Nenhuma venda encontrada.
            </td>
          </tr>
          <tr v-for="sale in sales" :key="sale.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-slate-900">
              #{{ sale.id }}
            </td>
            <td v-if="authStore.isSuperAdmin" class="px-4 py-3 whitespace-nowrap text-sm text-slate-700">
              {{ sale.branch_name || '-' }}
            </td>
            <td v-if="authStore.isManager || authStore.isSuperAdmin" class="px-4 py-3 whitespace-nowrap text-sm text-slate-700">
              {{ sale.user_name }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-700">
              {{ formatDate(sale.created_at) }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-700">
              {{ sale.customer_name }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-slate-900 text-right">
              {{ formatCurrency(sale.final_amount) }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-center">
              <span :class="getStatusBadgeClass(sale.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                {{ getStatusLabel(sale.status) }}
              </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
              <div class="flex items-center justify-end gap-2">
                <button
                  v-if="canCancelSale(sale)"
                  @click="confirmCancelSale(sale)"
                  class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50 transition-colors"
                  title="Cancelar Venda"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
                <button
                  @click="viewSale(sale)"
                  class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50 transition-colors"
                  title="Ver Detalhes"
                >
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <div v-if="pagination.total > pagination.per_page" class="flex items-center justify-between px-4 py-3 bg-white border-t border-slate-200">
      <div class="flex-1 flex justify-between sm:hidden">
        <button
          @click="goToPage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Anterior
        </button>
        <button
          @click="goToPage(pagination.current_page + 1)"
          :disabled="pagination.current_page === pagination.last_page"
          class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Próxima
        </button>
      </div>
      <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
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
          <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Paginação">
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page === 1"
              class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="sr-only">Anterior</span>
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
              </svg>
            </button>
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="goToPage(page)"
              :disabled="page === '...'"
              :class="[
                page === pagination.current_page
                  ? 'z-10 bg-blue-600 border-blue-600 text-white'
                  : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50',
                page === '...' ? 'cursor-default' : '',
                'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
              ]"
            >
              {{ page }}
            </button>
            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page === pagination.last_page"
              class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="sr-only">Próxima</span>
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
              </svg>
            </button>
          </nav>
        </div>
      </div>
    </div>

    <!-- Drawer de Detalhes -->
    <SaleDetailsDrawer
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
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import SaleDetailsDrawer from '@/components/Sales/SaleDetailsDrawer.vue';

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

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'completed', label: 'Concluída' },
  { value: 'pending_payment', label: 'Aguardando Pagamento' },
  { value: 'canceled', label: 'Cancelada' },
];

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
