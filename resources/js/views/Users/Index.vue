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
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome ou e-mail..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
      </div>

      <div v-if="userStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando usuários...</p>
      </div>
      <div v-else-if="!filteredUsers.length" class="text-center py-8">
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
      <div v-if="userStore.pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-slate-500">Mostrando {{ userStore.pagination.from }} a {{ userStore.pagination.to }} de {{ userStore.pagination.total }} resultados</div>
        <div class="flex gap-2">
          <Button v-if="userStore.pagination.current_page > 1" variant="outline" @click="loadUsers({ page: userStore.pagination.current_page - 1 })">Anterior</Button>
          <Button v-if="userStore.pagination.current_page < userStore.pagination.last_page" variant="outline" @click="loadUsers({ page: userStore.pagination.current_page + 1 })">Próxima</Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAlert } from '@/composables/useAlert';
import Button from '@/components/Common/Button.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const ROLE_LABELS = {
  admin: 'Administrador',
  requester: 'Requerente',
  validator: 'Validador',
  authorizer: 'Concedente',
  payer: 'Pagador',
  'super-admin': 'Super Admin',
};

const toast = useToast();
const { confirm } = useAlert();
const authStore = useAuthStore();
const userStore = useUserStore();
const searchQuery = ref('');
let searchTimeout = null;

const showDepartmentColumn = computed(() => {
  if (!authStore.user) return false;
  const roles = authStore.user.roles || [];
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name;
    return ['super-admin', 'admin'].includes(name);
  });
});

// Filtra para remover o próprio usuário logado
const filteredUsers = computed(() => {
  return userStore.users.filter(u => u.id !== authStore.user?.id);
});

function roleLabel(role) {
  return role ? (ROLE_LABELS[role] ?? role) : '—';
}

async function loadUsers(params = {}) {
  try {
    const p = { ...params };
    if (searchQuery.value) p.search = searchQuery.value;
    await userStore.fetchUsers(p);
  } catch {
    toast.error('Erro ao carregar usuários');
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

onMounted(() => loadUsers());
</script>
