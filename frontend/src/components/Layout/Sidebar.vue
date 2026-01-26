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
  UsersIcon,
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
  if (item.permission == null || item.permission === '') return true;
  return authStore.can(item.permission);
}

const menuGroups = computed(() => {
  const allGroups = [
    {
      title: 'Principal',
      items: [
        { name: 'Painel', to: { name: 'dashboard' }, icon: HomeIcon, permission: null },
        { name: 'PDV / Frente de Caixa', to: { name: 'pos' }, icon: ComputerDesktopIcon, permission: 'pos.access' },
      ],
    },
    {
      title: 'Operacional',
      items: [
        { name: 'Vendas Realizadas', to: { name: 'sales' }, icon: ShoppingBagIcon, permission: 'sales.view' },
        { name: 'Produtos & Estoque', to: { name: 'products.index' }, icon: TagIcon, permission: 'products.view' },
        { name: 'Gerar Etiquetas', to: { name: 'products.labels' }, icon: RectangleGroupIcon, permission: 'products.view' },
        { name: 'Movimentações', to: { name: 'inventory.movements' }, icon: ClipboardDocumentListIcon, permission: 'stock.view' },
        { name: 'Clientes', to: { name: 'customers' }, icon: UserGroupIcon, permission: 'customers.view' },
        { name: 'Fornecedores', to: { name: 'suppliers' }, icon: TruckIcon, permission: 'suppliers.view' },
      ],
    },
    {
      title: 'Financeiro',
      items: [
        { name: 'Despesas', to: { name: 'expenses' }, icon: BanknotesIcon, permission: 'expenses.view' },
        { name: 'Relatórios', to: { name: 'reports' }, icon: ChartBarIcon, permission: 'reports.view' },
      ],
    },
    {
      title: 'Admin',
      items: [
        { name: 'Filiais / Lojas', to: { name: 'branches.index' }, icon: BuildingStorefrontIcon, permission: 'branches.view' },
        { name: 'Usuários', to: { name: 'users.index' }, icon: UsersIcon, permission: 'users.view' },
        { name: 'Cupons', to: { name: 'coupons' }, icon: TicketIcon, permission: 'marketing.coupons' },
        { name: 'Configurações', to: { name: 'settings' }, icon: Cog6ToothIcon, permission: 'settings.manage' },
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
