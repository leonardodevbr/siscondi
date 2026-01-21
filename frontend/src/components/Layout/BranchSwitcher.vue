<script setup>
import { computed, onMounted } from 'vue';
import { MapPinIcon } from '@heroicons/vue/24/outline';
import { useAppStore } from '@/stores/app';
import { useAuthStore } from '@/stores/auth';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';

const appStore = useAppStore();
const auth = useAuthStore();

const isSuperAdmin = computed(() => auth.user?.roles?.some((r) => r.name === 'super-admin'));

const branchOptions = computed(() =>
  (appStore.branches || []).map((b) => ({ id: b.id, name: b.name })),
);

const selectedBranchId = computed({
  get() {
    return appStore.currentBranch?.id ?? null;
  },
  set(value) {
    const branch = appStore.branches.find((b) => b.id === value) || null;
    appStore.setBranch(branch || null);
  },
});

onMounted(() => {
  if (isSuperAdmin.value) {
    appStore.fetchBranches();
  } else if (!appStore.currentBranch && auth.user?.branch) {
    appStore.setBranch({ id: auth.user.branch.id, name: auth.user.branch.name });
  }
});
</script>

<template>
  <div class="flex items-center gap-2">
    <template v-if="isSuperAdmin">
      <SearchableSelect
        v-model="selectedBranchId"
        :options="branchOptions"
        placeholder="Selecionar filial"
      />
    </template>
    <template v-else>
      <span
        class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
      >
        <MapPinIcon class="mr-2 h-4 w-4 text-slate-500" aria-hidden="true" />
        <span>
          Filial:
          <strong>{{ appStore.currentBranch?.name || auth.user?.branch?.name || 'Sem filial' }}</strong>
        </span>
      </span>
    </template>
  </div>
</template>

