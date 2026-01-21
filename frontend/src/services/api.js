import axios from 'axios';
import { useAppStore } from '@/stores/app';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
});
api.interceptors.request.use((config) => {
  const token = window.localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  try {
    const appStore = useAppStore();
    if (appStore.currentBranch?.id) {
      config.headers['X-Branch-ID'] = appStore.currentBranch.id;
    }
  } catch (e) {
    // se o Pinia ainda não estiver inicializado, ignoramos o header
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

