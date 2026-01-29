<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLogo from '@/components/Common/AppLogo.vue';
import {
  HomeIcon,
  DocumentTextIcon,
  UserGroupIcon,
  ClipboardDocumentCheckIcon,
  BuildingOfficeIcon,
  BriefcaseIcon,
  MapPinIcon,
  UsersIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const route = useRoute();
const authStore = useAuthStore();

/** Item visível se não tem permission (ex.: Dashboard) ou se user pode (can) a permissão. */
function itemVisible(item) {
  // Verifica role específica (ex.: apenas super-admin)
  if (item.role) {
    return authStore.hasRole(item.role);
  }
  
  // Exclui roles específicas (ex.: vendedor não vê movimentações)
  if (item.excludeRoles && Array.isArray(item.excludeRoles)) {
    const userRoles = authStore.user?.roles || [];
    const hasExcludedRole = userRoles.some((r) => {
      const roleName = typeof r === 'string' ? r : r?.name;
      return item.excludeRoles.includes(roleName);
    });
    if (hasExcludedRole) {
      return false;
    }
  }
  
  // Verifica permissão
  if (item.permission == null || item.permission === '') return true;
  return authStore.can(item.permission);
}

const menuGroups = computed(() => {
  const allGroups = [
    {
      title: 'Principal',
      items: [
        { name: 'Dashboard', to: { name: 'dashboard' }, icon: HomeIcon, permission: null },
      ],
    },
    {
      title: 'Diárias',
      items: [
        { name: 'Nova Solicitação', to: { name: 'daily-requests.create' }, icon: ClipboardDocumentCheckIcon, permission: 'daily-requests.create' },
        { name: 'Minhas Solicitações', to: { name: 'daily-requests.index' }, icon: DocumentTextIcon, permission: 'daily-requests.view' },
      ],
    },
    {
      title: 'Cadastros',
      items: [
        { name: 'Servidores', to: { name: 'servants.index' }, icon: UserGroupIcon, permission: 'servants.view' },
        { name: 'Cargos', to: { name: 'cargos.index' }, icon: BriefcaseIcon, permission: 'cargos.view' },
        { name: 'Legislações', to: { name: 'legislations.index' }, icon: DocumentTextIcon, permission: 'legislations.view' },
        { name: 'Secretarias', to: { name: 'departments.index' }, icon: BuildingOfficeIcon, permission: 'departments.view' },
      ],
    },
    {
      title: 'Sistema',
      items: [
        { name: 'Dados do município', to: { name: 'municipality.profile' }, icon: BuildingOfficeIcon, role: 'admin' },
        { name: 'Usuários', to: { name: 'users.index' }, icon: UsersIcon, permission: 'users.view' },
        { name: 'Municípios', to: { name: 'municipalities.index' }, icon: MapPinIcon, permission: null, role: 'super-admin' },
        { name: 'Configurações', to: { name: 'settings' }, icon: Cog6ToothIcon, permission: null, role: 'super-admin' },
      ],
    },
  ];

  return allGroups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => itemVisible(item)),
    }))
    .filter((group) => group.items.length > 0);
});

function isActive(item) {
  return route.name === item.to.name;
}

function handleClick() {
  emit('close');
}
</script>

<template>
  <!-- Mobile overlay -->
  <div
    v-if="isOpen"
    class="fixed inset-0 z-40 bg-black/50 md:hidden"
    @click="emit('close')"
  />

  <aside
    class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 shadow-lg flex flex-col"
    :class="{
      '-translate-x-full md:translate-x-0': !isOpen,
      'translate-x-0': isOpen,
    }"
  >
    <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-4 bg-slate-50/80">
      <AppLogo icon-class="h-6 w-6" text-class="text-lg" :light="false" />
    </div>

    <nav class="flex-1 space-y-1 py-4 text-sm">
      <div
        v-for="group in menuGroups"
        :key="group.title"
        class="mt-4 first:mt-0"
      >
        <p class="mt-2 mb-1 px-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
          {{ group.title }}
        </p>

        <div class="space-y-0.5 px-2">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.to"
            class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-900 border-l-4 border-transparent"
            :class="
              isActive(item)
                ? 'border-emerald-500 bg-emerald-50 text-slate-900'
                : ''
            "
            @click="handleClick"
          >
            <component
              :is="item.icon"
              :class="['h-5 w-5 shrink-0', isActive(item) ? 'text-emerald-600' : 'text-slate-500 group-hover:text-slate-700']"
            />
            <span class="truncate">
              {{ item.name }}
            </span>
          </router-link>
        </div>
      </div>
    </nav>
  </aside>
</template>
