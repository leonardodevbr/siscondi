<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Filiais / Lojas</h2>
        <p class="text-xs text-slate-500">
          Gerencie as filiais e lojas do sistema
        </p>
      </div>
      <button
        @click="showCreateModal = true"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors"
      >
        Nova Filial
      </button>
    </div>

    <div class="card">
      <div v-if="loading" class="text-center py-8">
        <p class="text-slate-500">Carregando filiais...</p>
      </div>

      <div v-else-if="branches.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhuma filial cadastrada</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Nome
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Tipo
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Criado em
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="branch in branches" :key="branch.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">{{ branch.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="branch.is_main"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                >
                  Matriz
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800"
                >
                  Filial
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                {{ formatDate(branch.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="editBranch(branch)"
                  class="text-blue-600 hover:text-blue-900 mr-4"
                >
                  Editar
                </button>
                <button
                  v-if="!branch.is_main"
                  @click="deleteBranch(branch)"
                  class="text-red-600 hover:text-red-900"
                >
                  Excluir
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal de criação/edição -->
    <div
      v-if="showCreateModal || editingBranch"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg border border-slate-200 w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">
          {{ editingBranch ? 'Editar Filial' : 'Nova Filial' }}
        </h3>

        <form @submit.prevent="saveBranch" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Nome da Filial
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: Filial Shopping Center"
            />
          </div>

          <div>
            <label class="flex items-center">
              <input
                v-model="form.is_main"
                type="checkbox"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                :disabled="editingBranch && editingBranch.is_main"
              />
              <span class="ml-2 text-sm text-slate-700">Filial Principal (Matriz)</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:bg-slate-400 transition-colors"
            >
              <span v-if="saving">Salvando...</span>
              <span v-else>Salvar</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/services/api';
import { useAlert } from '@/composables/useAlert';

export default {
  name: 'BranchesIndex',
  data() {
    return {
      branches: [],
      loading: true,
      showCreateModal: false,
      editingBranch: null,
      saving: false,
      form: {
        name: '',
        is_main: false,
      },
    };
  },
  mounted() {
    this.loadBranches();
  },
  methods: {
    async loadBranches() {
      try {
        this.loading = true;
        const response = await api.get('/branches');
        this.branches = response.data.data || response.data || [];
      } catch (error) {
        console.error('Erro ao carregar filiais:', error);
        this.$toast?.error('Erro ao carregar filiais');
      } finally {
        this.loading = false;
      }
    },
    editBranch(branch) {
      this.editingBranch = branch;
      this.form = {
        name: branch.name,
        is_main: branch.is_main,
      };
    },
    async saveBranch() {
      try {
        this.saving = true;
        
        if (this.editingBranch) {
          await api.put(`/branches/${this.editingBranch.id}`, this.form);
          this.$toast?.success('Filial atualizada com sucesso!');
        } else {
          await api.post('/branches', this.form);
          this.$toast?.success('Filial criada com sucesso!');
        }
        
        this.closeModal();
        this.loadBranches();
      } catch (error) {
        console.error('Erro ao salvar filial:', error);
        const message = error.response?.data?.message || 'Erro ao salvar filial';
        this.$toast?.error(message);
      } finally {
        this.saving = false;
      }
    },
    async deleteBranch(branch) {
      const { confirm } = useAlert();
      const confirmed = await confirm(
        'Excluir Filial',
        `Tem certeza que deseja excluir a filial "${branch.name}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmed) {
        return;
      }

      try {
        await api.delete(`/branches/${branch.id}`);
        this.$toast?.success('Filial excluída com sucesso!');
        this.loadBranches();
      } catch (error) {
        console.error('Erro ao excluir filial:', error);
        const message = error.response?.data?.message || 'Erro ao excluir filial';
        this.$toast?.error(message);
      }
    },
    closeModal() {
      this.showCreateModal = false;
      this.editingBranch = null;
      this.form = {
        name: '',
        is_main: false,
      };
    },
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('pt-BR');
    },
  },
};
</script>
