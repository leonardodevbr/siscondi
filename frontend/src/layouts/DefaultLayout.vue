<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Sidebar from '@/components/Layout/Sidebar.vue';
import Header from '@/components/Layout/Header.vue';

const auth = useAuthStore();
const router = useRouter();

const isSidebarOpen = ref(false);

const currentUserName = computed(() => auth.user?.name ?? 'Usuário');

function handleLogout() {
  auth.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <Sidebar
      :is-open="isSidebarOpen"
      @close="isSidebarOpen = false"
    />

    <div class="flex flex-col flex-1 transition-all duration-300 md:ml-64">
      <Header
        :user-name="currentUserName"
        @toggleSidebar="isSidebarOpen = !isSidebarOpen"
        @logout="handleLogout"
      />

      <main class="flex-1 bg-gray-50">
        <div class="p-4 md:p-6">
          <div class="card p-4 md:p-6">
            <router-view />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

