<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import { useToast } from 'vue-toastification';

const router = useRouter();
const toast = useToast();

const metrics = ref({
  sales_today: 0,
  sales_month: 0,
  profit_month: 0,
  net_profit_month: 0,
  total_sales_count_today: 0,
  low_stock_products: [],
  top_selling_products: [],
});

const loading = ref(true);

const averageTicket = computed(() => {
  if (metrics.value.total_sales_count_today === 0) return 0;
  return metrics.value.sales_today / metrics.value.total_sales_count_today;
});

const fetchMetrics = async () => {
  try {
    loading.value = true;
    const response = await api.get('/dashboard');
    metrics.value = response.data;
  } catch (error) {
    console.error('Erro ao carregar métricas:', error);
    toast.error('Erro ao carregar dados do painel');
  } finally {
    loading.value = false;
  }
};

const goToPos = () => router.push('/pos');
const goToProducts = () => router.push('/products');
const goToExpenses = () => router.push('/expenses');
const goToSales = () => router.push('/sales');
const goToInventory = () => router.push('/inventory/movements');

onMounted(() => {
  fetchMetrics();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Painel</h2>
        <p class="text-xs text-slate-500">
          Visão geral rápida das operações da loja.
        </p>
      </div>
      
      <!-- Ações Rápidas -->
      <div class="flex gap-2">
        <button
          @click="goToPos"
          class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors flex items-center gap-2"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          Abrir PDV
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Dashboard Content -->
    <div v-else class="space-y-6">
      <!-- 💰 FINANCEIRO -->
      <div>
        <div class="flex items-center gap-2 mb-3">
          <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h3 class="text-base font-semibold text-slate-800">Financeiro</h3>
        </div>
        
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Faturamento Hoje -->
          <div class="card p-5 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                  Hoje
                </p>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                  {{ formatCurrency(metrics.sales_today) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ metrics.total_sales_count_today }} {{ metrics.total_sales_count_today === 1 ? 'venda' : 'vendas' }}
                </p>
              </div>
              <div class="p-3 bg-blue-100 rounded-full">
                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Faturamento Mês -->
          <div class="card p-5 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                  Mês
                </p>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                  {{ formatCurrency(metrics.sales_month) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Faturamento total
                </p>
              </div>
              <div class="p-3 bg-green-100 rounded-full">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Lucro Bruto -->
          <div class="card p-5 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                  Lucro Bruto
                </p>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                  {{ formatCurrency(metrics.profit_month) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Sem despesas
                </p>
              </div>
              <div class="p-3 bg-purple-100 rounded-full">
                <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Lucro Líquido -->
          <div class="card p-5 border-l-4" :class="metrics.net_profit_month >= 0 ? 'border-emerald-500' : 'border-red-500'">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                  Lucro Líquido
                </p>
                <p class="mt-2 text-2xl font-bold" :class="metrics.net_profit_month >= 0 ? 'text-emerald-600' : 'text-red-600'">
                  {{ formatCurrency(metrics.net_profit_month) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  Com despesas
                </p>
              </div>
              <div class="p-3 rounded-full" :class="metrics.net_profit_month >= 0 ? 'bg-emerald-100' : 'bg-red-100'">
                <svg class="h-6 w-6" :class="metrics.net_profit_month >= 0 ? 'text-emerald-600' : 'text-red-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Grid de 2 Colunas: Vendas + Estoque -->
      <div class="grid gap-6 lg:grid-cols-2">
        <!-- 🛒 VENDAS -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="text-base font-semibold text-slate-800">Vendas</h3>
          </div>

          <div class="card p-5 space-y-4">
            <!-- Ticket Médio -->
            <div class="flex items-center justify-between py-3 border-b border-slate-100">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-100 rounded">
                  <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-slate-500">Ticket Médio Hoje</p>
                  <p class="text-lg font-semibold text-slate-900">{{ formatCurrency(averageTicket) }}</p>
                </div>
              </div>
            </div>

            <!-- Botão Ver Todas -->
            <button
              @click="goToSales"
              class="w-full py-2 px-4 border border-slate-300 rounded text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Ver Todas as Vendas
            </button>
          </div>
        </div>

        <!-- 📦 ESTOQUE - Produtos em Baixo Estoque -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <svg class="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <h3 class="text-base font-semibold text-slate-800">Estoque Baixo</h3>
            <span
              v-if="metrics.low_stock_products.length > 0"
              class="px-2 py-0.5 text-xs font-semibold text-red-700 bg-red-100 rounded-full"
            >
              {{ metrics.low_stock_products.length }}
            </span>
          </div>

          <div class="card p-5">
            <!-- Lista de Produtos em Baixo Estoque -->
            <div v-if="metrics.low_stock_products.length > 0" class="space-y-3">
              <div
                v-for="product in metrics.low_stock_products"
                :key="product.id"
                class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg"
              >
                <div class="flex-1">
                  <p class="text-sm font-medium text-slate-900">{{ product.name }}</p>
                  <p class="text-xs text-red-600 mt-1">
                    <span class="font-semibold">{{ product.stock_quantity }}</span> em estoque
                    (mínimo: {{ product.min_stock_quantity }})
                  </p>
                </div>
                <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>

              <button
                @click="goToInventory"
                class="w-full py-2 px-4 border border-slate-300 rounded text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors mt-3"
              >
                Ver Movimentações
              </button>
            </div>

            <!-- Sem Alertas -->
            <div v-else class="text-center py-8">
              <svg class="h-12 w-12 text-green-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-sm font-medium text-slate-700">Tudo certo!</p>
              <p class="text-xs text-slate-500 mt-1">Nenhum produto em baixo estoque</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 🏆 TOP 5 PRODUTOS MAIS VENDIDOS -->
      <div>
        <div class="flex items-center gap-2 mb-3">
          <svg class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
          </svg>
          <h3 class="text-base font-semibold text-slate-800">Top 5 Produtos do Mês</h3>
        </div>

        <div class="card p-5">
          <div v-if="metrics.top_selling_products.length > 0" class="space-y-2">
            <div
              v-for="(product, index) in metrics.top_selling_products"
              :key="product.id"
              class="flex items-center gap-4 p-3 rounded-lg hover:bg-slate-50 transition-colors"
            >
              <!-- Ranking Badge -->
              <div
                class="flex items-center justify-center w-8 h-8 rounded-full font-bold text-sm"
                :class="{
                  'bg-yellow-100 text-yellow-700': index === 0,
                  'bg-slate-200 text-slate-700': index === 1,
                  'bg-orange-100 text-orange-700': index === 2,
                  'bg-slate-100 text-slate-600': index > 2,
                }"
              >
                {{ index + 1 }}
              </div>

              <!-- Produto Info -->
              <div class="flex-1">
                <p class="text-sm font-medium text-slate-900">{{ product.name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ product.total_quantity }} {{ product.total_quantity === 1 ? 'unidade vendida' : 'unidades vendidas' }}
                </p>
              </div>

              <!-- Trophy Icon para o 1º lugar -->
              <svg
                v-if="index === 0"
                class="h-6 w-6 text-yellow-500"
                fill="currentColor"
                viewBox="0 0 24 24"
              >
                <path d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 00-.584.859 6.753 6.753 0 006.138 5.6 6.73 6.73 0 002.743 1.346A6.707 6.707 0 019.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 00-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 00.75-.75 2.25 2.25 0 00-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 01-1.113-3.173 6.73 6.73 0 002.743-1.347 6.753 6.753 0 006.139-5.6.75.75 0 00-.585-.858 47.077 47.077 0 00-3.07-.543V2.62a.75.75 0 00-.658-.744 49.22 49.22 0 00-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 00-.657.744zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 013.16 5.337a45.6 45.6 0 012.006-.343v.256zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 01-2.863 3.207 6.72 6.72 0 00.857-3.294z" />
              </svg>
            </div>
          </div>

          <!-- Sem Dados -->
          <div v-else class="text-center py-8">
            <svg class="h-12 w-12 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="text-sm text-slate-500">Nenhuma venda registrada este mês</p>
          </div>
        </div>
      </div>

      <!-- Ações Rápidas Adicionais -->
      <div class="grid gap-3 sm:grid-cols-3">
        <button
          @click="goToProducts"
          class="card p-4 text-left hover:shadow-md transition-shadow"
        >
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-100 rounded">
              <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-900">Gerenciar Produtos</p>
              <p class="text-xs text-slate-500">Ver catálogo completo</p>
            </div>
          </div>
        </button>

        <button
          @click="goToExpenses"
          class="card p-4 text-left hover:shadow-md transition-shadow"
        >
          <div class="flex items-center gap-3">
            <div class="p-2 bg-red-100 rounded">
              <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-900">Lançar Despesa</p>
              <p class="text-xs text-slate-500">Controle financeiro</p>
            </div>
          </div>
        </button>

        <button
          @click="goToInventory"
          class="card p-4 text-left hover:shadow-md transition-shadow"
        >
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-100 rounded">
              <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-900">Conferir Estoque</p>
              <p class="text-xs text-slate-500">Movimentações</p>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>

