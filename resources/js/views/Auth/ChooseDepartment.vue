<template>
  <div class="space-y-6">
    <div class="space-y-1">
      <h2 class="text-lg font-semibold text-slate-800">Escolher secretaria</h2>
      <p class="text-sm text-slate-500">
        Você tem acesso a mais de uma secretaria. Escolha em qual deseja atuar nesta sessão.
      </p>
    </div>

    <div v-if="loading" class="text-center py-8 text-slate-500">Carregando...</div>
    <div v-else class="space-y-3">
      <button
        v-for="dept in departments"
        :key="dept.id"
        type="button"
        class="w-full flex items-center justify-between rounded-lg border-2 px-4 py-3 text-left transition-colors"
        :class="selectedId === dept.id
          ? 'border-blue-600 bg-blue-50 text-slate-900'
          : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
        @click="selectedId = dept.id"
      >
        <span class="font-medium">{{ dept.name }}</span>
        <span v-if="selectedId === dept.id" class="text-blue-600 text-sm">Selecionada</span>
      </button>
    </div>

    <div class="pt-4">
      <Button
        type="button"
        :disabled="!selectedId || submitting"
        class="w-full justify-center"
        @click="handleConfirm"
      >
        {{ submitting ? 'Continuando...' : 'Continuar' }}
      </Button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import Button from '@/components/Common/Button.vue';

const router = useRouter();
const authStore = useAuthStore();
const appStore = useAppStore();

const loading = ref(true);
const submitting = ref(false);
const selectedId = ref(null);

const departments = computed(() => authStore.user?.departments ?? []);

async function load() {
  loading.value = true;
  try {
    await appStore.fetchDepartments();
    const depts = authStore.user?.departments ?? [];
    if (depts.length === 1) {
      selectedId.value = depts[0].id;
    } else if (depts.length > 0 && !selectedId.value) {
      selectedId.value = depts[0].id;
    }
  } finally {
    loading.value = false;
  }
}

async function handleConfirm() {
  if (!selectedId.value) return;
  submitting.value = true;
  try {
    await authStore.setPrimaryDepartment(selectedId.value);
    router.push({ name: 'dashboard' });
  } catch (error) {
    console.error(error);
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (!authStore.user?.needs_primary_department && authStore.user?.primary_department_id) {
    router.replace({ name: 'dashboard' });
    return;
  }
  load();
});
</script>
