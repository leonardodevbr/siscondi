<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-slate-800">Configurações</h1>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <p class="text-slate-500">Carregando configurações...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Tabs -->
      <div class="border-b border-slate-200 overflow-x-auto">
        <nav class="flex gap-6 px-6 min-w-max">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap outline-none focus:outline-none focus:ring-0',
              activeTab === tab.id
                ? 'text-blue-600 border-b-2 border-blue-600 py-4 font-semibold'
                : 'text-slate-500 hover:text-slate-700 py-4 font-medium transition-colors',
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="p-6 sm:p-8 space-y-6">
        <!-- Geral -->
        <template v-if="activeTab === 'general'">
          <div class="border border-slate-200 rounded-lg p-6 mb-6">
            <h2 class="text-base font-semibold text-slate-800 mb-4">
              Dados da Loja
            </h2>
            <p class="text-sm text-slate-500">
              Configurações gerais da loja serão configuradas aqui (nome, logo, dados da empresa).
            </p>
          </div>
        </template>

        <!-- Produtos & Estoque -->
        <template v-else-if="activeTab === 'products'">
          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="mb-2">
              <h2 class="text-base font-semibold text-slate-800">
                Produtos & Estoque
              </h2>
              <p class="text-sm text-slate-600 mt-1">
                Configure a geração automática de SKUs para produtos e variações.
              </p>
            </div>

            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-slate-700">
                  Gerar SKUs Automaticamente
                </label>
                <Toggle
                  v-model="form.skuAutoGeneration"
                  label=""
                />
              </div>
              <p class="text-xs text-slate-500">
                Quando ativado, os SKUs serão gerados automaticamente ao criar produtos/variações.
              </p>
            </div>

            <div v-if="form.skuAutoGeneration" class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Padrão do SKU
                </label>
                <input
                  v-model="form.skuPattern"
                  type="text"
                  class="input-base"
                  placeholder="{CATEGORY}-{NAME}-{SEQ}"
                />
              </div>
              <div class="mt-1 p-3 bg-slate-50 rounded-lg border border-slate-200">
                <p class="text-xs font-medium text-slate-700 mb-2">Tags disponíveis:</p>
                <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">
                  <div>
                    <code class="bg-white px-1 py-0.5 rounded text-blue-600">{CATEGORY}</code>
                    <span class="ml-2">3 primeiras letras da categoria</span>
                  </div>
                  <div>
                    <code class="bg-white px-1 py-0.5 rounded text-blue-600">{NAME}</code>
                    <span class="ml-2">3 primeiras letras do nome</span>
                  </div>
                  <div>
                    <code class="bg-white px-1 py-0.5 rounded text-blue-600">{VARIANTS}</code>
                    <span class="ml-2">Iniciais dos atributos (ex: G-B)</span>
                  </div>
                  <div>
                    <code class="bg-white px-1 py-0.5 rounded text-blue-600">{SEQ}</code>
                    <span class="ml-2">Número sequencial</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Vendas & PDV -->
        <template v-else-if="activeTab === 'sales'">
          <div class="border border-slate-200 rounded-lg p-6 mb-6">
            <h2 class="text-base font-semibold text-slate-800 mb-2">
              Vendas & PDV
            </h2>
            <p class="text-sm text-slate-600">
              Configurações de recibo, abertura de caixa e fluxo do PDV serão configuradas aqui.
            </p>
          </div>
        </template>

        <!-- Financeiro -->
        <template v-else-if="activeTab === 'finance'">
          <div class="border border-slate-200 rounded-lg p-6 mb-6">
            <h2 class="text-base font-semibold text-slate-800 mb-2">
              Financeiro
            </h2>
            <p class="text-sm text-slate-600">
              Configurações financeiras (integrações bancárias, centros de custo, etc.) serão configuradas aqui.
            </p>
          </div>
        </template>

        <div class="mt-2 flex justify-end">
          <button
            @click="handleSave"
            :disabled="saving"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="saving">Salvando...</span>
            <span v-else>Salvar Configurações</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useSettingsStore } from '@/stores/settings';
import Toggle from '@/components/Common/Toggle.vue';

export default {
  name: 'SettingsIndex',
  components: {
    Toggle,
  },
  setup() {
    const toast = useToast();
    const settingsStore = useSettingsStore();
    const loading = ref(false);
    const saving = ref(false);

    const activeTab = ref('products');

    const tabs = [
      { id: 'general', label: 'Geral' },
      { id: 'products', label: 'Produtos & Estoque' },
      { id: 'sales', label: 'Vendas & PDV' },
      { id: 'finance', label: 'Financeiro' },
    ];

    const form = ref({
      skuAutoGeneration: false,
      skuPattern: '{CATEGORY}-{NAME}-{SEQ}',
    });

    const loadSettings = async () => {
      loading.value = true;
      try {
        await settingsStore.fetchSettings();
        form.value.skuAutoGeneration = settingsStore.skuAutoGeneration;
        form.value.skuPattern = settingsStore.skuPattern;
      } catch (error) {
        toast.error('Erro ao carregar configurações');
      } finally {
        loading.value = false;
      }
    };

    const handleSave = async () => {
      saving.value = true;
      try {
        const settingsArray = [
          {
            key: 'sku_auto_generation',
            value: form.value.skuAutoGeneration,
            type: 'boolean',
            group: 'products',
          },
          {
            key: 'sku_pattern',
            value: form.value.skuPattern,
            type: 'string',
            group: 'products',
          },
        ];

        await settingsStore.updateSettings(settingsArray);
        toast.success('Configurações salvas com sucesso!');
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar configurações';
        toast.error(message);
      } finally {
        saving.value = false;
      }
    };

    onMounted(() => {
      loadSettings();
    });

    return {
      loading,
      saving,
      activeTab,
      tabs,
      form,
      handleSave,
    };
  },
};
</script>
