<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Secretarias</h2>
        <p class="text-xs text-slate-500">
          Gerencie as secretarias e setores do sistema
        </p>
      </div>
      <button
        @click="showCreateModal = true"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors"
      >
        Nova Secretaria
      </button>
    </div>

    <div class="card">
      <div v-if="loading" class="text-center py-8">
        <p class="text-slate-500">Carregando secretarias...</p>
      </div>

      <div v-else-if="departments.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhuma secretaria cadastrada</p>
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
            <tr v-for="dept in departments" :key="dept.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">{{ dept.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="dept.is_main"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                >
                  Principal
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800"
                >
                  Secretaria
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                {{ formatDate(dept.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-1">
                  <button
                    v-if="!dept.is_main"
                    type="button"
                    class="p-1.5 text-red-600 hover:text-red-900 rounded hover:bg-red-50 transition-colors"
                    title="Excluir"
                    @click="deleteDepartment(dept)"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-blue-600 hover:text-blue-900 rounded hover:bg-blue-50 transition-colors"
                    title="Editar"
                    @click="editDepartment(dept)"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="showCreateModal || editingDepartment"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div class="bg-white rounded-lg border border-slate-200 w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">
          {{ editingDepartment ? 'Editar Secretaria' : 'Nova Secretaria' }}
        </h3>

        <form @submit.prevent="saveDepartment" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: Secretaria de Educação"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">CNPJ</label>
            <input
              v-model="form.cnpj"
              type="text"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="00.000.000/0001-00"
              maxlength="18"
            />
          </div>

          <div class="border-t border-slate-200 pt-4">
            <h4 class="text-sm font-semibold text-slate-800 mb-2">Dados do fundo (pagamento)</h4>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nome do fundo</label>
                <input
                  v-model="form.fund_name"
                  type="text"
                  class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ex: Fundo Municipal de Educação"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Código do fundo</label>
                <input
                  v-model="form.fund_code"
                  type="text"
                  class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ex: 001"
                  maxlength="50"
                />
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Brasão / Logo</label>
            <p class="text-xs text-slate-500 mb-2">Caminho do arquivo ou URL (upload em versão futura)</p>
            <input
              v-model="form.logo_path"
              type="text"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: departments/1/logo.png"
            />
          </div>

          <div>
            <label class="flex items-center">
              <input
                v-model="form.is_main"
                type="checkbox"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                :disabled="editingDepartment && editingDepartment.is_main"
              />
              <span class="ml-2 text-sm text-slate-700">Secretaria principal</span>
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
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

export default {
  components: { PencilSquareIcon, TrashIcon },
  name: 'DepartmentsIndex',
  data() {
    return {
      departments: [],
      loading: true,
      showCreateModal: false,
      editingDepartment: null,
      saving: false,
      form: {
        name: '',
        is_main: false,
      },
    };
  },
  mounted() {
    this.loadDepartments();
  },
  methods: {
    async loadDepartments() {
      try {
        this.loading = true;
        const response = await api.get('/departments');
        this.departments = response.data.data || response.data || [];
      } catch (error) {
        console.error('Erro ao carregar secretarias:', error);
        this.$toast?.error('Erro ao carregar secretarias');
      } finally {
        this.loading = false;
      }
    },
    editDepartment(dept) {
      this.editingDepartment = dept;
      this.form = {
        name: dept.name,
        is_main: dept.is_main ?? false,
        cnpj: dept.cnpj ?? '',
        fund_name: dept.fund_name ?? '',
        fund_code: dept.fund_code ?? '',
        logo_path: dept.logo_path ?? '',
      };
    },
    async saveDepartment() {
      try {
        this.saving = true;

        if (this.editingDepartment) {
          await api.put(`/departments/${this.editingDepartment.id}`, this.form);
          this.$toast?.success('Secretaria atualizada com sucesso.');
        } else {
          await api.post('/departments', this.form);
          this.$toast?.success('Secretaria criada com sucesso.');
        }

        this.closeModal();
        this.loadDepartments();
      } catch (error) {
        console.error('Erro ao salvar secretaria:', error);
        const message = error.response?.data?.message || 'Erro ao salvar secretaria';
        this.$toast?.error(message);
      } finally {
        this.saving = false;
      }
    },
    async deleteDepartment(dept) {
      const { confirm } = useAlert();
      const confirmed = await confirm(
        'Excluir secretaria',
        `Tem certeza que deseja excluir a secretaria "${dept.name}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmed) return;

      try {
        await api.delete(`/departments/${dept.id}`);
        this.$toast?.success('Secretaria excluída com sucesso.');
        this.loadDepartments();
      } catch (error) {
        console.error('Erro ao excluir secretaria:', error);
        const message = error.response?.data?.message || 'Erro ao excluir secretaria';
        this.$toast?.error(message);
      }
    },
    closeModal() {
      this.showCreateModal = false;
      this.editingDepartment = null;
      this.form = {
        name: '',
        is_main: false,
        cnpj: '',
        fund_name: '',
        fund_code: '',
        logo_path: '',
      };
    },
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('pt-BR');
    },
  },
};
</script>
