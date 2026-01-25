<template>
  <div class="flex items-center justify-center h-[80vh] bg-slate-50 p-4">
    <div class="w-full max-w-md text-center">
      <div class="mb-8 flex justify-center">
        <div class="rounded-full bg-slate-100 p-6">
          <LockClosedIcon class="h-16 w-16 text-slate-400" />
        </div>
      </div>

      <h1 class="text-2xl font-semibold text-slate-800 mb-2">
        O Caixa está Fechado
      </h1>
      <p class="text-slate-600 mb-8">
        Para iniciar as vendas de hoje, por favor abra o caixa.
      </p>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            Saldo Inicial (R$)
          </label>
          <input
            v-model.number="initialBalance"
            type="number"
            step="0.01"
            min="0"
            placeholder="0,00"
            class="w-full px-4 py-3 text-lg text-center border-2 border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            @keyup.enter="handleOpen"
            autofocus
          />
        </div>

        <button
          @click="handleOpen"
          :disabled="loading || !isValid"
          class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg text-base font-medium hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
        >
          <span v-if="loading">Abrindo...</span>
          <span v-else>Abrir Caixa</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useToast } from 'vue-toastification';
import { LockClosedIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'PosClosedState',
  components: {
    LockClosedIcon,
  },
  setup() {
    const cashRegisterStore = useCashRegisterStore();
    const toast = useToast();
    const initialBalance = ref('');
    const loading = computed(() => cashRegisterStore.loading);

    const isValid = computed(() => {
      const balance = parseFloat(initialBalance.value);
      return initialBalance.value !== '' && !isNaN(balance) && balance >= 0;
    });

    const handleOpen = async () => {
      const balance = parseFloat(initialBalance.value);

      if (!initialBalance.value || isNaN(balance) || balance < 0) {
        toast.error('Informe um saldo inicial válido (maior ou igual a zero).');
        return;
      }

      try {
        await cashRegisterStore.openRegister(balance);
        toast.success('Caixa aberto com sucesso!');
        initialBalance.value = '';
        if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen().catch(() => {});
        }
      } catch (error) {
        const message = error.response?.data?.message || error.message || 'Erro ao abrir o caixa.';
        toast.error(message);
      }
    };

    return {
      initialBalance,
      loading,
      isValid,
      handleOpen,
    };
  },
};
</script>
