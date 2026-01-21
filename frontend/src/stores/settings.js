import { defineStore } from 'pinia';
import api from '@/services/api';

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    settings: {},
    loading: false,
  }),
  
  getters: {
    skuAutoGeneration: (state) => state.settings['sku_auto_generation'] ?? false,
    skuPattern: (state) => state.settings['sku_pattern'] ?? '{CATEGORY}-{NAME}-{SEQ}',
    
    getSetting: (state) => (key) => {
      return state.settings[key];
    },
  },
  
  actions: {
    async fetchSettings() {
      this.loading = true;
      try {
        const response = await api.get('/settings');
        const grouped = response.data;
        
        const flatSettings = {};
        Object.keys(grouped).forEach((group) => {
          grouped[group].forEach((setting) => {
            flatSettings[setting.key] = setting.value;
          });
        });
        
        this.settings = flatSettings;
      } catch (error) {
        console.error('Erro ao carregar configurações:', error);
      } finally {
        this.loading = false;
      }
    },
    
    async updateSettings(settingsArray) {
      this.loading = true;
      try {
        await api.put('/settings', {
          settings: settingsArray,
        });
        
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
