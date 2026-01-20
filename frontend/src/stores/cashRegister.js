import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCashRegisterStore = defineStore('cashRegister', {
  state: () => ({
    isOpen: false,
    balance: 0,
    registerId: null,
    openedAt: null,
    initialBalance: 0,
    loading: false,
    error: null,
  }),
  actions: {
    async checkStatus() {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/cash-register/status');
        const { is_open, cash_register } = response.data;

        if (is_open && cash_register) {
          this.isOpen = true;
          this.registerId = cash_register.id;
          this.balance = parseFloat(cash_register.current_balance);
          this.initialBalance = parseFloat(cash_register.initial_balance);
          this.openedAt = cash_register.opened_at;
        } else {
          this.isOpen = false;
          this.registerId = null;
          this.balance = 0;
          this.initialBalance = 0;
          this.openedAt = null;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao verificar status do caixa.';
        this.isOpen = false;
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async openRegister(initialBalance) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/cash-register/open', {
          initial_balance: initialBalance,
        });

        const { cash_register } = response.data;
        this.isOpen = true;
        this.registerId = cash_register.id;
        this.balance = parseFloat(cash_register.current_balance);
        this.initialBalance = parseFloat(cash_register.initial_balance);
        this.openedAt = cash_register.opened_at;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao abrir o caixa.';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async closeRegister(finalBalance) {
      if (!this.registerId) {
        throw new Error('Nenhum caixa aberto para fechar.');
      }

      this.loading = true;
      this.error = null;

      try {
        await api.post(`/cash-register/${this.registerId}/close`, {
          final_balance: finalBalance,
        });

        this.isOpen = false;
        this.registerId = null;
        this.balance = 0;
        this.initialBalance = 0;
        this.openedAt = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao fechar o caixa.';
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});
