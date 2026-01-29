import { defineStore } from 'pinia';
import api from '@/services/api';

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    settings: {},
    settingsMeta: {},
    publicConfig: {
      app_name: '',
      municipality: {},
    },
    loading: false,
  }),

  getters: {
    appName: (state) => state.publicConfig.app_name || '',
    municipality: (state) => state.publicConfig.municipality || {},
    getSetting: (state) => (key) => state.settings[key],
    getSettingMeta: (state) => (key) => state.settingsMeta[key] || null,
  },

  actions: {
    async fetchPublicConfig() {
      try {
        const response = await api.get('/config');
        const data = response.data || {};
        this.publicConfig = {
          app_name: data.app_name || '',
          municipality: data.municipality || {},
        };
        return this.publicConfig;
      } catch (error) {
        console.error('Erro ao carregar configurações públicas:', error);
        this.publicConfig = { app_name: '', municipality: {} };
        return this.publicConfig;
      }
    },

    async fetchSettings() {
      this.loading = true;
      try {
        const response = await api.get('/settings');
        const grouped = response.data || {};
        const flatSettings = {};
        const meta = {};
        Object.keys(grouped).forEach((group) => {
          (grouped[group] || []).forEach((setting) => {
            flatSettings[setting.key] = setting.value;
            meta[setting.key] = { masked: !!setting.masked };
          });
        });
        this.settings = flatSettings;
        this.settingsMeta = meta;
      } catch (error) {
        console.error('Erro ao carregar configurações:', error);
      } finally {
        this.loading = false;
      }
    },

    async updateSettings(settingsArray) {
      this.loading = true;
      try {
        await api.put('/settings', { settings: settingsArray });
        await this.fetchSettings();
      } catch (error) {
        console.error('Erro ao atualizar configurações:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});
