import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCustomerStore = defineStore('customer', {
  state: () => ({
    customers: [],
    currentCustomer: null,
    loading: false,
    error: null,
    pagination: null,
  }),
  actions: {
    async fetchAll(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/customers', { params });
        this.customers = response.data.data || response.data || [];
        this.pagination = response.data.meta || null;
        return this.customers;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar clientes';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchOne(id) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/customers/${id}`);
        this.currentCustomer = response.data;
        return this.currentCustomer;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar cliente';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async create(customerData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/customers', customerData);
        const newCustomer = response.data;
        this.customers.unshift(newCustomer);
        return newCustomer;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao criar cliente';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async update(id, customerData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.put(`/customers/${id}`, customerData);
        const updatedCustomer = response.data;
        const index = this.customers.findIndex((c) => c.id === id);
        if (index !== -1) {
          this.customers[index] = updatedCustomer;
        }
        if (this.currentCustomer?.id === id) {
          this.currentCustomer = updatedCustomer;
        }
        return updatedCustomer;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao atualizar cliente';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async delete(id) {
      this.loading = true;
      this.error = null;

      try {
        await api.delete(`/customers/${id}`);
        this.customers = this.customers.filter((c) => c.id !== id);
        if (this.currentCustomer?.id === id) {
          this.currentCustomer = null;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao excluir cliente';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    clearCurrent() {
      this.currentCustomer = null;
    },
  },
});
