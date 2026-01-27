<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
        @click="$router.push({ name: 'users' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ userId ? 'Editar Usuário' : 'Novo Usuário' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ userId ? 'Atualize os dados do usuário' : 'Preencha os dados para criar um novo usuário' }}
        </p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="card p-12 text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-blue-600"></div>
      <p class="text-slate-500 mt-3">Carregando...</p>
    </div>

    <!-- Form -->
    <form v-else class="card p-6" @submit.prevent="submit">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Informações Básicas -->
        <div class="lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <UserIcon class="h-5 w-5 text-slate-500" />
            Informações Básicas
          </h3>
        </div>

        <div>
          <Input v-model="form.name" label="Nome Completo" required placeholder="Ex: João Silva" />
        </div>

        <div>
          <Input v-model="form.email" label="E-mail" type="email" required placeholder="joao@exemplo.com" />
        </div>

        <!-- Senhas -->
        <div class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <KeyIcon class="h-5 w-5 text-slate-500" />
            Segurança e Acesso
          </h3>
        </div>

        <div>
          <Input
            v-model="form.password"
            :label="userId ? 'Nova Senha (deixe em branco para não alterar)' : 'Senha'"
            type="password"
            :required="!userId"
            autocomplete="new-password"
            placeholder="Mínimo 8 caracteres"
          />
        </div>

        <div>
          <Input
            v-model="form.password_confirmation"
            label="Confirmação da Senha"
            type="password"
            :required="!userId && !!form.password"
            autocomplete="new-password"
            placeholder="Repita a senha"
          />
        </div>

        <div>
          <Input
            v-model="form.operation_password"
            label="Senha de Operação (opcional)"
            type="password"
            autocomplete="off"
            placeholder="Para operações sensíveis"
          />
          <p class="mt-1 text-xs text-slate-500">Usada para autorizar cancelamentos e ajustes</p>
        </div>

        <div v-if="form.role === 'manager' || form.role === 'owner' || form.role === 'super-admin'">
          <Input
            v-model="form.operation_pin"
            label="PIN de Autorização (PDV)"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="off"
            maxlength="10"
            placeholder="Ex: 1234"
            @input="form.operation_pin = form.operation_pin.replace(/[^0-9]/g, '')"
          />
          <p class="mt-1 text-xs text-slate-500">Apenas números, máximo 10 dígitos</p>
        </div>

        <!-- Cargo e Permissões -->
        <div class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <ShieldCheckIcon class="h-5 w-5 text-slate-500" />
            Cargo e Permissões
          </h3>
        </div>

        <div class="lg:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Cargo *</label>
          <select
            v-model="form.role"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Selecione o cargo</option>
            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <!-- Info Box: Gestor Geral -->
          <div v-if="form.role === 'owner'" class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-3">
              <InformationCircleIcon class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
              <div class="text-sm text-blue-800">
                <strong class="font-semibold">Gestor(a) Geral:</strong>
                <ul class="mt-1 space-y-1 list-disc list-inside">
                  <li>Tem acesso automático a <strong>todas as filiais</strong></li>
                  <li>Possui mesmas permissões de <strong>Gerente</strong></li>
                  <li>Não pode excluir Super Administradores</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Info Box: Gerente -->
          <div v-else-if="form.role === 'manager'" class="mt-3 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start gap-3">
              <InformationCircleIcon class="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" />
              <div class="text-sm text-amber-800">
                <strong class="font-semibold">Gerente:</strong> Acesso completo à(s) filial(is) vinculada(s), exceto configurações críticas do sistema.
              </div>
            </div>
          </div>

          <!-- Info Box: Vendedor -->
          <div v-else-if="form.role === 'seller'" class="mt-3 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-start gap-3">
              <InformationCircleIcon class="h-5 w-5 text-green-600 mt-0.5 flex-shrink-0" />
              <div class="text-sm text-green-800">
                <strong class="font-semibold">Vendedor:</strong> Acesso ao PDV, visualização de produtos, estoque e clientes da sua filial.
              </div>
            </div>
          </div>

          <!-- Info Box: Estoquista -->
          <div v-else-if="form.role === 'stockist'" class="mt-3 p-4 bg-purple-50 border border-purple-200 rounded-lg">
            <div class="flex items-start gap-3">
              <InformationCircleIcon class="h-5 w-5 text-purple-600 mt-0.5 flex-shrink-0" />
              <div class="text-sm text-purple-800">
                <strong class="font-semibold">Estoquista:</strong> Gerenciamento de produtos, estoque, entradas e fornecedores.
              </div>
            </div>
          </div>
        </div>

        <!-- Filiais (apenas Super Admin) -->
        <div v-if="authStore.user?.is_super_admin && form.role !== 'owner'" class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BuildingStorefrontIcon class="h-5 w-5 text-slate-500" />
            Filiais
          </h3>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Selecione as Filiais *</label>
            <p class="text-xs text-slate-500 mb-3">Escolha uma ou mais filiais que o usuário terá acesso</p>
            
            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <!-- Busca -->
              <input
                v-model="branchSearchQuery"
                type="text"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"
                placeholder="Pesquisar filiais..."
              >
              
              <!-- Tags selecionadas -->
              <div v-if="selectedBranches.length" class="mb-3 flex flex-wrap gap-2">
                <span
                  v-for="branch in selectedBranches"
                  :key="branch.id"
                  class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-sm text-blue-800"
                >
                  {{ branch.name }}
                  <button
                    type="button"
                    class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    aria-label="Remover"
                    @click="removeBranchId(branch.id)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </span>
              </div>
              
              <!-- Lista de checkboxes -->
              <div class="max-h-48 overflow-y-auto rounded border border-slate-100">
                <label
                  v-for="branch in filteredBranchOptions"
                  :key="branch.value"
                  :class="[
                    'flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50',
                    form.branch_ids.includes(branch.value) ? 'bg-blue-50' : ''
                  ]"
                >
                  <input
                    type="checkbox"
                    :checked="form.branch_ids.includes(branch.value)"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleBranchId(branch.value)"
                  >
                  <span class="text-sm text-slate-800">{{ branch.label }}</span>
                </label>
                <p v-if="filteredBranchOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                  Nenhuma filial encontrada.
                </p>
              </div>
            </div>
          </div>

          <!-- Seleção de Filial Primária -->
          <div v-if="form.branch_ids && form.branch_ids.length > 1" class="mt-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">Filial Primária</label>
            <p class="text-xs text-slate-500 mb-3">Filial padrão onde o usuário atuará com mais frequência</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label
                v-for="branchId in form.branch_ids"
                :key="branchId"
                class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-slate-50 transition-all"
                :class="{
                  'border-blue-500 bg-blue-50 shadow-sm': form.primary_branch_id === branchId,
                  'border-slate-200': form.primary_branch_id !== branchId
                }"
              >
                <input
                  v-model="form.primary_branch_id"
                  type="radio"
                  :value="branchId"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300"
                >
                <span class="ml-3 text-sm font-medium text-slate-900">
                  {{ branchOptions.find(b => b.value === branchId)?.label }}
                </span>
                <CheckCircleIcon
                  v-if="form.primary_branch_id === branchId"
                  class="ml-auto h-5 w-5 text-blue-600"
                />
              </label>
            </div>
          </div>
        </div>

        <!-- Info: Owner mostra filiais (read-only) -->
        <div v-if="authStore.user?.is_super_admin && form.role === 'owner'" class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BuildingStorefrontIcon class="h-5 w-5 text-slate-500" />
            Filiais (Acesso Automático)
          </h3>
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
            <p class="text-sm text-slate-700 mb-3">O(A) Gestor(a) Geral tem acesso automático a todas as filiais:</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="branch in appStore.branches"
                :key="branch.id"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-full text-xs font-medium text-slate-700"
              >
                <CheckCircleIcon class="h-4 w-4 text-green-600" />
                {{ branch.name }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
        <Button type="button" variant="outline" @click="$router.push({ name: 'users' })">
          Cancelar
        </Button>
        <Button type="submit" :loading="saving">
          <template v-if="userId">
            Atualizar Usuário
          </template>
          <template v-else>
            Criar Usuário
          </template>
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import {
  ArrowLeftIcon,
  UserIcon,
  KeyIcon,
  ShieldCheckIcon,
  BuildingStorefrontIcon,
  InformationCircleIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const roleOptions = [
  { value: 'seller', label: 'Vendedor(a)' },
  { value: 'stockist', label: 'Estoquista' },
  { value: 'manager', label: 'Gerente' },
  { value: 'owner', label: 'Gestor(a) Geral' },
  { value: 'super-admin', label: 'Super Admin' },
];

const route = useRoute();
const router = useRouter();
const toast = useToast();
const userStore = useUserStore();
const authStore = useAuthStore();
const appStore = useAppStore();

const userId = computed(() => route.params.id ? parseInt(route.params.id) : null);
const loading = ref(false);
const saving = ref(false);
const branchSearchQuery = ref('');

const branchOptions = computed(() =>
  (appStore.branches || []).map((b) => ({ value: b.id, label: b.name }))
);

const filteredBranchOptions = computed(() => {
  if (!branchSearchQuery.value) return branchOptions.value;
  const q = branchSearchQuery.value.toLowerCase();
  return branchOptions.value.filter(b => b.label.toLowerCase().includes(q));
});

const selectedBranches = computed(() => {
  return form.value.branch_ids
    .map(id => appStore.branches?.find(b => b.id === id))
    .filter(Boolean);
});

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  operation_password: '',
  operation_pin: '',
  branch_id: null,
  branch_ids: [],
  primary_branch_id: null,
  role: null,
});

onMounted(async () => {
  if (authStore.user?.is_super_admin) {
    await appStore.fetchBranches();
  }

  if (userId.value) {
    // Impede edição do próprio usuário
    if (userId.value === authStore.user?.id) {
      toast.error('Você não pode editar seu próprio usuário por aqui. Use a página de Perfil.');
      router.push({ name: 'profile' });
      return;
    }
    await loadUser();
  }
});

async function loadUser() {
  loading.value = true;
  try {
    const user = await userStore.fetchUser(userId.value);
    
    if (!user) {
      toast.error('Usuário não encontrado.');
      router.push({ name: 'users' });
      return;
    }

    form.value = {
      name: user.name ?? '',
      email: user.email ?? '',
      password: '',
      password_confirmation: '',
      operation_password: '',
      operation_pin: '',
      branch_id: user.branch_id ?? user.branch?.id ?? null,
      branch_ids: user.branch_ids ?? [],
      primary_branch_id: user.primary_branch_id ?? user.branch_id ?? null,
      role: user.role ?? null,
    };
  } catch (error) {
    toast.error('Erro ao carregar usuário.');
    router.push({ name: 'users' });
  } finally {
    loading.value = false;
  }
}

// Watch para auto-selecionar todas as filiais quando escolher "Dono"
watch(() => form.value.role, (newRole) => {
  if (newRole === 'owner' && authStore.user?.is_super_admin) {
    // Auto-seleciona todas as filiais
    form.value.branch_ids = branchOptions.value.map(b => b.value);
    // Define a primeira como primária
    if (form.value.branch_ids.length > 0) {
      form.value.primary_branch_id = form.value.branch_ids[0];
    }
  }
});

function toggleBranchId(branchId) {
  const index = form.value.branch_ids.indexOf(branchId);
  if (index > -1) {
    form.value.branch_ids.splice(index, 1);
    // Se remover a filial primária, define outra
    if (form.value.primary_branch_id === branchId) {
      form.value.primary_branch_id = form.value.branch_ids[0] || null;
    }
  } else {
    form.value.branch_ids.push(branchId);
    // Se é a primeira, define como primária
    if (form.value.branch_ids.length === 1) {
      form.value.primary_branch_id = branchId;
    }
  }
}

function removeBranchId(branchId) {
  const index = form.value.branch_ids.indexOf(branchId);
  if (index > -1) {
    form.value.branch_ids.splice(index, 1);
    // Se remover a filial primária, define outra
    if (form.value.primary_branch_id === branchId) {
      form.value.primary_branch_id = form.value.branch_ids[0] || null;
    }
  }
}

async function submit() {
  // Validações
  if (!form.value.name || !form.value.email) {
    toast.error('Nome e e-mail são obrigatórios.');
    return;
  }
  if (!userId.value && (!form.value.password || form.value.password !== form.value.password_confirmation)) {
    toast.error('Senha e confirmação devem ser iguais.');
    return;
  }
  if (userId.value && form.value.password && form.value.password !== form.value.password_confirmation) {
    toast.error('Nova senha e confirmação devem ser iguais.');
    return;
  }
  if (!form.value.role) {
    toast.error('Selecione o cargo.');
    return;
  }
  if (authStore.user?.is_super_admin && form.value.role !== 'owner' && (!form.value.branch_ids || form.value.branch_ids.length === 0)) {
    toast.error('Selecione pelo menos uma filial.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
    };

    // Envia múltiplas filiais se Super Admin
    if (authStore.user?.is_super_admin) {
      payload.branch_ids = form.value.branch_ids;
      payload.primary_branch_id = form.value.primary_branch_id || form.value.branch_ids[0];
      // Mantém branch_id para compatibilidade
      payload.branch_id = payload.primary_branch_id;
    }

    if (form.value.password) {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
    }

    if (form.value.operation_password && !userId.value) {
      payload.operation_password = form.value.operation_password;
    }

    const isManagerOrOwnerOrSuperAdmin = ['manager', 'owner', 'super-admin'].includes(form.value.role);
    if (isManagerOrOwnerOrSuperAdmin) {
      payload.operation_pin = form.value.operation_pin?.trim() || null;
    }

    if (userId.value) {
      payload.operation_password = form.value.operation_password === '' ? null : (form.value.operation_password || null);
      if (Object.prototype.hasOwnProperty.call(payload, 'password') && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
      }
      await userStore.updateUser(userId.value, payload);
      toast.success('Usuário atualizado com sucesso.');
    } else {
      await userStore.createUser(payload);
      toast.success('Usuário criado com sucesso.');
    }

    router.push({ name: 'users' });
  } catch (err) {
    const msg = err.response?.data?.message ?? err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : 'Erro ao salvar usuário.';
    toast.error(msg);
  } finally {
    saving.value = false;
  }
}
</script>
