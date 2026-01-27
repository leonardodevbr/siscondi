import { defineStore } from 'pinia';
import api from '@/services/api';

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    settings: {},
    settingsMeta: {},
    publicConfig: {
      enable_global_stock_search: false,
      sku_auto_generation: true,
      sku_pattern: '{NAME}-{VARIANTS}-{SEQ}',
    },
    loading: false,
  }),
  
  getters: {
    skuAutoGeneration: (state) => state.publicConfig.sku_auto_generation ?? state.settings['sku_auto_generation'] ?? true,
    skuPattern: (state) => state.publicConfig.sku_pattern ?? state.settings['sku_pattern'] ?? '{NAME}-{VARIANTS}-{SEQ}',
    enableGlobalStockSearch: (state) => state.publicConfig.enable_global_stock_search ?? false,
    
    getSetting: (state) => (key) => {
      return state.settings[key];
    },
    getSettingMeta: (state) => (key) => {
      return state.settingsMeta[key] || null;
    },
    mercadopagoConnected: (state) => {
      return state.settingsMeta.mp_access_token?.masked === true;
    },
    /**
     * Gateway ativo para pagamento com cartão (maquininha).
     * 'mercadopago_point' = loja tem MP Point configurado; flow abre seleção de device.
     * 'manual' = apenas registro manual de cartão crédito/débito.
     * Usa publicConfig (vindo de /config no PDV) ou, na tela de Settings, settings/settingsMeta.
     */
    activePaymentGateway: (state) => {
      const fromConfig = state.publicConfig.active_payment_gateway;
      if (fromConfig === 'mercadopago_point' || fromConfig === 'manual') return fromConfig;
      if (state.settingsMeta.mp_access_token?.masked === true) return 'mercadopago_point';
      const cid = state.settings['mp_client_id'];
      if (typeof cid === 'string' && cid.trim() !== '') return 'mercadopago_point';
      return 'manual';
    },
  },
  
  actions: {
    async fetchPublicConfig() {
      try {
        const response = await api.get('/config');
        this.publicConfig = {
          enable_global_stock_search: response.data.enable_global_stock_search ?? false,
          sku_auto_generation: response.data.sku_auto_generation ?? true,
          sku_pattern: response.data.sku_pattern ?? '{NAME}-{VARIANTS}-{SEQ}',
          active_payment_gateway: response.data.active_payment_gateway ?? 'manual',
          print_pix_receipt: response.data.print_pix_receipt ?? true,
        };
      } catch (error) {
        console.error('Erro ao carregar configurações públicas:', error);
        this.publicConfig = {
          enable_global_stock_search: false,
          sku_auto_generation: true,
          sku_pattern: '{NAME}-{VARIANTS}-{SEQ}',
          active_payment_gateway: 'manual',
          print_pix_receipt: true,
        };
      }
    },

    async fetchSettings() {
      this.loading = true;
      try {
        const response = await api.get('/settings');
        const grouped = response.data;
        
        const flatSettings = {};
        const meta = {};
        Object.keys(grouped).forEach((group) => {
          grouped[group].forEach((setting) => {
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

    async mercadopagoConnect(payload) {
      const { data } = await api.post('/settings/mercadopago/connect', payload);
      await this.fetchSettings();
      return data;
    },
  },
});
