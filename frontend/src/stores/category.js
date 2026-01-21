import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCategoryStore = defineStore('category', {
  state: () => ({
    items: [],
    loading: false,
  }),
  actions: {
    async fetchAll(params = {}) {
      this.loading = true;
      try {
        const response = await api.get('/categories', { params });
        this.items = response.data.data || response.data || [];
      } catch (error) {
        console.error('Erro ao carregar categorias:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchForFilter() {
      await this.fetchAll({ only_active: true });
    },
  },
});

