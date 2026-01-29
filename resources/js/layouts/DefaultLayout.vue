<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import { useSettingsStore } from '@/stores/settings';
import Sidebar from '@/components/Layout/Sidebar.vue';
import Header from '@/components/Layout/Header.vue';

const auth = useAuthStore();
const router = useRouter();
const appStore = useAppStore();
const settingsStore = useSettingsStore();

const isSidebarOpen = ref(false);

const currentUserName = computed(() => auth.user?.name ?? 'Usuário');

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

onMounted(async () => {
  if (auth.token) {
    await auth.fetchMe();
  }
  if (auth.user) {
    try {
      await settingsStore.fetchPublicConfig();
      appStore.appName = settingsStore.publicConfig?.app_name || '';
      if (!appStore.appName) {
        const meta = document.querySelector('meta[name="apple-mobile-web-app-title"]');
        appStore.appName = meta?.getAttribute('content') || '';
      }
    } catch (error) {
      console.error('Erro ao carregar configurações públicas:', error);
    }
  }
});
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

      <main class="flex-1 overflow-hidden bg-gray-50">
        <div class="h-full p-4 md:p-6">
          <div class="card h-full p-4 md:p-6">
            <router-view />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

