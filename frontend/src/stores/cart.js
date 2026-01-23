import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCartStore = defineStore('cart', {
  state: () => ({
    saleId: null,
    items: [],
    customer: null,
    totalAmount: 0,
    discountAmount: 0,
    finalAmount: 0,
    payments: [],
    saleStarted: false,
  }),
  getters: {
    subtotal(state) {
      return state.totalAmount;
    },
    totalCount(state) {
      return state.items.reduce((sum, item) => sum + item.quantity, 0);
    },
    totalPayments(state) {
      return state.payments.reduce((sum, payment) => sum + payment.amount, 0);
    },
    remainingAmount(state) {
      return Math.max(0, state.finalAmount - state.totalPayments);
    },
    canFinish(state) {
      return state.remainingAmount <= 0 && state.items.length > 0;
    },
  },
  actions: {
    async init() {
      try {
        const { data } = await api.get('/pos/active-sale');
        if (data.sale) {
          this.syncFromSale(data.sale);
          this.saleStarted = true;
        } else {
          this.reset();
        }
      } catch (error) {
        console.error('Erro ao buscar venda ativa:', error);
        this.reset();
      }
    },
    async startSale(customerId = null, branchId = null) {
      try {
        const { data } = await api.post('/pos/start', {
          branch_id: branchId,
          customer_id: customerId,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
          this.saleStarted = true;
        } else if (data.message) {
          throw new Error(data.message);
        }
      } catch (error) {
        const message = error.response?.data?.message || error.message || 'Erro ao iniciar venda.';
        throw new Error(message);
      }
    },
    async addItem(barcode, quantity = 1) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada. Inicie uma venda primeiro.');
      }
      try {
        const { data } = await api.post('/pos/add-item', {
          barcode,
          quantity,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao adicionar item.';
        throw new Error(message);
      }
    },
    async removeItem(itemId) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/remove-item', {
          sale_id: this.saleId,
          item_id: itemId,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao remover item.';
        throw new Error(message);
      }
    },
    async removeItemByCode(barcode) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/remove-item-by-code', {
          barcode,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao remover item.';
        throw new Error(message);
      }
    },
    async setCustomer(customerId) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/identify-customer', {
          sale_id: this.saleId,
          customer_id: customerId,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao identificar cliente.';
        throw new Error(message);
      }
    },
    async applyDiscount(type, value) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/apply-discount', {
          sale_id: this.saleId,
          type,
          value,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao aplicar desconto.';
        throw new Error(message);
      }
    },
    async addPayment(method, amount, installments = 1) {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/add-payment', {
          method,
          amount,
          installments,
        });
        if (data.sale) {
          this.syncFromSale(data.sale);
        }
        return data;
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao adicionar pagamento.';
        throw new Error(message);
      }
    },
    async cancel() {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/cancel', {
          sale_id: this.saleId,
        });
        this.reset();
        return data;
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao cancelar venda.';
        throw new Error(message);
      }
    },
    async finish() {
      if (!this.saleId) {
        throw new Error('Venda não iniciada.');
      }
      try {
        const { data } = await api.post('/pos/finish');
        this.reset();
        return data;
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao finalizar venda.';
        throw new Error(message);
      }
    },
    syncFromSale(sale) {
      this.saleId = sale.id;
      this.items = sale.items || [];
      this.customer = sale.customer;
      this.totalAmount = sale.total_amount || 0;
      this.discountAmount = sale.discount_amount || 0;
      this.finalAmount = sale.final_amount || 0;
      this.payments = sale.payments || [];
    },
    reset() {
      this.saleId = null;
      this.items = [];
      this.customer = null;
      this.totalAmount = 0;
      this.discountAmount = 0;
      this.finalAmount = 0;
      this.payments = [];
      this.saleStarted = false;
    },
  },
});
