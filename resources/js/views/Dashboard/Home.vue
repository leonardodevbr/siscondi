<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();
const appStore = useAppStore();

const stats = ref({
  total_servants: 0,
  total_legislations: 0,
  total_requests: 0,
  requests_by_status: {},
  financial: {},
  recent_requests: []
});

const loading = ref(true);

const fetchStats = async () => {
  try {
    loading.value = true;
    const response = await api.get('/dashboard');
    stats.value = response.data;
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error);
    toast.error('Erro ao carregar dados do painel');
  } finally {
    loading.value = false;
  }
};

const goToNewRequest = () => router.push('/daily-requests/create');
const goToRequests = () => router.push('/daily-requests');
const goToServants = () => router.push('/servants');
const goToLegislations = () => router.push('/legislations');

onMounted(() => {
  fetchStats();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Dashboard</h2>
        <p class="text-xs text-slate-500">{{ appStore.appName || 'Sistema de Concessão de Diárias' }}</p>
      </div>
      
      <button
        @click="goToNewRequest"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors"
      >
        Nova Solicitação
      </button>
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
      <!-- Cards de Estatísticas -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card p-5 border-l-4 border-blue-500">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Servidores Ativos</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ stats.total_servants }}</p>
        </div>

        <div class="card p-5 border-l-4 border-green-500">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Legislações Ativas</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ stats.total_legislations }}</p>
        </div>

        <div class="card p-5 border-l-4 border-purple-500">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total de Solicitações</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ stats.total_requests }}</p>
        </div>
      </div>

      <!-- Solicitações por Status -->
      <div class="card p-5">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Solicitações por Status</h3>
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div class="text-center p-3 bg-gray-50 rounded">
            <p class="text-2xl font-bold text-gray-600">{{ stats.requests_by_status?.draft || 0 }}</p>
            <p class="text-xs text-gray-600 mt-1">Rascunho</p>
          </div>
          <div class="text-center p-3 bg-blue-50 rounded">
            <p class="text-2xl font-bold text-blue-600">{{ stats.requests_by_status?.requested || 0 }}</p>
            <p class="text-xs text-blue-600 mt-1">Solicitado</p>
          </div>
          <div class="text-center p-3 bg-yellow-50 rounded">
            <p class="text-2xl font-bold text-yellow-600">{{ stats.requests_by_status?.validated || 0 }}</p>
            <p class="text-xs text-yellow-600 mt-1">Validado</p>
          </div>
          <div class="text-center p-3 bg-green-50 rounded">
            <p class="text-2xl font-bold text-green-600">{{ stats.requests_by_status?.authorized || 0 }}</p>
            <p class="text-xs text-green-600 mt-1">Concedido</p>
          </div>
          <div class="text-center p-3 bg-purple-50 rounded">
            <p class="text-2xl font-bold text-purple-600">{{ stats.requests_by_status?.paid || 0 }}</p>
            <p class="text-xs text-purple-600 mt-1">Pago</p>
          </div>
          <div class="text-center p-3 bg-red-50 rounded">
            <p class="text-2xl font-bold text-red-600">{{ stats.requests_by_status?.cancelled || 0 }}</p>
            <p class="text-xs text-red-600 mt-1">Cancelado</p>
          </div>
        </div>
      </div>

      <!-- Resumo Financeiro -->
      <div class="card p-5">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Resumo Financeiro</h3>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="p-4 bg-green-50 rounded">
            <p class="text-xs text-green-600">Total Autorizado</p>
            <p class="text-xl font-bold text-green-700 mt-1">
              {{ formatCurrency(stats.financial?.total_authorized || 0) }}
            </p>
          </div>
          <div class="p-4 bg-purple-50 rounded">
            <p class="text-xs text-purple-600">Total Pago</p>
            <p class="text-xl font-bold text-purple-700 mt-1">
              {{ formatCurrency(stats.financial?.total_paid || 0) }}
            </p>
          </div>
          <div class="p-4 bg-yellow-50 rounded">
            <p class="text-xs text-yellow-600">Pendente de Pagamento</p>
            <p class="text-xl font-bold text-yellow-700 mt-1">
              R$ {{ formatCurrency(stats.financial?.pending_payment || 0) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Solicitações Recentes -->
      <div class="card p-5">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-base font-semibold text-slate-800">Solicitações Recentes</h3>
          <button @click="goToRequests" class="text-sm text-blue-600 hover:text-blue-800">Ver todas</button>
        </div>
        <div v-if="stats.recent_requests?.length > 0" class="space-y-2">
          <div v-for="req in stats.recent_requests" :key="req.id" class="flex justify-between items-center p-3 bg-slate-50 rounded">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ req.servant_name }}</p>
              <p class="text-xs text-slate-500">{{ req.destination }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-slate-900">{{ formatCurrency(req.total_value) }}</p>
              <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-800">{{ req.status_label }}</span>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-slate-500">Nenhuma solicitação recente</div>
      </div>
    </div>
  </div>
</template>

