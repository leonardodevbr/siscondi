<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Clientes</h2>
        <p class="text-xs text-slate-500">
          Gerencie o cadastro de clientes
        </p>
      </div>
      <button
        @click="showFormModal = true; editingCustomer = null"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors w-full md:w-auto"
      >
        Novo Cliente
      </button>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome, CPF/CNPJ, email ou telefone..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
      </div>

      <div v-if="customerStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando clientes...</p>
      </div>

      <div v-else-if="customerStore.customers.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhum cliente encontrado</p>
      </div>

      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Nome
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                CPF/CNPJ
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Email
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Telefone
              </th>
              <th class="sticky right-0 bg-slate-50 px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider shadow-[-4px_0px_6px_-2px_rgba(0,0,0,0.1)]">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="customer in customerStore.customers" :key="customer.id">
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm font-medium text-slate-900 truncate max-w-xs">
                  {{ customer.name }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ formatCpfCnpj(customer.cpf_cnpj) }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm text-slate-900 truncate max-w-xs">
                  {{ customer.email || '-' }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ formatPhone(customer.phone) || '-' }}
                </div>
              </td>
              <td class="sticky right-0 bg-white px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium shadow-[-4px_0px_6px_-2px_rgba(0,0,0,0.1)]">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="editCustomer(customer)"
                    class="text-amber-600 hover:text-amber-900 p-1 rounded hover:bg-amber-50 transition-colors"
                    title="Editar"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    @click="deleteCustomer(customer)"
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

      <div v-if="customerStore.pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-slate-500">
          Mostrando {{ customerStore.pagination.from }} a {{ customerStore.pagination.to }} de
          {{ customerStore.pagination.total }} resultados
        </div>
        <div class="flex gap-2">
          <button
            v-if="customerStore.pagination.current_page > 1"
            @click="changePage(customerStore.pagination.current_page - 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Anterior
          </button>
          <button
            v-if="customerStore.pagination.current_page < customerStore.pagination.last_page"
            @click="changePage(customerStore.pagination.current_page + 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>

    <CustomerForm
      v-if="showFormModal"
      :customer="editingCustomer"
      @close="showFormModal = false; editingCustomer = null"
      @saved="handleSaved"
    />
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useCustomerStore } from '@/stores/customer';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useAlert } from '@/composables/useAlert';
import CustomerForm from './Form.vue';

let searchTimeout = null;

export default {
  name: 'CustomersIndex',
  components: {
    CustomerForm,
    PencilSquareIcon,
    TrashIcon,
  },
  setup() {
    const toast = useToast();
    const { confirm } = useAlert();
    const customerStore = useCustomerStore();
    const searchQuery = ref('');
    const showFormModal = ref(false);
    const editingCustomer = ref(null);

    const loadCustomers = async () => {
      try {
        const params = {};
        if (searchQuery.value) {
          params.search = searchQuery.value;
        }
        await customerStore.fetchAll(params);
      } catch (error) {
        toast.error('Erro ao carregar clientes');
      }
    };

    const debouncedSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadCustomers();
      }, 500);
    };

    const changePage = (page) => {
      loadCustomers({ page });
    };

    const editCustomer = (customer) => {
      editingCustomer.value = customer;
      showFormModal.value = true;
    };

    const deleteCustomer = async (customer) => {
      const confirmed = await confirm(
        'Excluir Cliente',
        `Tem certeza que deseja excluir o cliente "${customer.name}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmed) {
        return;
      }

      try {
        await customerStore.delete(customer.id);
        toast.success('Cliente excluído com sucesso!');
        loadCustomers();
      } catch (error) {
        toast.error('Erro ao excluir cliente');
      }
    };

    const handleSaved = () => {
      showFormModal.value = false;
      editingCustomer.value = null;
      loadCustomers();
    };

    const formatCpfCnpj = (value) => {
      if (!value) return '-';
      const cleaned = value.replace(/\D/g, '');
      if (cleaned.length === 11) {
        return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
      } else if (cleaned.length === 14) {
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
      loadCustomers();
    });

    return {
      customerStore,
      searchQuery,
      showFormModal,
      editingCustomer,
      loadCustomers,
      debouncedSearch,
      changePage,
      editCustomer,
      deleteCustomer,
      handleSaved,
      formatCpfCnpj,
      formatPhone,
    };
  },
};
</script>
