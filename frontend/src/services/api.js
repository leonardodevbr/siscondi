import axios from 'axios';
import { useAppStore } from '@/stores/app';

// TEMPORÁRIO: URL de produção hardcoded. Altere aqui quando mudar o domínio.
const baseURL = (import.meta.env.VITE_API_URL || '').trim().replace(/\/$/, '') || 'https://api.pazmental.app.br/v1';

const api = axios.create({
  baseURL,
});

api.interceptors.request.use((config) => {
  const token = window.localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  // Header de filial via store (quando Pinia já estiver pronto)
  try {
    const appStore = useAppStore();
    if (appStore.currentBranch?.id) {
      config.headers['X-Branch-ID'] = appStore.currentBranch.id;
    }
  } catch (e) {
    // se o Pinia ainda não estiver inicializado, ignoramos aqui
  }

  // Fallback: usa o ID salvo no localStorage
  if (!config.headers['X-Branch-ID']) {
    const storedBranchId = window.localStorage.getItem('selected_branch_id');
    if (storedBranchId) {
      config.headers['X-Branch-ID'] = storedBranchId;
    }
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      window.localStorage.removeItem('token');
      window.localStorage.removeItem('user');

      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    return Promise.reject(error);
  },
);

export default api;

