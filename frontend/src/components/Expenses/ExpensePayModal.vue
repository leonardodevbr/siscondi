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
          
          <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
              <h3 class="text-lg font-semibold text-slate-900">
                Pagar Despesa
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
            <div v-if="expense" class="p-6 space-y-4">
              <!-- Info da Despesa -->
              <div class="bg-slate-50 rounded-lg p-4 space-y-2">
                <div class="flex justify-between">
                  <span class="text-sm text-slate-600">Descrição:</span>
                  <span class="text-sm font-medium text-slate-900">{{ expense.description }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-slate-600">Valor:</span>
                  <span class="text-lg font-bold text-slate-900">{{ formatCurrency(expense.amount) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-sm text-slate-600">Vencimento:</span>
                  <span class="text-sm font-medium text-slate-900">{{ formatDate(expense.due_date) }}</span>
                </div>
              </div>

              <!-- Forma de Pagamento -->
              <form @submit.prevent="handlePay">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-2">
                    Forma de Pagamento <span class="text-red-500">*</span>
                  </label>
                  <div class="space-y-2">
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
                      <span class="ml-3 text-sm font-medium text-slate-900">{{ method.label }}</span>
                    </label>
                  </div>
                </div>

                <!-- Alerta -->
                <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                  <svg class="h-5 w-5 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <p class="text-xs text-amber-800">
                    Esta ação registrará o pagamento no caixa atual. Certifique-se de que há um caixa aberto.
                  </p>
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
                    :disabled="!paymentMethod || submitting"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                  >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ submitting ? 'Processando...' : 'Confirmar Pagamento' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import { useToast } from 'vue-toastification';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  expense: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'success']);
const toast = useToast();

const paymentMethod = ref('');
const submitting = ref(false);

const paymentMethods = [
  { value: 'money', label: 'Dinheiro' },
  { value: 'pix', label: 'PIX' },
  { value: 'debit_card', label: 'Cartão de Débito' },
  { value: 'credit_card', label: 'Cartão de Crédito' },
];

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    paymentMethod.value = '';
  }
});

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('pt-BR');
};

const handlePay = async () => {
  try {
    submitting.value = true;
    
    await api.post(`/expenses/${props.expense.id}/pay`, {
      payment_method: paymentMethod.value,
    });
    
    emit('success');
  } catch (error) {
    console.error('Erro ao pagar despesa:', error);
    toast.error(error.response?.data?.message || 'Erro ao processar pagamento');
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
