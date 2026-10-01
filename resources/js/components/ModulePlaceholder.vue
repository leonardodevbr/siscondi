<script setup>
import PageHeader from './PageHeader.vue';
defineProps({ title: String, subtitle: String, items: { type: Array, default: () => [] } });
</script>
<template>
  <PageHeader :title="title" :subtitle="subtitle" />
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <div v-for="item in items" :key="item.title" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 class="font-bold text-slate-900">{{ item.title }}</h3>
      <p class="mt-2 text-sm leading-6 text-slate-500">{{ item.description }}</p>
    </div>
  </div>
</template>
