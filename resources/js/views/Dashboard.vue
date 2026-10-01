<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';
import PageHeader from '../components/PageHeader.vue';

const data = ref({ agents: 0, communities: 0, manifestations: 0, projects: 0, pending_sync: 0 });
onMounted(async () => {
  try { data.value = (await api.get('/dashboard')).data; } catch {}
});
</script>

<template>
  <PageHeader title="Visão geral" subtitle="Panorama rápido do mapeamento e das ações culturais." />
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <div v-for="card in [
      ['Agentes mapeados', data.agents, 'text-teal-700'],
      ['Comunidades', data.communities, 'text-blue-600'],
      ['Manifestações', data.manifestations, 'text-amber-600'],
      ['Projetos ativos', data.projects, 'text-rose-600'],
      ['Conflitos de sync', data.pending_sync, 'text-violet-600'],
    ]" :key="card[0]" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="text-3xl font-black" :class="card[2]">{{ card[1] }}</div>
      <div class="mt-1 text-sm text-slate-500">{{ card[0] }}</div>
    </div>
  </div>
  <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
    <router-link to="/mapeamento" class="rounded-3xl bg-slate-900 p-7 text-white shadow-sm">
      <p class="text-sm font-semibold text-teal-300">MAPA CULTURAL</p>
      <h2 class="mt-2 text-2xl font-bold">Visualizar agentes e manifestações no território</h2>
      <p class="mt-3 text-slate-300">Clusters, filtros por categoria e recorte por comunidade.</p>
    </router-link>
    <router-link to="/busca-ativa" class="rounded-3xl border border-teal-200 bg-teal-50 p-7 text-teal-950">
      <p class="text-sm font-semibold text-teal-700">CAMPO</p>
      <h2 class="mt-2 text-2xl font-bold">Busca ativa offline</h2>
      <p class="mt-3 text-teal-800/70">Cadastre mesmo sem sinal e sincronize depois.</p>
    </router-link>
  </div>
</template>
