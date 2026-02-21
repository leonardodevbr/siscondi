<template>
  <div class="min-h-screen flex flex-col bg-slate-50">
    <!-- Header fixo no topo -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-blue-700 text-white shadow-md">
      <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-3 shrink-0">
          <img
            v-if="config?.municipality?.logo_url"
            :src="config.municipality.logo_url"
            alt="Brasão"
            class="h-14 w-14 object-contain bg-white rounded"
          />
          <div
            v-else
            class="h-14 w-14 rounded bg-white/20 flex items-center justify-center text-2xl font-bold"
          >
            {{ config?.municipality?.name?.charAt(0) || 'S' }}
          </div>
          <div>
            <h1 class="text-lg font-semibold leading-tight">
              Portal da Transparência
            </h1>
            <p class="text-sm text-blue-100">
              Diárias e passagens
            </p>
          </div>
        </div>
        <div class="ml-auto text-right">
          <p class="font-semibold text-white">
            {{ config?.municipality?.display_name || config?.municipality?.name || 'Município' }}
          </p>
          <p v-if="config?.municipality?.display_state" class="text-sm text-blue-100">
            {{ config.municipality.display_state }}
          </p>
        </div>
      </div>
    </header>

    <!-- Espaço para o header fixo -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-6 sm:px-6 pt-24">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="bg-slate-700 text-slate-300 text-sm py-4 mt-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-wrap items-center justify-between gap-2">
        <span>Data de atualização: {{ updateDate }}</span>
        <span>{{ config?.municipality?.display_name || config?.municipality?.name || '' }}</span>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, provide, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/services/api';

const route = useRoute();
const config = ref(null);

const slug = computed(() => route.params.slug ?? '');

const updateDate = computed(() => {
  const d = new Date();
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' - ' +
    d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
});

async function loadConfig() {
  const s = slug.value;
  if (!s) {
    config.value = { municipality: null, departments: [] };
    return;
  }
  try {
    const res = await api.get('/public/transparency/config', { params: { slug: s } });
    config.value = res.data;
  } catch (e) {
    config.value = { municipality: null, departments: [] };
  }
}

watch(slug, loadConfig, { immediate: true });

provide('transparencyConfig', config);
defineExpose({ config });
</script>
