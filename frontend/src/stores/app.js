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
        const { data } = await api.get('/branches');
        const items = data.data || data || [];
        this.branches = items;
        if (!this.currentBranch && items.length > 0) {
          this.setBranch(items[0]);
        }
      } catch (e) {
        console.error('Erro ao carregar filiais', e);
      } finally {
        this.loadingBranches = false;
      }
    },
    setBranch(branch) {
      this.currentBranch = branch ? { id: branch.id, name: branch.name } : null;
      if (this.currentBranch) {
        window.localStorage.setItem('currentBranch', JSON.stringify(this.currentBranch));
      } else {
        window.localStorage.removeItem('currentBranch');
      }
      window.location.reload();
    },
  },
});

