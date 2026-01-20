import { defineStore } from 'pinia';
import api from '@/services/api';

export const useSupplierStore = defineStore('supplier', {
  state: () => ({
    suppliers: [],
    currentSupplier: null,
    loading: false,
    error: null,
    pagination: null,
  }),
  actions: {
    async fetchAll(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/suppliers', { params });
        this.suppliers = response.data.data || response.data || [];
        this.pagination = response.data.meta || null;
        return this.suppliers;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar fornecedores';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchOne(id) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/suppliers/${id}`);
        this.currentSupplier = response.data;
        return this.currentSupplier;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar fornecedor';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async create(supplierData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/suppliers', supplierData);
        const newSupplier = response.data;
        this.suppliers.unshift(newSupplier);
        return newSupplier;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao criar fornecedor';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async update(id, supplierData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.put(`/suppliers/${id}`, supplierData);
        const updatedSupplier = response.data;
        const index = this.suppliers.findIndex((s) => s.id === id);
        if (index !== -1) {
          this.suppliers[index] = updatedSupplier;
        }
        if (this.currentSupplier?.id === id) {
          this.currentSupplier = updatedSupplier;
        }
        return updatedSupplier;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao atualizar fornecedor';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async delete(id) {
      this.loading = true;
      this.error = null;

      try {
        await api.delete(`/suppliers/${id}`);
        this.suppliers = this.suppliers.filter((s) => s.id !== id);
        if (this.currentSupplier?.id === id) {
          this.currentSupplier = null;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao excluir fornecedor';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    clearCurrent() {
      this.currentSupplier = null;
    },
  },
});
