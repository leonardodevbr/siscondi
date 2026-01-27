<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="close"
      >
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
          
          <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
              <h3 class="text-lg font-semibold text-slate-900">
                {{ expense ? 'Editar Despesa' : 'Nova Despesa' }}
              </h3>
              <button
                @click="close"
                class="text-slate-400 hover:text-slate-600 transition-colors"
              >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
              <!-- Descrição -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Descrição <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.description"
                  type="text"
                  required
                  placeholder="Ex: Conta de Luz"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
              </div>

              <!-- Categoria -->
              <div>
                <SearchableSelect
                  v-model="form.expense_category_id"
                  label="Categoria"
                  :options="categoryOptions"
                  placeholder="Selecione uma categoria"
                />
              </div>

              <!-- Valor -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Valor <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.amount"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  placeholder="0,00"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
              </div>

              <!-- Data de Vencimento -->
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Data de Vencimento <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.due_date"
                  type="date"
                  required
                  class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
              </div>

              <!-- Botões -->
              <div class="flex items-center justify-end gap-3 pt-4">
                <button
                  type="button"
                  @click="close"
                  class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 transition-colors"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="submitting"
                  class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ submitting ? 'Salvando...' : 'Salvar' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  expense: {
    type: Object,
    default: null,
  },
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'success']);
const toast = useToast();

const form = ref({
  description: '',
  amount: '',
  due_date: '',
  expense_category_id: null,
});

const submitting = ref(false);

const categoryOptions = computed(() => {
  return props.categories.map(cat => ({
    value: cat.id,
    label: cat.name,
  }));
});

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    if (props.expense) {
      // Edição
      form.value = {
        description: props.expense.description,
        amount: props.expense.amount,
        due_date: props.expense.due_date,
        expense_category_id: props.expense.category?.id || null,
      };
    } else {
      // Criação
      form.value = {
        description: '',
        amount: '',
        due_date: '',
        expense_category_id: null,
      };
    }
  }
});

const handleSubmit = async () => {
  try {
    submitting.value = true;
    
    if (props.expense) {
      // Editar
      await api.put(`/expenses/${props.expense.id}`, form.value);
    } else {
      // Criar
      await api.post('/expenses', form.value);
    }
    
    emit('success');
  } catch (error) {
    console.error('Erro ao salvar despesa:', error);
    toast.error(error.response?.data?.message || 'Erro ao salvar despesa');
  } finally {
    submitting.value = false;
  }
};

const close = () => {
  emit('close');
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
