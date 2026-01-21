import { defineStore } from 'pinia';
import api from '@/services/api';

export const useAppStore = defineStore('app', {
  state: () => ({
    currentBranch: JSON.parse(window.localStorage.getItem('currentBranch') || 'null'),
    branches: [],
    loadingBranches: false,
  }),
  actions: {
    async fetchBranches() {
      if (this.loadingBranches) return;
      this.loadingBranches = true;
      try {
        // Solicita todas as branches sem paginação
        const response = await api.get('/branches?all=1');
        // A resposta vem como { data: [...] }
        let items = [];
        if (response.data) {
          if (Array.isArray(response.data)) {
            items = response.data;
          } else if (response.data.data && Array.isArray(response.data.data)) {
            items = response.data.data;
          }
        }
        this.branches = items;
        if (!this.currentBranch && items.length > 0) {
          this.setBranch(items[0]);
        }
      } catch (e) {
        console.error('Erro ao carregar filiais', e);
        console.error('Detalhes do erro:', e.response?.data || e.message);
      } finally {
        this.loadingBranches = false;
      }
    },
    setBranch(branch) {
      this.currentBranch = branch ? { id: branch.id, name: branch.name } : null;

      if (this.currentBranch) {
        window.localStorage.setItem('currentBranch', JSON.stringify(this.currentBranch));
        window.localStorage.setItem('selected_branch_id', String(this.currentBranch.id));
      } else {
        window.localStorage.removeItem('currentBranch');
        window.localStorage.removeItem('selected_branch_id');
      }

      window.location.reload();
    },
  },
});

