import { defineStore } from 'pinia';
import api from '@/services/api';
import { useAppStore } from '@/stores/app';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(window.localStorage.getItem('user') || 'null'),
    token: window.localStorage.getItem('token') || null,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    async login(email, password) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/login', { email, password });
        const { token, user } = response.data;

        this.token = token;
        this.user = user;

        window.localStorage.setItem('token', token);
        window.localStorage.setItem('user', JSON.stringify(user));
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors?.email?.[0] || error.response?.data?.message || 'Não foi possível fazer login.';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    logout() {
      this.token = null;
      this.user = null;
      this.error = null;

      window.localStorage.removeItem('token');
      window.localStorage.removeItem('user');
    },
    setBranch(branch) {
      const appStore = useAppStore();

      if (branch) {
        // atualiza a filial no usuário em memória/localStorage
        if (this.user) {
          this.user = {
            ...this.user,
            branch,
          };
          window.localStorage.setItem('user', JSON.stringify(this.user));
        }
      }

      appStore.setBranch(branch);
    },
  },
});

