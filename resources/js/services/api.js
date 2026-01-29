import axios from 'axios';
import { useAppStore } from '@/stores/app';

const baseURL = '/api';

const api = axios.create({
  baseURL,
});

api.interceptors.request.use((config) => {
  const token = window.localStorage.getItem('token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  try {
    const appStore = useAppStore();
    if (appStore.currentDepartment?.id) {
      config.headers['X-Department-ID'] = appStore.currentDepartment.id;
    }
  } catch (e) {
    // Pinia pode ainda não estar inicializado
  }

  if (!config.headers['X-Department-ID']) {
    const storedId = window.localStorage.getItem('selected_department_id');
    if (storedId) {
      config.headers['X-Department-ID'] = storedId;
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
