<script setup>
import { ref } from 'vue';
import PageHeader from '../components/PageHeader.vue';
import { enqueueOperation, flushSyncQueue } from '../offline/sync';
import { pendingCount } from '../offline/db';
import { useOnlineStatus } from '../composables/useOnlineStatus';

const { online } = useOnlineStatus();
const pending = ref(0);
const message = ref('');
const form = ref({ name: '', artistic_name: '', phone: '', community_name: '', notes: '', latitude: null, longitude: null });

async function refresh() { pending.value = await pendingCount(); }
async function locate() {
  navigator.geolocation?.getCurrentPosition(pos => {
    form.value.latitude = pos.coords.latitude;
    form.value.longitude = pos.coords.longitude;
  });
}
async function saveOffline() {
  await enqueueOperation({ resource: 'cultural-agent', action: 'create', payload: { ...form.value } });
  form.value = { name: '', artistic_name: '', phone: '', community_name: '', notes: '', latitude: null, longitude: null };
  message.value = 'Cadastro salvo no aparelho.';
  await refresh();
  if (online.value) {
    try { await flushSyncQueue(); message.value = 'Cadastro sincronizado.'; } catch {}
    await refresh();
  }
}
async function syncNow() {
  await flushSyncQueue();
  await refresh();
}
refresh();
</script>

<template>
  <PageHeader title="Busca ativa" subtitle="Formulário de campo preparado para trabalhar sem internet." />
  <div class="mb-4 flex flex-wrap items-center gap-3">
    <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="online ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
      {{ online ? 'Com internet' : 'Modo offline' }}
    </span>
    <span class="text-sm text-slate-500">{{ pending }} operação(ões) aguardando sincronização</span>
    <button v-if="online && pending" @click="syncNow" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Sincronizar agora</button>
  </div>
  <form @submit.prevent="saveOffline" class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="grid gap-4 md:grid-cols-2">
      <input v-model="form.name" required placeholder="Nome completo" class="rounded-xl border px-4 py-3" />
      <input v-model="form.artistic_name" placeholder="Nome artístico" class="rounded-xl border px-4 py-3" />
      <input v-model="form.phone" placeholder="WhatsApp" class="rounded-xl border px-4 py-3" />
      <input v-model="form.community_name" placeholder="Comunidade/localidade" class="rounded-xl border px-4 py-3" />
      <textarea v-model="form.notes" placeholder="Observações / trajetória cultural" class="min-h-28 rounded-xl border px-4 py-3 md:col-span-2"></textarea>
    </div>
    <div class="mt-4 flex flex-wrap gap-3">
      <button type="button" @click="locate" class="rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-700">Capturar localização</button>
      <button class="rounded-xl bg-teal-700 px-5 py-3 font-semibold text-white">Salvar cadastro</button>
    </div>
    <p v-if="message" class="mt-4 text-sm text-teal-700">{{ message }}</p>
  </form>
</template>
