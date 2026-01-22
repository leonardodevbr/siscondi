<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Movimentações de Estoque</h2>
        <p class="text-xs text-slate-500">
          Auditoria de entradas, saídas e ajustes
        </p>
      </div>
    </div>

    <div class="card p-4 sm:p-6">
      <!-- Filtros -->
      <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por produto ou SKU..."
            class="w-full h-10 px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @input="debouncedSearch"
          />
        </div>
        <div>
          <select
            v-model="filters.type"
            class="w-full h-10 px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadMovements"
          >
            <option value="">Todos os tipos</option>
            <option value="entry">Entrada</option>
            <option value="exit">Saída</option>
            <option value="adjustment">Ajuste</option>
            <option value="return">Devolução</option>
          </select>
        </div>
        <div>
          <SearchableSelect
            v-model="filters.user_id"
            :options="userOptions"
            placeholder="Todos os usuários"
            @update:modelValue="loadMovements"
          />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full h-10 px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadMovements"
          />
          <input
            v-model="filters.date_to"
            type="date"
            class="w-full h-10 px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadMovements"
          />
        </div>
      </div>

      <!-- Tabela -->
      <div v-if="loading" class="text-center py-8">
        <p class="text-slate-500">Carregando movimentações...</p>
      </div>

      <div v-else-if="movements.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhuma movimentação encontrada</p>
      </div>

      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Data
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Produto
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Tipo
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Quantidade
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Responsável
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Motivo
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="movement in movements" :key="movement.id">
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ movement.created_at }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm font-medium text-slate-900">
                  {{ movement.product_name }}
                </div>
                <div v-if="movement.variation" class="text-xs text-slate-500 mt-0.5">
                  <span v-if="movement.variation.sku">SKU: {{ movement.variation.sku }}</span>
                  <span v-else-if="movement.variation.barcode">Código: {{ movement.variation.barcode }}</span>
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    movement.type === 'entry' || movement.type === 'return'
                      ? 'bg-green-100 text-green-800'
                      : movement.type === 'adjustment'
                      ? 'bg-blue-100 text-blue-800'
                      : 'bg-red-100 text-red-800',
                  ]"
                >
                  {{ movement.type_label }}
                </span>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div
                  :class="[
                    'text-sm font-medium',
                    movement.operation === 'add' ? 'text-green-600' : 'text-red-600',
                  ]"
                >
                  {{ movement.quantity_display }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-600">
                  {{ movement.user?.name || 'N/A' }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm text-slate-600 max-w-xs truncate">
                  {{ movement.reason || '-' }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="pagination && movements.length > 0" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-slate-500">
          Mostrando {{ pagination.from }} a {{ pagination.to }} de
          {{ pagination.total }} resultados
        </div>
        <div class="flex gap-2">
          <button
            v-if="pagination.current_page > 1"
            @click="changePage(pagination.current_page - 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Anterior
          </button>
          <button
            v-if="pagination.current_page < pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useToast } from 'vue-toastification';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import api from '@/services/api';

let searchTimeout = null;

export default {
  name: 'MovementsIndex',
  components: {
    SearchableSelect,
  },
  setup() {
    const toast = useToast();
    const loading = ref(false);
    const movements = ref([]);
    const pagination = ref(null);
    const users = ref([]);

    const filters = ref({
      search: '',
      type: '',
      user_id: null,
      date_from: '',
      date_to: '',
    });

    const userOptions = computed(() => {
      return users.value.map((user) => ({
        value: user.id,
        label: user.name,
      }));
    });

    const loadUsers = async () => {
      try {
        const response = await api.get('/inventory/users');
        users.value = response.data.data || [];
      } catch (error) {
        console.error('Erro ao carregar usuários:', error);
      }
    };

    const loadMovements = async (page = 1) => {
      try {
        loading.value = true;
        const params = {
          page,
          ...filters.value,
        };

        // Remove campos vazios
        Object.keys(params).forEach((key) => {
          if (params[key] === '' || params[key] === null) {
            delete params[key];
          }
        });

        const response = await api.get('/inventory/movements', { params });
        movements.value = response.data.data || [];
        pagination.value = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total,
          from: (response.data.meta.current_page - 1) * response.data.meta.per_page + 1,
          to: Math.min(
            response.data.meta.current_page * response.data.meta.per_page,
            response.data.meta.total
          ),
        };
      } catch (error) {
        toast.error('Erro ao carregar movimentações');
        movements.value = [];
        pagination.value = null;
      } finally {
        loading.value = false;
      }
    };

    const debouncedSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadMovements(1);
      }, 500);
    };

    const changePage = (page) => {
      loadMovements(page);
    };

    onMounted(async () => {
      await loadUsers();
      await loadMovements();
    });

    return {
      loading,
      movements,
      pagination,
      filters,
      userOptions,
      loadMovements,
      debouncedSearch,
      changePage,
    };
  },
};
</script>
