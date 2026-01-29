<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useSettingsStore } from '@/stores/settings';
import Sidebar from '@/components/Layout/Sidebar.vue';
import Header from '@/components/Layout/Header.vue';

const router = useRouter();
const auth = useAuthStore();
const cashRegisterStore = useCashRegisterStore();
const settingsStore = useSettingsStore();

const isSidebarOpen = ref(false);

const currentUserName = computed(() => auth.user?.name ?? 'Usuário');

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

onMounted(async () => {
  if (auth.user) {
    try {
      await settingsStore.fetchPublicConfig();
    } catch (error) {
      console.error('Erro ao carregar configurações públicas:', error);
    }
  }
  await cashRegisterStore.checkStatus();
});
</script>

<template>
  <div class="h-screen flex flex-col bg-slate-100">
    <template v-if="!cashRegisterStore.isOpen">
      <div class="min-h-screen bg-gray-50 flex">
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
              <div class="h-full">
                <router-view />
              </div>
            </div>
          </main>
        </div>
      </div>
    </template>

    <template v-else>
      <router-view />
    </template>
  </div>
</template>
