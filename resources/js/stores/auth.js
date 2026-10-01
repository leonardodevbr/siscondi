import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token'),
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    loading: false,
  }),
  getters: {
    isAuthenticated: state => Boolean(state.token),
  },
  actions: {
    async login(login, password) {
      this.loading = true;
      try {
        const { data } = await api.post('/login', { login, password });
        this.token = data.token;
        this.user = data.user;
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
      } finally {
        this.loading = false;
      }
    },
    async me() {
      if (!this.token) return;
      const { data } = await api.get('/me');
      this.user = data.user;
      localStorage.setItem('user', JSON.stringify(data.user));
    },
    async logout() {
      try { await api.post('/logout'); } catch {}
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    },
  },
});
