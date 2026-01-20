<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
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
  QrCodeIcon,
  BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const route = useRoute();

function can(permission) {
  // Placeholder para ACL futura
  return true;
}

const menuGroups = computed(() => [
  {
    title: 'Principal',
    items: [
      { name: 'Painel', to: { name: 'dashboard' }, icon: HomeIcon },
      { name: 'PDV / Frente de Caixa', to: { name: 'pos' }, icon: ComputerDesktopIcon },
    ],
  },
  {
    title: 'Operacional',
    items: [
      { name: 'Vendas Realizadas', to: { name: 'sales' }, icon: ShoppingBagIcon },
      { name: 'Produtos & Estoque', to: { name: 'products.index' }, icon: TagIcon },
      { name: 'Gerar Etiquetas', to: { name: 'products.labels' }, icon: QrCodeIcon },
      { name: 'Clientes', to: { name: 'customers' }, icon: UserGroupIcon },
      { name: 'Fornecedores', to: { name: 'suppliers' }, icon: TruckIcon },
    ],
  },
  {
    title: 'Financeiro',
    items: [
      { name: 'Despesas', to: { name: 'expenses' }, icon: BanknotesIcon, permission: 'financial.manage' },
      { name: 'Relatórios', to: { name: 'reports' }, icon: ChartBarIcon, permission: 'reports.view' },
    ],
  },
  {
    title: 'Admin',
    items: [
      { name: 'Filiais / Lojas', to: { name: 'branches.index' }, icon: BuildingStorefrontIcon },
      { name: 'Cupons', to: { name: 'coupons' }, icon: TicketIcon },
      { name: 'Configurações', to: { name: 'settings' }, icon: Cog6ToothIcon },
    ],
  },
]);

function isActive(item) {
  return route.name === item.to.name;
}

function handleClick() {
  emit('close');
}

function showItem(item) {
  if (!item) {
    return false;
  }

  if (item.permission && !can(item.permission)) {
    return false;
  }

  return true;
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
          <template
            v-for="item in group.items"
            :key="item.name"
          >
            <router-link
              v-if="showItem(item)"
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
          </template>
        </div>
      </div>
    </nav>
  </aside>
</template>
