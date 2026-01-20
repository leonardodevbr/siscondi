import { defineStore } from 'pinia';
import api from '@/services/api';

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
        this.error = error.response?.data?.message || 'Não foi possível fazer login.';
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
  },
});

