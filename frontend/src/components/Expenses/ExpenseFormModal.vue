<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
      >
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50 transition-opacity" @click="close"></div>
          
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

              <!-- Toggle: Despesa Fixa/Futura -->
              <div v-if="!expense" class="flex items-center justify-between py-2 px-3 bg-slate-50 rounded-lg border border-slate-200">
                <label class="text-sm font-medium text-slate-700">
                  Despesa Fixa/Futura
                </label>
                <button
                  type="button"
                  @click.prevent="hasSchedule = !hasSchedule"
                  :class="[
                    'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                    hasSchedule ? 'bg-blue-600' : 'bg-slate-300'
                  ]"
                  role="switch"
                  :aria-checked="hasSchedule"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      hasSchedule ? 'translate-x-4' : 'translate-x-0'
                    ]"
                  ></span>
                </button>
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
                <div class="relative flex items-center">
                  <span class="absolute left-3 text-sm text-slate-500 pointer-events-none">R$</span>
                  <input
                    v-model="displayAmount"
                    type="text"
                    inputmode="numeric"
                    required
                    placeholder="0,00"
                    @input="handleAmountInput"
                    @blur="formatAmountOnBlur"
                    class="w-full rounded-md border border-slate-300 pl-11 pr-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                </div>
              </div>

              <!-- Data de Vencimento (se toggle ativo) -->
              <div v-if="hasSchedule || expense">
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

              <!-- Forma de Pagamento (se toggle desativado) -->
              <div v-if="!hasSchedule && !expense" class="space-y-2">
                <label class="block text-sm font-medium text-slate-700">
                  Forma de Pagamento <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                  <label
                    v-for="method in paymentMethods"
                    :key="method.value"
                    class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-slate-50 transition-colors"
                    :class="{ 'border-blue-500 bg-blue-50': paymentMethod === method.value }"
                  >
                    <input
                      v-model="paymentMethod"
                      type="radio"
                      :value="method.value"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300"
                    >
                    <span class="ml-2 text-sm font-medium text-slate-900">{{ method.label }}</span>
                  </label>
                </div>
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

const displayAmount = ref('');
const hasSchedule = ref(false);
const paymentMethod = ref('');
const submitting = ref(false);

const categoryOptions = computed(() => {
  return props.categories.map(cat => ({
    value: cat.id,
    label: cat.name,
  }));
});

const paymentMethods = [
  { value: 'CASH', label: 'Dinheiro' },
  { value: 'BANK_TRANSFER', label: 'Transferência/PIX' },
  { value: 'CREDIT_CARD', label: 'Cartão de Crédito' },
];

const handleAmountInput = (event) => {
  let value = event.target.value;
  
  // Remove tudo exceto dígitos
  value = value.replace(/\D/g, '');
  
  // Converte para número e divide por 100 (centavos)
  const numValue = parseInt(value || '0') / 100;
  
  // Atualiza o valor do form (para envio)
  form.value.amount = numValue.toFixed(2);
  
  // Formata para exibição
  displayAmount.value = numValue.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatAmountOnBlur = () => {
  if (form.value.amount) {
    const numValue = parseFloat(form.value.amount);
    displayAmount.value = numValue.toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }
};

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
      displayAmount.value = parseFloat(props.expense.amount).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      hasSchedule.value = false;
      paymentMethod.value = '';
    } else {
      // Criação
      form.value = {
        description: '',
        amount: '',
        due_date: '',
        expense_category_id: null,
      };
      displayAmount.value = '';
      hasSchedule.value = false;
      paymentMethod.value = '';
    }
  }
});

const handleSubmit = async () => {
  try {
    // Validação: Se não tem agendamento, precisa escolher forma de pagamento
    if (!hasSchedule.value && !paymentMethod.value && !props.expense) {
      toast.warning('Selecione a forma de pagamento');
      return;
    }
    
    submitting.value = true;
    
    const payload = { ...form.value };
    
    // Se não tem agendamento, usa a data de hoje
    if (!hasSchedule.value && !props.expense) {
      payload.due_date = new Date().toISOString().split('T')[0];
    }
    
    if (props.expense) {
      // Editar
      await api.put(`/expenses/${props.expense.id}`, payload);
    } else {
      // Criar
      const response = await api.post('/expenses', payload);
      
      // Se não tem agendamento, paga automaticamente
      if (!hasSchedule.value) {
        const expenseId = response.data.id;
        await api.post(`/expenses/${expenseId}/pay`, {
          payment_method: paymentMethod.value,
        });
        toast.success('Despesa criada e paga com sucesso!');
      }
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
