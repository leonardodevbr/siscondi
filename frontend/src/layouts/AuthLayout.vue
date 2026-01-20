<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-md">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-semibold text-slate-800">Adonai System</h1>
        <p class="mt-1 text-sm text-slate-500">Acesse o painel para gerenciar sua loja.</p>
      </div>

      <div class="card p-6">
        <router-view />
      </div>
    </div>
  </div>
</template>

