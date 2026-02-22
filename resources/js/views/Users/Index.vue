<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Usuários</h2>
        <p class="text-xs text-slate-500">Gerencie os usuários do sistema</p>
      </div>
      <Button v-if="authStore.can('users.create')" type="button" variant="primary" @click="$router.push({ name: 'users.create' })">Novo Usuário</Button>
    </div>

    <div class="card p-4 sm:p-6">
      <!-- Busca e filtros (uma linha em desktop, empilha em mobile) -->
      <div class="mb-4">
        <p class="text-sm font-medium text-slate-700 mb-3">Busca e filtros</p>
        <div class="flex flex-col sm:flex-row sm:items-end gap-3 sm:gap-4 flex-nowrap">
          <div class="min-w-0 flex-1 sm:max-w-xs">
            <label class="block text-sm font-medium text-slate-700 mb-1">Nome ou e-mail</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Digite para buscar..."
              class="w-full px-3 py-2.5 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              @input="debouncedSearch"
            />
          </div>
          <div class="min-w-0 w-full sm:w-40 shrink-0">
            <SelectInput
              v-model="filters.role"
              label="Tipo (cargo)"
              :options="roleOptionsForSelect"
              placeholder="Todos"
              :searchable="true"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="min-w-0 w-full sm:w-72 sm:min-w-[18rem] shrink-0">
            <SelectInput
              v-model="filters.department_id"
              label="Secretaria"
              :options="departmentOptionsForSelect"
              placeholder="Todas"
              :searchable="true"
              @update:model-value="applyFilters"
            />
          </div>
          <button
            type="button"
            class="shrink-0 min-h-[2.5rem] flex items-center justify-center px-4 py-2.5 border border-slate-300 rounded text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            @click="clearFilters"
          >
            Limpar filtros
          </button>
        </div>
      </div>

      <div v-if="userStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando usuários...</p>
      </div>
      <div v-else-if="!userStore.users.length" class="text-center py-8">
        <p class="text-slate-500">Nenhum usuário encontrado</p>
      </div>
      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">E-mail</th>
              <th v-if="showDepartmentColumn" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Secretaria principal</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Cargo</th>
              <th class="sticky right-0 z-10 bg-slate-50 px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider border-l border-slate-200">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="u in userStore.users" :key="u.id">
              <td class="px-4 sm:px-6 py-4 text-sm font-medium text-slate-900">{{ u.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ u.email }}</td>
              <td v-if="showDepartmentColumn" class="px-4 sm:px-6 py-4 text-sm text-slate-900">
                <div class="flex items-center gap-2">
                  <span>{{ u.department?.name ?? '—' }}</span>
                  <span
                    v-if="u.department_ids && u.department_ids.length > 1"
                    class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                    :title="`Acesso a ${u.department_ids.length} secretarias`"
                  >
                    +{{ u.department_ids.length - 1 }}
                  </span>
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">
                <span v-if="u.role" class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">{{ roleLabel(u.role) }}</span>
                <span v-else>—</span>
              </td>
              <td class="sticky right-0 z-10 bg-white px-4 sm:px-6 py-4 text-right border-l border-slate-200">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="u.id !== authStore.user?.id"
                    type="button"
                    class="p-1.5 text-red-600 hover:text-red-900 rounded hover:bg-red-50 transition-colors"
                    title="Excluir"
                    @click="confirmDelete(u)"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                  <button type="button" class="p-1.5 text-blue-600 hover:text-blue-900 rounded hover:bg-blue-50 transition-colors" title="Editar" @click="$router.push({ name: 'users.edit', params: { id: u.id } })">
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Paginação -->
      <PaginationBar
        v-if="!userStore.loading && (userStore.pagination || userStore.users.length > 0)"
        :pagination="userStore.pagination || defaultPagination"
        @page-change="(page) => loadUsers({ page })"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import PaginationBar from '@/components/Common/PaginationBar.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const ROLE_LABELS = {
  admin: 'Administrador',
  requester: 'Requerente',
  validator: 'Validador',
  authorizer: 'Concedente',
  payer: 'Pagador',
  beneficiary: 'Beneficiário',
  'super-admin': 'Super Admin',
};

const toast = useToast();
const { confirm } = useAlert();
const authStore = useAuthStore();
const userStore = useUserStore();
const searchQuery = ref('');
const perPageRef = ref(15);
const departmentOptions = ref([]);
const filters = ref({
  role: '',
  department_id: '',
});
let searchTimeout = null;

const roleOptionsForSelect = computed(() => [
  { value: '', label: 'Todos' },
  ...Object.entries(ROLE_LABELS).map(([value, label]) => ({ value, label })),
]);

const departmentOptionsForSelect = computed(() => [
  { value: '', label: 'Todas' },
  ...departmentOptions.value.map((d) => ({ value: d.id, label: d.name })),
]);

const defaultPagination = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
};

const showDepartmentColumn = computed(() => {
  if (!authStore.user) return false;
  const roles = authStore.user.roles || [];
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name;
    return ['super-admin', 'admin'].includes(name);
  });
});

function roleLabel(role) {
  return role ? (ROLE_LABELS[role] ?? role) : '—';
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage;
  loadUsers({ page: 1, per_page: perPage });
}

async function loadUsers(params = {}) {
  try {
    const p = { per_page: perPageRef.value, ...params };
    if (searchQuery.value) p.search = searchQuery.value;
    if (filters.value.role) p.role = filters.value.role;
    if (filters.value.department_id) p.department_id = filters.value.department_id;
    await userStore.fetchUsers(p);
    if (userStore.pagination?.per_page) perPageRef.value = userStore.pagination.per_page;
  } catch {
    toast.error('Erro ao carregar usuários');
  }
}

function applyFilters() {
  loadUsers({ page: 1 });
}

function clearFilters() {
  filters.value = { role: '', department_id: '' };
  searchQuery.value = '';
  loadUsers({ page: 1 });
}

async function loadDepartments() {
  try {
    const { data } = await api.get('/departments', { params: { all: 1 } });
    departmentOptions.value = data.data ?? data ?? [];
  } catch {
    departmentOptions.value = [];
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadUsers(), 500);
}

async function confirmDelete(user) {
  const ok = await confirm('Excluir usuário', `Tem certeza que deseja excluir "${user.name}"? Esta ação não pode ser desfeita.`);
  if (!ok) return;
  try {
    await userStore.deleteUser(user.id);
    toast.success('Usuário excluído com sucesso.');
    loadUsers();
  } catch {
    toast.error(userStore.error ?? 'Erro ao excluir usuário');
  }
}

onMounted(async () => {
  await loadDepartments();
  loadUsers();
});
</script>
