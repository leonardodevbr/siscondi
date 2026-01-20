<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Fornecedores</h2>
        <p class="text-xs text-slate-500">
          Gerencie o cadastro de fornecedores
        </p>
      </div>
      <button
        @click="showFormModal = true; editingSupplier = null"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors w-full md:w-auto"
      >
        Novo Fornecedor
      </button>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome, razão social, CNPJ, email ou telefone..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
      </div>

      <div v-if="supplierStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando fornecedores...</p>
      </div>

      <div v-else-if="supplierStore.suppliers.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhum fornecedor encontrado</p>
      </div>

      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Nome Fantasia
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Razão Social
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                CNPJ
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Telefone
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Status
              </th>
              <th class="sticky right-0 bg-slate-50 px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider shadow-[-4px_0px_6px_-2px_rgba(0,0,0,0.1)]">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="supplier in supplierStore.suppliers" :key="supplier.id">
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm font-medium text-slate-900 truncate max-w-xs">
                  {{ supplier.trade_name || '-' }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm text-slate-900 truncate max-w-xs">{{ supplier.name }}</div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ formatCnpj(supplier.cnpj) }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ formatPhone(supplier.phone) || '-' }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    supplier.active
                      ? 'bg-green-100 text-green-800'
                      : 'bg-red-100 text-red-800',
                  ]"
                >
                  {{ supplier.active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="sticky right-0 bg-white px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium shadow-[-4px_0px_6px_-2px_rgba(0,0,0,0.1)]">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="editSupplier(supplier)"
                    class="text-amber-600 hover:text-amber-900 p-1 rounded hover:bg-amber-50 transition-colors"
                    title="Editar"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    @click="deleteSupplier(supplier)"
                    class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50 transition-colors"
                    title="Excluir"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="supplierStore.pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-slate-500">
          Mostrando {{ supplierStore.pagination.from }} a {{ supplierStore.pagination.to }} de
          {{ supplierStore.pagination.total }} resultados
        </div>
        <div class="flex gap-2">
          <button
            v-if="supplierStore.pagination.current_page > 1"
            @click="changePage(supplierStore.pagination.current_page - 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Anterior
          </button>
          <button
            v-if="supplierStore.pagination.current_page < supplierStore.pagination.last_page"
            @click="changePage(supplierStore.pagination.current_page + 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>

    <SupplierForm
      v-if="showFormModal"
      :supplier="editingSupplier"
      @close="showFormModal = false; editingSupplier = null"
      @saved="handleSaved"
    />
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useSupplierStore } from '@/stores/supplier';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useAlert } from '@/composables/useAlert';
import SupplierForm from './Form.vue';

let searchTimeout = null;

export default {
  name: 'SuppliersIndex',
  components: {
    SupplierForm,
    PencilSquareIcon,
    TrashIcon,
  },
  setup() {
    const toast = useToast();
    const { confirm } = useAlert();
    const supplierStore = useSupplierStore();
    const searchQuery = ref('');
    const showFormModal = ref(false);
    const editingSupplier = ref(null);

    const loadSuppliers = async () => {
      try {
        const params = {};
        if (searchQuery.value) {
          params.search = searchQuery.value;
        }
        await supplierStore.fetchAll(params);
      } catch (error) {
        toast.error('Erro ao carregar fornecedores');
      }
    };

    const debouncedSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadSuppliers();
      }, 500);
    };

    const changePage = (page) => {
      loadSuppliers({ page });
    };

    const editSupplier = (supplier) => {
      editingSupplier.value = supplier;
      showFormModal.value = true;
    };

    const deleteSupplier = async (supplier) => {
      const confirmed = await confirm(
        'Excluir Fornecedor',
        `Tem certeza que deseja excluir o fornecedor "${supplier.name}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmed) {
        return;
      }

      try {
        await supplierStore.delete(supplier.id);
        toast.success('Fornecedor excluído com sucesso!');
        loadSuppliers();
      } catch (error) {
        toast.error('Erro ao excluir fornecedor');
      }
    };

    const handleSaved = () => {
      showFormModal.value = false;
      editingSupplier.value = null;
      loadSuppliers();
    };

    const formatCnpj = (value) => {
      if (!value) return '-';
      const cleaned = value.replace(/\D/g, '');
      if (cleaned.length === 14) {
        return cleaned.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
      }
      return value;
    };

    const formatPhone = (value) => {
      if (!value) return '';
      const cleaned = value.replace(/\D/g, '');
      if (cleaned.length === 10) {
        return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
      } else if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
      }
      return value;
    };

    onMounted(() => {
      loadSuppliers();
    });

    return {
      supplierStore,
      searchQuery,
      showFormModal,
      editingSupplier,
      loadSuppliers,
      debouncedSearch,
      changePage,
      editSupplier,
      deleteSupplier,
      handleSaved,
      formatCnpj,
      formatPhone,
    };
  },
};
</script>
