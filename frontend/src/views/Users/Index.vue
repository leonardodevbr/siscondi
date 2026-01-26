<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Usuários</h2>
        <p class="text-xs text-slate-500">Gerencie os usuários da filial</p>
      </div>
      <Button type="button" variant="primary" @click="openForm(null)">Novo Usuário</Button>
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
      <div v-else-if="!userStore.users.length" class="text-center py-8">
        <p class="text-slate-500">Nenhum usuário encontrado</p>
      </div>
      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">E-mail</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Cargo</th>
              <th class="sticky right-0 bg-slate-50 px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider border-l border-slate-200">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="u in userStore.users" :key="u.id">
              <td class="px-4 sm:px-6 py-4 text-sm font-medium text-slate-900">{{ u.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ u.email }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ roleLabel(u.role) }}</td>
              <td class="sticky right-0 bg-white px-4 sm:px-6 py-4 text-right border-l border-slate-200">
                <div class="flex items-center justify-end gap-2">
                  <button type="button" class="text-amber-600 hover:text-amber-900 p-1 rounded hover:bg-amber-50 transition-colors" title="Editar" @click="openForm(u)">
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button type="button" class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50 transition-colors" title="Excluir" @click="confirmDelete(u)">
                    <TrashIcon class="h-5 w-5" />
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

    <Modal :is-open="showFormModal" :title="editingUser ? 'Editar Usuário' : 'Novo Usuário'" @close="closeForm">
      <UserForm :user="editingUser" @close="closeForm" @saved="onSaved" />
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAlert } from '@/composables/useAlert';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import UserForm from './Form.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const ROLE_LABELS = { seller: 'Vendedor', stockist: 'Estoquista', manager: 'Gerente', 'super-admin': 'Super Admin' };

const toast = useToast();
const { confirm } = useAlert();
const userStore = useUserStore();
const searchQuery = ref('');
const showFormModal = ref(false);
const editingUser = ref(null);
let searchTimeout = null;

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

function openForm(user) {
  editingUser.value = user ?? null;
  showFormModal.value = true;
}

function closeForm() {
  showFormModal.value = false;
  editingUser.value = null;
}

function onSaved() {
  closeForm();
  loadUsers();
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
