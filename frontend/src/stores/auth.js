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
    /**
     * Verifica se o usuário tem a permissão ou é super-admin.
     * @param {string} permissionName - Ex: 'products.view', 'users.create'
     * @returns {boolean}
     */
    can: (state) => (permissionName) => {
      if (!state.user) return false;
      if (state.user.is_super_admin === true) return true;
      const perms = state.user.permissions || [];
      return Array.isArray(perms) && perms.includes(permissionName);
    },
    /**
     * Verifica se o usuário tem uma role específica.
     * @param {string|string[]} roleName - Ex: 'super-admin', 'manager', ['manager', 'super-admin']
     * @returns {boolean}
     */
    hasRole: (state) => (roleName) => {
      if (!state.user) return false;
      const roles = state.user.roles || [];
      const roleNames = Array.isArray(roleName) ? roleName : [roleName];
      return roles.some((r) => {
        const name = typeof r === 'string' ? r : r?.name;
        return roleNames.includes(name);
      });
    },
    isSuperAdmin: (state) => {
      if (!state.user) return false;
      const roles = state.user.roles || [];
      return roles.some((r) => {
        const name = typeof r === 'string' ? r : r?.name;
        return name === 'super-admin';
      });
    },
    isManager: (state) => {
      if (!state.user) return false;
      const roles = state.user.roles || [];
      return roles.some((r) => {
        const name = typeof r === 'string' ? r : r?.name;
        return name === 'manager';
      });
    },
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

        const isAdmin = user?.roles?.some((r) => {
          if (typeof r === 'string') {
            return r === 'super-admin';
          }
          return r?.name === 'super-admin';
        });

        const appStore = useAppStore();
        if (!isAdmin && user?.branch) {
          appStore.currentBranch = {
            id: user.branch.id,
            name: user.branch.name,
          };
          window.localStorage.setItem('currentBranch', JSON.stringify(appStore.currentBranch));
          window.localStorage.setItem('selected_branch_id', String(user.branch.id));
        } else if (isAdmin) {
          await appStore.fetchBranches();
          if (appStore.branches?.length) {
            const first = appStore.branches[0];
            appStore.currentBranch = { id: first.id, name: first.name };
            window.localStorage.setItem('currentBranch', JSON.stringify(appStore.currentBranch));
            window.localStorage.setItem('selected_branch_id', String(first.id));
          }
        }
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors?.email?.[0] || error.response?.data?.message || 'Não foi possível fazer login.';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchMe() {
      if (!this.token) return;
      try {
        const { data } = await api.get('/me');
        const user = data?.user ?? data;
        if (user) {
          this.user = user;
          window.localStorage.setItem('user', JSON.stringify(user));
        }
      } catch {
        // token inválido ou expirado: não sobrescreve user
      }
    },
    logout() {
      this.token = null;
      this.user = null;
      this.error = null;

      window.localStorage.removeItem('token');
      window.localStorage.removeItem('user');
    },
    /**
     * Valida autorização do gerente: PIN + senha de operação (o gerente digita no PDV).
     * @param {{ pin: string, password: string }} payload - PIN e senha informados pelo gerente
     * @returns {Promise<{ valid: boolean, authorized_by_user_id?: number }>}
     */
    async validateOperationPassword(payload) {
      try {
        const { data } = await api.post('/validate-operation-password', {
          pin: payload?.pin ?? '',
          password: payload?.password ?? '',
        });
        return {
          valid: data?.valid === true,
          authorized_by_user_id: data?.authorized_by_user_id ?? null,
        };
      } catch {
        return { valid: false, authorized_by_user_id: null };
      }
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

