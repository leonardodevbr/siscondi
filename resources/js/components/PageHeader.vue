<script setup>
defineProps({ title: String, subtitle: String });
</script>
<template>
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900 lg:text-3xl">{{ title }}</h1>
    <p v-if="subtitle" class="mt-1 text-slate-500">{{ subtitle }}</p>
  </div>
</template>
