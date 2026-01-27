<template>
  <div class="space-y-4">
    <!-- Cabeçalho -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Despesas</h2>
        <p class="text-xs text-slate-500">Gerencie as despesas da loja</p>
      </div>
      
      <button
        @click="openCreateModal"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors flex items-center gap-2"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nova Despesa
      </button>
    </div>

    <!-- Filtros -->
    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
      <!-- Busca -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Buscar</label>
        <input
          v-model="filters.search"
          type="text"
          placeholder="Descrição da despesa..."
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          @input="debouncedSearch"
        >
      </div>

      <!-- Status Pagamento -->
      <div>
        <SearchableSelect
          v-model="filters.paid"
          label="Status Pagamento"
          :options="paidStatusOptions"
          placeholder="Todos"
          @update:model-value="applyFilters"
        />
      </div>

      <!-- Data Início -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Data Início</label>
        <input
          v-model="filters.start_date"
          type="date"
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          @change="applyFilters"
        >
      </div>

      <!-- Data Fim -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Data Fim</label>
        <input
          v-model="filters.end_date"
          type="date"
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          @change="applyFilters"
        >
      </div>
    </div>

    <!-- Tabela -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <div v-else-if="expenses.length === 0" class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="mt-2 text-sm text-slate-600">Nenhuma despesa encontrada</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Descrição</th>
              <th v-if="authStore.isSuperAdmin" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Filial</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Categoria</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Vencimento</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Valor</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="expense in expenses" :key="expense.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-sm text-slate-900">{{ expense.description }}</td>
              <td v-if="authStore.isSuperAdmin" class="px-4 py-3 text-sm text-slate-600">{{ expense.branch_name || '-' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600">{{ expense.category?.name || '-' }}</td>
              <td class="px-4 py-3 text-sm text-slate-600">{{ formatDate(expense.due_date) }}</td>
              <td class="px-4 py-3 text-sm text-right font-medium text-slate-900">{{ formatCurrency(expense.amount) }}</td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': expense.is_paid,
                    'bg-red-100 text-red-800': expense.is_overdue && !expense.is_paid,
                    'bg-yellow-100 text-yellow-800': !expense.is_paid && !expense.is_overdue
                  }"
                >
                  {{ getStatusLabel(expense) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-right">
                <div class="flex items-center justify-end gap-2">
                  <!-- Pagar -->
                  <button
                    v-if="!expense.is_paid"
                    @click="openPayModal(expense)"
                    class="text-green-600 hover:text-green-700"
                    title="Pagar"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                  
                  <!-- Editar -->
                  <button
                    v-if="!expense.is_paid"
                    @click="openEditModal(expense)"
                    class="text-blue-600 hover:text-blue-700"
                    title="Editar"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  
                  <!-- Deletar -->
                  <button
                    v-if="!expense.is_paid"
                    @click="confirmDelete(expense)"
                    class="text-red-600 hover:text-red-700"
                    title="Excluir"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-slate-200 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="text-sm text-slate-700">
            Mostrando <span class="font-medium">{{ pagination.from }}</span> a <span class="font-medium">{{ pagination.to }}</span> de <span class="font-medium">{{ pagination.total }}</span> resultados
          </div>
          
          <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
            <button
              @click="changePage(pagination.current_page - 1)"
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
              @click="changePage(page)"
              :class="[
                page === pagination.current_page
                  ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                  : 'bg-white border-slate-300 text-slate-500 hover:bg-slate-50',
                'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
              ]"
            >
              {{ page }}
            </button>
            
            <button
              @click="changePage(pagination.current_page + 1)"
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

    <!-- Modal de Criar/Editar -->
    <ExpenseFormModal
      :is-open="showFormModal"
      :expense="selectedExpense"
      :categories="categories"
      @close="closeFormModal"
      @success="handleFormSuccess"
    />

    <!-- Modal de Pagamento -->
    <ExpensePayModal
      :is-open="showPayModal"
      :expense="selectedExpense"
      @close="closePayModal"
      @success="handlePaySuccess"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import Swal from 'sweetalert2';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import ExpenseFormModal from '@/components/Expenses/ExpenseFormModal.vue';
import ExpensePayModal from '@/components/Expenses/ExpensePayModal.vue';

const toast = useToast();
const authStore = useAuthStore();

const expenses = ref([]);
const categories = ref([]);
const loading = ref(false);
const showFormModal = ref(false);
const showPayModal = ref(false);
const selectedExpense = ref(null);

const filters = ref({
  search: '',
  paid: '',
  start_date: '',
  end_date: '',
});

const paidStatusOptions = [
  { value: '', label: 'Todos' },
  { value: 'true', label: 'Pagas' },
  { value: 'false', label: 'Pendentes' },
];

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
  
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }
  
  if (current - delta > 2) {
    range.unshift('...');
  }
  if (current + delta < last - 1) {
    range.push('...');
  }
  
  range.unshift(1);
  if (last > 1) {
    range.push(last);
  }
  
  return range.filter(page => page !== '...' || range.indexOf(page) === range.lastIndexOf(page));
});

let searchTimeout = null;

const debouncedSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
};

const fetchExpenses = async (page = 1) => {
  try {
    loading.value = true;
    const params = {
      page,
      ...Object.fromEntries(Object.entries(filters.value).filter(([_, v]) => v !== '')),
    };
    
    const response = await api.get('/expenses', { params });
    expenses.value = response.data.data;
    pagination.value = {
      current_page: response.data.meta.current_page,
      last_page: response.data.meta.last_page,
      per_page: response.data.meta.per_page,
      total: response.data.meta.total,
      from: response.data.meta.from,
      to: response.data.meta.to,
    };
  } catch (error) {
    console.error('Erro ao buscar despesas:', error);
    toast.error('Erro ao carregar despesas');
  } finally {
    loading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const response = await api.get('/expense-categories');
    categories.value = response.data.data || response.data;
  } catch (error) {
    console.error('Erro ao buscar categorias:', error);
  }
};

const applyFilters = () => {
  fetchExpenses(1);
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchExpenses(page);
  }
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('pt-BR');
};

const getStatusLabel = (expense) => {
  if (expense.is_paid) return 'Paga';
  if (expense.is_overdue) return 'Vencida';
  return 'Pendente';
};

const openCreateModal = () => {
  selectedExpense.value = null;
  showFormModal.value = true;
};

const openEditModal = (expense) => {
  selectedExpense.value = expense;
  showFormModal.value = true;
};

const closeFormModal = () => {
  showFormModal.value = false;
  selectedExpense.value = null;
};

const handleFormSuccess = () => {
  closeFormModal();
  fetchExpenses(pagination.value.current_page);
  toast.success(selectedExpense.value ? 'Despesa atualizada com sucesso!' : 'Despesa criada com sucesso!');
};

const openPayModal = (expense) => {
  selectedExpense.value = expense;
  showPayModal.value = true;
};

const closePayModal = () => {
  showPayModal.value = false;
  selectedExpense.value = null;
};

const handlePaySuccess = () => {
  closePayModal();
  fetchExpenses(pagination.value.current_page);
  toast.success('Despesa paga com sucesso!');
};

const confirmDelete = async (expense) => {
  const result = await Swal.fire({
    title: 'Excluir despesa?',
    text: `Tem certeza que deseja excluir "${expense.description}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Sim, excluir',
    cancelButtonText: 'Cancelar',
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/expenses/${expense.id}`);
      toast.success('Despesa excluída com sucesso!');
      fetchExpenses(pagination.value.current_page);
    } catch (error) {
      console.error('Erro ao excluir despesa:', error);
      toast.error('Erro ao excluir despesa');
    }
  }
};

onMounted(() => {
  fetchExpenses();
  fetchCategories();
});
</script>
