<script setup>
import Sidebar from '../components/Sidebar.vue';
import Topbar from '../components/Topbar.vue';
</script>

<template>
  <div class="min-h-screen bg-slate-50 lg:flex">
    <Sidebar />
    <div class="min-w-0 flex-1">
      <Topbar />
      <main class="p-5 lg:p-8"><router-view /></main>
    </div>
  </div>
</template>
