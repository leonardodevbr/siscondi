<script setup>
import AppLogo from '@/components/Common/AppLogo.vue';
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-md">
      <div class="mb-6 flex flex-col items-center">
        <AppLogo icon-class="h-9 w-9" text-class="text-xl" />
        <p class="mt-2 text-sm text-slate-500">Acesse o painel para gerenciar sua loja.</p>
      </div>

      <div class="card p-6">
        <router-view />
      </div>
    </div>
  </div>
</template>

