<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const login = ref('admin@cultura.local');
const password = ref('password');
const error = ref('');
const auth = useAuthStore();
const router = useRouter();

async function submit() {
  error.value = '';
  try {
    await auth.login(login.value, password.value);
    router.push('/');
  } catch (e) {
    error.value = e.response?.data?.message || 'Não foi possível entrar.';
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-4">
    <label class="block">
      <span class="text-sm font-medium text-slate-700">E-mail ou usuário</span>
      <input v-model="login" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-teal-600" />
    </label>
    <label class="block">
      <span class="text-sm font-medium text-slate-700">Senha</span>
      <input v-model="password" type="password" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-teal-600" />
    </label>
    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    <button class="w-full rounded-xl bg-teal-700 px-4 py-3 font-semibold text-white hover:bg-teal-800" :disabled="auth.loading">
      {{ auth.loading ? 'Entrando...' : 'Entrar' }}
    </button>
  </form>
</template>
