<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
  HomeIcon,
  ComputerDesktopIcon,
  ShoppingBagIcon,
  TagIcon,
  UserGroupIcon,
  TruckIcon,
  BanknotesIcon,
  ChartBarIcon,
  TicketIcon,
  Cog6ToothIcon,
  BuildingStorefrontIcon,
  ClipboardDocumentListIcon,
  RectangleGroupIcon,
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

function hasRole(roleName) {
  const roles = authStore.user?.roles || [];
  return roles.some((r) => {
    if (typeof r === 'string') {
      return r === roleName;
    }
    return r?.name === roleName;
  });
}

function hasAnyRole(roleNames) {
  if (roleNames.includes('*')) {
    return true;
  }
  return roleNames.some((role) => hasRole(role));
}

const menuGroups = computed(() => {
  const allGroups = [
    {
      title: 'Principal',
      items: [
        { name: 'Painel', to: { name: 'dashboard' }, icon: HomeIcon, roles: ['*'] },
        { name: 'PDV / Frente de Caixa', to: { name: 'pos' }, icon: ComputerDesktopIcon, roles: ['*'] },
      ],
    },
    {
      title: 'Operacional',
      items: [
        { name: 'Vendas Realizadas', to: { name: 'sales' }, icon: ShoppingBagIcon, roles: ['*'] },
        { name: 'Produtos & Estoque', to: { name: 'products.index' }, icon: TagIcon, roles: ['*'] },
        { name: 'Gerar Etiquetas', to: { name: 'products.labels' }, icon: RectangleGroupIcon, roles: ['*'] },
        { name: 'Movimentações', to: { name: 'inventory.movements' }, icon: ClipboardDocumentListIcon, roles: ['super-admin', 'manager', 'stockist'] },
        { name: 'Clientes', to: { name: 'customers' }, icon: UserGroupIcon, roles: ['*'] },
        { name: 'Fornecedores', to: { name: 'suppliers' }, icon: TruckIcon, roles: ['super-admin', 'manager'] },
      ],
    },
    {
      title: 'Financeiro',
      items: [
        { name: 'Despesas', to: { name: 'expenses' }, icon: BanknotesIcon, roles: ['super-admin', 'manager'] },
        { name: 'Relatórios', to: { name: 'reports' }, icon: ChartBarIcon, roles: ['super-admin', 'manager'] },
      ],
    },
    {
      title: 'Admin',
      items: [
        { name: 'Filiais / Lojas', to: { name: 'branches.index' }, icon: BuildingStorefrontIcon, roles: ['super-admin'] },
        { name: 'Cupons', to: { name: 'coupons' }, icon: TicketIcon, roles: ['super-admin', 'manager'] },
        { name: 'Configurações', to: { name: 'settings' }, icon: Cog6ToothIcon, roles: ['super-admin'] },
      ],
    },
  ];

  return allGroups.map((group) => ({
    ...group,
    items: group.items.filter((item) => hasAnyRole(item.roles || ['*'])),
  })).filter((group) => group.items.length > 0);
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
    class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transition-transform duration-300 ease-in-out shadow-lg flex flex-col"
    :class="{
      '-translate-x-full md:translate-x-0': !isOpen,
      'translate-x-0': isOpen,
    }"
  >
    <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-4">
      <span class="text-sm font-semibold tracking-wide text-white">
        ADONAI
      </span>
      <span class="text-xs text-slate-400">PDV • ERP</span>
    </div>

    <nav class="flex-1 space-y-1 py-4 text-sm">
      <div
        v-for="group in menuGroups"
        :key="group.title"
        class="mt-4 first:mt-0"
      >
        <p class="mt-2 mb-1 px-4 text-xs font-semibold uppercase tracking-wide text-slate-400">
          {{ group.title }}
        </p>

        <div class="space-y-0.5 px-2">
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="item.to"
            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white border-l-4 border-transparent"
            :class="
              isActive(item)
                ? 'border-blue-500 bg-slate-800 text-white'
                : ''
            "
            @click="handleClick"
          >
            <component
              :is="item.icon"
              class="h-5 w-5 text-slate-400"
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
