<script setup>
import { onMounted, ref } from 'vue';
import api from '../services/api';
import PageHeader from '../components/PageHeader.vue';

const agents = ref([]);
const loading = ref(false);
const form = ref({ name: '', artistic_name: '', phone: '', community_name: '' });

async function load() {
  loading.value = true;
  try { agents.value = (await api.get('/cultural-agents')).data.data ?? []; } finally { loading.value = false; }
}
async function save() {
  await api.post('/cultural-agents', form.value);
  form.value = { name: '', artistic_name: '', phone: '', community_name: '' };
  await load();
}
onMounted(load);
</script>

<template>
  <PageHeader title="Agentes culturais" subtitle="Cadastro permanente de pessoas e fazedores de cultura." />
  <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
    <form @submit.prevent="save" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
      <h2 class="font-bold text-slate-900">Novo agente</h2>
      <input v-model="form.name" required placeholder="Nome completo" class="w-full rounded-xl border px-3 py-2.5" />
      <input v-model="form.artistic_name" placeholder="Nome artístico" class="w-full rounded-xl border px-3 py-2.5" />
      <input v-model="form.phone" placeholder="WhatsApp" class="w-full rounded-xl border px-3 py-2.5" />
      <input v-model="form.community_name" placeholder="Comunidade/localidade" class="w-full rounded-xl border px-3 py-2.5" />
      <button class="w-full rounded-xl bg-teal-700 px-4 py-3 font-semibold text-white">Cadastrar</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div v-if="loading" class="p-6 text-slate-500">Carregando...</div>
      <div v-else-if="!agents.length" class="p-6 text-slate-500">Nenhum agente cadastrado ainda.</div>
      <div v-for="agent in agents" :key="agent.id" class="border-b border-slate-100 p-4 last:border-0">
        <div class="font-semibold text-slate-900">{{ agent.artistic_name || agent.name }}</div>
        <div class="text-sm text-slate-500">{{ agent.name }} · {{ agent.community?.name || 'Sem comunidade' }}</div>
      </div>
    </div>
  </div>
</template>
