<script setup>
import {
  HomeIcon, MapIcon, MagnifyingGlassIcon, IdentificationIcon,
  DocumentTextIcon, FolderIcon, ChartBarIcon, UsersIcon
} from '@heroicons/vue/24/outline';

const items = [
  ['Visão geral', '/', HomeIcon],
  ['Agentes', '/agentes', UsersIcon],
  ['Mapeamento', '/mapeamento', MapIcon],
  ['Busca ativa', '/busca-ativa', MagnifyingGlassIcon],
  ['Carteiras', '/carteiras', IdentificationIcon],
  ['PNAB', '/pnab', DocumentTextIcon],
  ['Projetos', '/projetos', FolderIcon],
  ['Relatórios', '/relatorios', ChartBarIcon],
];
</script>

<template>
  <aside class="w-72 shrink-0 bg-slate-950 text-white min-h-screen p-5 hidden lg:block">
    <div class="mb-8 px-3">
      <div class="text-2xl font-black">CULTURA</div>
      <div class="text-sm font-semibold tracking-wider text-teal-400">CAFARNAUM</div>
    </div>
    <nav class="space-y-1">
      <router-link v-for="[label,to,Icon] in items" :key="to" :to="to"
        class="flex items-center gap-3 rounded-xl px-3 py-3 text-slate-300 hover:bg-white/10 hover:text-white"
        active-class="!bg-teal-700 !text-white">
        <component :is="Icon" class="h-5 w-5" />
        <span>{{ label }}</span>
      </router-link>
    </nav>
  </aside>
</template>
