import { defineStore } from 'pinia';
import api from '@/services/api';
import { useAppStore } from '@/stores/app';
import { disconnectEcho } from '@/echo';

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
      
      // Super-admin tem acesso total
      const roles = state.user.roles || [];
      const isSuperAdmin = roles.some((r) => {
        const name = typeof r === 'string' ? r : r?.name;
        return name === 'super-admin';
      });
      
      if (isSuperAdmin) return true;
      
      // Verifica se tem a permissão específica
      const permissions = state.user.permissions || [];
      return permissions.some((p) => {
        const pName = typeof p === 'string' ? p : p?.name;
        return pName === permissionName;
      });
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
        const { token, user, needs_primary_department } = response.data;

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

        const isOwner = user?.roles?.some((r) => {
          if (typeof r === 'string') {
            return r === 'owner';
          }
          return r?.name === 'owner';
        });

        const appStore = useAppStore();

        if (needs_primary_department) {
          await appStore.fetchDepartments();
          return { needsPrimaryDepartment: true };
        }

        if (isAdmin || isOwner || (user?.department_ids && user.department_ids.length > 1)) {
          await appStore.fetchDepartments();
        }

        if (!isAdmin && !isOwner && user?.department) {
          appStore.currentDepartment = {
            id: user.department.id,
            name: user.department.name,
          };
          window.localStorage.setItem('currentDepartment', JSON.stringify(appStore.currentDepartment));
          window.localStorage.setItem('selected_department_id', String(user.department.id));
        } else if (isAdmin || isOwner) {
          if (appStore.departments?.length) {
            const first = appStore.departments[0];
            appStore.currentDepartment = { id: first.id, name: first.name };
            window.localStorage.setItem('currentDepartment', JSON.stringify(appStore.currentDepartment));
            window.localStorage.setItem('selected_department_id', String(first.id));
          }
        }
        return { needsPrimaryDepartment: false };
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
      disconnectEcho();
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
    setDepartment(department) {
      const appStore = useAppStore();

      if (department && this.user) {
        this.user = {
          ...this.user,
          department,
          primary_department_id: department.id,
        };
        window.localStorage.setItem('user', JSON.stringify(this.user));
      }

      appStore.setDepartment(department);
    },
    async setPrimaryDepartment(departmentId) {
      const response = await api.post('/set-primary-department', { department_id: departmentId });
      const user = response.data?.user ?? response.data;
      if (user) {
        this.user = { ...user, primary_department_id: user.primary_department_id ?? departmentId };
        window.localStorage.setItem('user', JSON.stringify(this.user));
      }
      const appStore = useAppStore();
      if (user?.department) {
        appStore.currentDepartment = { id: user.department.id, name: user.department.name };
        window.localStorage.setItem('currentDepartment', JSON.stringify(appStore.currentDepartment));
        window.localStorage.setItem('selected_department_id', String(user.department.id));
      } else if (departmentId) {
        const dept = appStore.departments?.find((d) => Number(d.id) === Number(departmentId));
        if (dept) {
          appStore.currentDepartment = { id: dept.id, name: dept.name };
          window.localStorage.setItem('currentDepartment', JSON.stringify(appStore.currentDepartment));
          window.localStorage.setItem('selected_department_id', String(departmentId));
        }
      }
    },
  },
});

