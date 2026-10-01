<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useOnlineStatus } from '../composables/useOnlineStatus';

const auth = useAuthStore();
const router = useRouter();
const { online } = useOnlineStatus();

async function logout() {
  await auth.logout();
  router.push('/login');
}
</script>

<template>
  <header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 lg:px-8">
    <div>
      <p class="text-sm text-slate-500">Diretoria Municipal de Cultura</p>
      <h2 class="font-bold text-slate-900">Cultura em Cafarnaum</h2>
    </div>
    <div class="flex items-center gap-4">
      <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="online ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
        {{ online ? 'Online' : 'Offline' }}
      </span>
      <button @click="logout" class="text-sm font-medium text-slate-600 hover:text-slate-900">Sair</button>
    </div>
  </header>
</template>
