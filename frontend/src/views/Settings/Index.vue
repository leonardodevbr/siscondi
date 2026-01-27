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

          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="mb-2">
              <h2 class="text-base font-semibold text-slate-800">
                Estoque e Filiais
              </h2>
              <p class="text-sm text-slate-600 mt-1">
                Configure permissões de consulta de estoque entre filiais.
              </p>
            </div>

            <div>
              <div class="flex items-center justify-between mb-2">
                <div>
                  <label class="text-sm font-medium text-slate-700">
                    Permitir consulta de estoque global
                  </label>
                  <p class="text-xs text-slate-500 mt-1">
                    Se ativado, vendedores poderão consultar a disponibilidade de produtos em outras filiais.
                  </p>
                </div>
                <Toggle
                  v-model="form.enableGlobalStockSearch"
                  label=""
                />
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

        <!-- Integrações de Pagamento -->
        <template v-else-if="activeTab === 'integrations'">
          <!-- Seleção de Gateway -->
          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="mb-2">
              <h2 class="text-base font-semibold text-slate-800">
                Gateway de Pagamento Ativo
              </h2>
              <p class="text-sm text-slate-600 mt-1">
                Selecione qual gateway será utilizado para processar pagamentos PIX e cartões no PDV.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">
                Gateway Ativo
              </label>
              <select
                v-model="form.paymentGateway"
                class="input-base w-full max-w-md"
              >
                <option value="mercadopago">Mercado Pago (PIX Online + Point)</option>
                <option value="pagbank">PagBank (PIX Online)</option>
              </select>
              <p class="mt-1 text-xs text-slate-500">
                Configure as credenciais do gateway selecionado abaixo.
              </p>
            </div>
          </div>

          <!-- Mercado Pago -->
          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="flex items-center justify-between mb-2">
              <div>
                <h2 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                  Mercado Pago
                  <span
                    v-if="form.paymentGateway === 'mercadopago'"
                    class="text-xs font-medium px-2 py-0.5 bg-blue-100 text-blue-700 rounded"
                  >
                    Ativo
                  </span>
                </h2>
                <p class="text-sm text-slate-600 mt-1">
                  Credenciais por cliente, salvas no banco. Informe Client ID e Client Secret para o sistema gerar e renovar o token automaticamente.
                </p>
              </div>
              <span
                class="inline-flex items-center gap-1.5 text-sm font-medium"
                :class="mercadopagoConnected ? 'text-emerald-600' : 'text-slate-500'"
              >
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :class="mercadopagoConnected ? 'bg-emerald-500' : 'bg-red-500'"
                />
                {{ mercadopagoConnected ? 'Conectado' : 'Desconectado' }}
              </span>
            </div>
            <form autocomplete="off" class="space-y-4" @submit.prevent>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Client ID (Produção)
                </label>
                <input
                  v-model="form.mpClientId"
                  type="text"
                  name="mp_client_id"
                  autocomplete="off"
                  class="input-base w-full max-w-md"
                  placeholder="Ex.: 12345678901234567890123456789012"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Client Secret (Produção)
                </label>
                <input
                  v-model="form.mpClientSecret"
                  type="password"
                  name="mp_client_secret"
                  autocomplete="new-password"
                  class="input-base w-full max-w-md"
                  :placeholder="form.mpClientId ? '' : 'Obtenha em Suas integrações no painel do Mercado Pago'"
                />
                <p class="mt-1 text-xs text-slate-500">
                  Em <strong>Suas integrações</strong> &gt; sua aplicação &gt; Credenciais de produção. O sistema gera e renova o Access Token automaticamente.
                </p>
              </div>
              <div>
                <button
                  type="button"
                  :disabled="connecting || !form.mpClientId || !form.mpClientSecret"
                  @click="handleMercadopagoConnect"
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-slate-300 disabled:cursor-not-allowed transition-colors"
                >
                  <span v-if="connecting">Conectando ao Mercado Pago...</span>
                  <span v-else>Conectar / Gerar Token</span>
                </button>
              </div>
            </form>
          </div>

          <!-- PagBank -->
          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="mb-2">
              <h2 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                PagBank (PagSeguro)
                <span
                  v-if="form.paymentGateway === 'pagbank'"
                  class="text-xs font-medium px-2 py-0.5 bg-blue-100 text-blue-700 rounded"
                >
                  Ativo
                </span>
              </h2>
              <p class="text-sm text-slate-600 mt-1">
                Configure o token de acesso do PagBank para processar pagamentos PIX.
              </p>
            </div>
            <form autocomplete="off" class="space-y-4" @submit.prevent>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Token de Acesso
                </label>
                <input
                  v-model="form.pagbankToken"
                  type="password"
                  name="pagbank_token"
                  autocomplete="new-password"
                  class="input-base w-full max-w-md"
                  placeholder="Cole seu token do PagBank aqui"
                />
                <p class="mt-1 text-xs text-slate-500">
                  Obtenha em <strong>Integrações</strong> &gt; <strong>Tokens</strong> no painel do PagBank.
                </p>
              </div>
              <div>
                <div class="flex items-center justify-between mb-2">
                  <label class="text-sm font-medium text-slate-700">
                    Modo Sandbox (Teste)
                  </label>
                  <Toggle
                    v-model="form.pagbankSandbox"
                    label=""
                  />
                </div>
                <p class="text-xs text-slate-500">
                  Ative para usar o ambiente de testes do PagBank.
                </p>
              </div>
            </form>
          </div>

          <!-- Taxas de Parcelamento -->
          <div class="border border-slate-200 rounded-lg p-6 mb-6 space-y-6">
            <div class="mb-2">
              <h2 class="text-base font-semibold text-slate-800">
                Taxas de Parcelamento
              </h2>
              <p class="text-sm text-slate-600 mt-1">
                Configure as taxas e limites para parcelamento de cartão de crédito.
              </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Máximo de Parcelas
                </label>
                <input
                  v-model.number="form.ccMaxInstallments"
                  type="number"
                  min="1"
                  max="12"
                  class="input-base w-full"
                  placeholder="12"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Parcelas Sem Juros
                </label>
                <input
                  v-model.number="form.ccNoInterestInstallments"
                  type="number"
                  min="1"
                  max="12"
                  class="input-base w-full"
                  placeholder="3"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Taxa de Juros (%)
                </label>
                <input
                  v-model.number="form.ccInterestRate"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-base w-full"
                  placeholder="2.99"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                  Valor Mínimo da Parcela (R$)
                </label>
                <input
                  v-model.number="form.ccMinInstallmentValue"
                  type="number"
                  step="0.01"
                  min="1"
                  class="input-base w-full"
                  placeholder="10.00"
                />
              </div>
            </div>
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
import { ref, computed, onMounted } from 'vue';
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
      { id: 'integrations', label: 'Integrações de Pagamento' },
    ];

    const form = ref({
      skuAutoGeneration: false,
      skuPattern: '{CATEGORY}-{NAME}-{SEQ}',
      enableGlobalStockSearch: false,
      paymentGateway: 'mercadopago',
      mpClientId: '',
      mpClientSecret: '',
      pagbankToken: '',
      pagbankSandbox: false,
      ccMaxInstallments: 12,
      ccNoInterestInstallments: 3,
      ccInterestRate: 2.99,
      ccMinInstallmentValue: 10.0,
    });
    const connecting = ref(false);
    const mercadopagoConnected = computed(() => settingsStore.mercadopagoConnected);

    const loadSettings = async () => {
      loading.value = true;
      try {
        await settingsStore.fetchSettings();
        form.value.skuAutoGeneration = settingsStore.skuAutoGeneration;
        form.value.skuPattern = settingsStore.skuPattern;
        const stockSearchSetting = settingsStore.getSetting('enable_global_stock_search');
        form.value.enableGlobalStockSearch = stockSearchSetting !== undefined ? stockSearchSetting : settingsStore.enableGlobalStockSearch;
        
        // Gateway e credenciais
        form.value.paymentGateway = settingsStore.getSetting('payment_gateway') ?? 'mercadopago';
        form.value.mpClientId = settingsStore.getSetting('mp_client_id') ?? '';
        form.value.mpClientSecret = '';
        form.value.pagbankToken = '';
        form.value.pagbankSandbox = settingsStore.getSetting('pagbank_sandbox') ?? false;
        
        // Taxas de parcelamento
        form.value.ccMaxInstallments = settingsStore.getSetting('cc_max_installments') ?? 12;
        form.value.ccNoInterestInstallments = settingsStore.getSetting('cc_no_interest_installments') ?? 3;
        form.value.ccInterestRate = settingsStore.getSetting('cc_interest_rate') ?? 2.99;
        form.value.ccMinInstallmentValue = settingsStore.getSetting('cc_min_installment_value') ?? 10.0;
      } catch (error) {
        toast.error('Erro ao carregar configurações');
      } finally {
        loading.value = false;
      }
    };

    const handleMercadopagoConnect = async () => {
      if (!form.value.mpClientId?.trim() || !form.value.mpClientSecret?.trim()) {
        toast.error('Preencha Client ID e Client Secret.');
        return;
      }
      connecting.value = true;
      try {
        await settingsStore.mercadopagoConnect({
          mp_client_id: form.value.mpClientId.trim(),
          mp_client_secret: form.value.mpClientSecret.trim(),
        });
        toast.success('Conectado ao Mercado Pago. Token gerado com sucesso.');
      } catch (error) {
        const msg = error.response?.data?.message || 'Credenciais inválidas. Verifique o Client ID e o Client Secret.';
        toast.error(msg);
      } finally {
        connecting.value = false;
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
          {
            key: 'enable_global_stock_search',
            value: form.value.enableGlobalStockSearch,
            type: 'boolean',
            group: 'products',
          },
          {
            key: 'payment_gateway',
            value: form.value.paymentGateway,
            type: 'string',
            group: 'payments',
          },
          {
            key: 'pagbank_sandbox',
            value: form.value.pagbankSandbox,
            type: 'boolean',
            group: 'payments',
          },
          {
            key: 'cc_max_installments',
            value: form.value.ccMaxInstallments,
            type: 'integer',
            group: 'payments',
          },
          {
            key: 'cc_no_interest_installments',
            value: form.value.ccNoInterestInstallments,
            type: 'integer',
            group: 'payments',
          },
          {
            key: 'cc_interest_rate',
            value: form.value.ccInterestRate,
            type: 'string',
            group: 'payments',
          },
          {
            key: 'cc_min_installment_value',
            value: form.value.ccMinInstallmentValue,
            type: 'string',
            group: 'payments',
          },
        ];

        // Adiciona token do PagBank se preenchido
        if (form.value.pagbankToken && form.value.pagbankToken.trim() !== '') {
          settingsArray.push({
            key: 'pagbank_token',
            value: form.value.pagbankToken.trim(),
            type: 'string',
            group: 'payments',
          });
        }

        await settingsStore.updateSettings(settingsArray);
        await settingsStore.fetchPublicConfig();
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
      connecting,
      mercadopagoConnected,
      handleSave,
      handleMercadopagoConnect,
    };
  },
};
</script>
