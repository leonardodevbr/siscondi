<script setup>
import { computed, onMounted, ref, onUnmounted } from 'vue';
import { MapPinIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { useAppStore } from '@/stores/app';
import { useAuthStore } from '@/stores/auth';

const appStore = useAppStore();
const auth = useAuthStore();
const isOpen = ref(false);
const dropdownRef = ref(null);

const isSuperAdmin = computed(() => {
  if (auth.user?.is_super_admin === true) return true;
  const roles = auth.user?.roles || [];
  return roles.some((r) => {
    if (typeof r === 'string') return r === 'super-admin';
    return r?.name === 'super-admin';
  });
});

const isOwner = computed(() => {
  const roles = auth.user?.roles || [];
  return roles.some((r) => {
    if (typeof r === 'string') return r === 'owner';
    return r?.name === 'owner';
  });
});

// Usuário tem múltiplas filiais?
const hasMultipleBranches = computed(() => {
  const branchIds = auth.user?.branch_ids || [];
  return branchIds.length > 1;
});

// Deve mostrar o switcher? (Super Admin, Owner ou usuário com múltiplas filiais)
const showSwitcher = computed(() => {
  return isSuperAdmin.value || isOwner.value || hasMultipleBranches.value;
});

const currentBranchName = computed(() => {
  if (isSuperAdmin.value || isOwner.value || hasMultipleBranches.value) {
    return appStore.currentBranch?.name || 'Sem filial';
  }
  return auth.user?.branch?.name || 'Sem filial';
});

// Lista de filiais disponíveis
const branches = computed(() => {
  if (isSuperAdmin.value || isOwner.value) {
    // Super Admin e Owner veem todas
    return appStore.branches || [];
  } else if (hasMultipleBranches.value) {
    // Usuário com múltiplas filiais: usa a lista que veio do backend
    const userBranches = auth.user?.branches || [];
    if (userBranches.length > 0) {
      return userBranches; // Já vem completo do backend
    }
    // Fallback: filtra do appStore
    const userBranchIds = auth.user?.branch_ids || [];
    return (appStore.branches || []).filter(b => userBranchIds.includes(b.id));
  }
  return [];
});

function toggleDropdown() {
  isOpen.value = !isOpen.value;
}

function selectBranch(branch) {
  appStore.setBranch(branch);
  isOpen.value = false;
}

function handleClickOutside(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

onMounted(async () => {
  document.addEventListener('click', handleClickOutside);

  // Carrega filiais se necessário
  if (showSwitcher.value) {
    // Se o usuário já tem branches no auth, usa elas
    if (hasMultipleBranches.value && auth.user?.branches?.length > 0) {
      // Já tem as branches carregadas
      if (!appStore.currentBranch && auth.user.branches.length > 0) {
        appStore.setBranch(auth.user.branches[0]);
      }
    } else {
      // Caso contrário, busca do backend
      await appStore.fetchBranches();
      if (!appStore.currentBranch && branches.value.length > 0) {
        appStore.setBranch(branches.value[0]);
      }
    }
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div class="flex items-center gap-2">
    <template v-if="showSwitcher">
      <div ref="dropdownRef" class="relative">
        <button
          type="button"
          class="inline-flex cursor-pointer items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200 transition-colors"
          @click.stop="toggleDropdown"
        >
          <MapPinIcon class="mr-2 h-4 w-4 text-slate-500" aria-hidden="true" />
          <span>
            Filial:
            <strong>{{ currentBranchName }}</strong>
          </span>
          <ChevronDownIcon
            class="ml-2 h-4 w-4 text-slate-500 transition-transform"
            :class="{ 'rotate-180': isOpen }"
            aria-hidden="true"
          />
        </button>

        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="isOpen"
            class="absolute right-0 mt-2 w-56 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 border border-slate-200"
            style="z-index: 9999;"
          >
            <div class="py-1">
              <button
                v-for="branch in branches"
                :key="branch.id"
                type="button"
                class="w-full cursor-pointer text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors flex items-center"
                :class="{
                  'bg-blue-50 text-blue-700 font-medium': appStore.currentBranch?.id === branch.id,
                }"
                @click.stop="selectBranch(branch)"
              >
                <MapPinIcon
                  v-if="appStore.currentBranch?.id === branch.id"
                  class="mr-2 h-4 w-4"
                  aria-hidden="true"
                />
                <span class="flex-1">{{ branch.name }}</span>
                <span
                  v-if="branch.is_main"
                  class="ml-2 text-xs text-slate-500"
                >
                  (Principal)
                </span>
              </button>
            </div>
          </div>
        </transition>
      </div>
    </template>
    <template v-else>
      <span
        class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
      >
        <MapPinIcon class="mr-2 h-4 w-4 text-slate-500" aria-hidden="true" />
        <span>
          Filial:
          <strong>{{ currentBranchName }}</strong>
        </span>
      </span>
    </template>
  </div>
</template>

