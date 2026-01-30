<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
        @click="$router.push({ name: 'users.index' })"
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

        <div v-if="form.roles && (form.roles.includes('admin') || form.roles.includes('super-admin'))">
          <Input
            v-model="form.operation_pin"
            label="PIN de Autorização (opcional)"
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

        <!-- Perfis (Roles) -->
        <div class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <ShieldCheckIcon class="h-5 w-5 text-slate-500" />
            Perfis de acesso
          </h3>
        </div>

        <div class="lg:col-span-2">
          <SelectInput
            v-model="form.roles"
            label="Perfis (roles) *"
            :options="roleOptions"
            placeholder="Selecione um ou mais perfis"
            mode="multiple"
            :searchable="false"
          />
          <p class="mt-1 text-xs text-slate-500">Vincule diretamente os perfis de acesso ao usuário. Servidores (usuários com cargo) herdam as roles do cargo e podem ter roles adicionais.</p>
        </div>

        <!-- Secretarias (apenas Super Admin) -->
        <div v-if="authStore.user?.is_super_admin" class="lg:col-span-2 border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BuildingStorefrontIcon class="h-5 w-5 text-slate-500" />
            Secretarias
          </h3>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Selecione as secretarias *</label>
            <p class="text-xs text-slate-500 mb-3">Escolha uma ou mais secretarias que o usuário terá acesso</p>

            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <input
                v-model="departmentSearchQuery"
                type="text"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"
                placeholder="Pesquisar secretarias..."
              >

              <div v-if="selectedDepartments.length" class="mb-3 flex flex-wrap gap-2">
                <span
                  v-for="dept in selectedDepartments"
                  :key="dept.id"
                  class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-sm text-blue-800"
                >
                  {{ dept.name }}
                  <button
                    type="button"
                    class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    aria-label="Remover"
                    @click="removeDepartmentId(dept.id)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </span>
              </div>

              <div class="max-h-48 overflow-y-auto rounded border border-slate-100">
                <label
                  v-for="opt in filteredDepartmentOptions"
                  :key="opt.value"
                  :class="[
                    'flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50',
                    form.department_ids.includes(opt.value) ? 'bg-blue-50' : ''
                  ]"
                >
                  <input
                    type="checkbox"
                    :checked="form.department_ids.includes(opt.value)"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleDepartmentId(opt.value)"
                  >
                  <span class="text-sm text-slate-800">{{ opt.label }}</span>
                </label>
                <p v-if="filteredDepartmentOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                  Nenhuma secretaria encontrada.
                </p>
              </div>
            </div>
          </div>

          <div v-if="form.department_ids && form.department_ids.length > 1" class="mt-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">Secretaria principal</label>
            <p class="text-xs text-slate-500 mb-3">Secretaria padrão onde o usuário atuará com mais frequência</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label
                v-for="deptId in form.department_ids"
                :key="deptId"
                class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-slate-50 transition-all"
                :class="{
                  'border-blue-500 bg-blue-50 shadow-sm': form.primary_department_id === deptId,
                  'border-slate-200': form.primary_department_id !== deptId
                }"
              >
                <input
                  v-model="form.primary_department_id"
                  type="radio"
                  :value="deptId"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300"
                >
                <span class="ml-3 text-sm font-medium text-slate-900">
                  {{ departmentOptions.find(d => d.value === deptId)?.label }}
                </span>
                <CheckCircleIcon
                  v-if="form.primary_department_id === deptId"
                  class="ml-auto h-5 w-5 text-blue-600"
                />
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
        <Button type="button" variant="outline" @click="$router.push({ name: 'users.index' })">
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
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import {
  ArrowLeftIcon,
  UserIcon,
  KeyIcon,
  ShieldCheckIcon,
  BuildingStorefrontIcon,
  DocumentDuplicateIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const userStore = useUserStore();
const authStore = useAuthStore();
const appStore = useAppStore();

const userId = computed(() => route.params.id ? parseInt(route.params.id) : null);
const loading = ref(false);
const saving = ref(false);
const departmentSearchQuery = ref('');

const departmentOptions = computed(() =>
  (appStore.departments || []).map((d) => ({ value: d.id, label: d.name }))
);

const roleOptions = [
  { value: 'admin', label: 'Administrador' },
  { value: 'requester', label: 'Requerente' },
  { value: 'validator', label: 'Validador' },
  { value: 'authorizer', label: 'Concedente' },
  { value: 'payer', label: 'Pagador' },
  { value: 'beneficiary', label: 'Beneficiário (servidor com acesso)' },
  { value: 'super-admin', label: 'Super Admin' },
];

const filteredDepartmentOptions = computed(() => {
  if (!departmentSearchQuery.value) return departmentOptions.value;
  const q = departmentSearchQuery.value.toLowerCase();
  return departmentOptions.value.filter((d) => d.label.toLowerCase().includes(q));
});

const selectedDepartments = computed(() => {
  return form.value.department_ids
    .map((id) => appStore.departments?.find((d) => d.id === id))
    .filter(Boolean);
});

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  operation_password: '',
  operation_pin: '',
  department_ids: [],
  primary_department_id: null,
  roles: [],
});

const APPROVER_ROLES = ['validator', 'authorizer', 'payer', 'admin', 'super-admin'];
const canUploadSignature = computed(() => {
  const roles = form.value.roles;
  return Array.isArray(roles) && roles.some((r) => APPROVER_ROLES.includes(r));
});
const signatureInputRef = ref(null);
const signatureFile = ref(null);
const signaturePreviewUrl = ref(null);

function onSignatureFileChange(e) {
  const file = e.target.files?.[0];
  signatureFile.value = file || null;
  if (signaturePreviewUrl.value) {
    URL.revokeObjectURL(signaturePreviewUrl.value);
    signaturePreviewUrl.value = null;
  }
  if (file) {
    signaturePreviewUrl.value = URL.createObjectURL(file);
  }
}

onMounted(async () => {
  if (authStore.user?.is_super_admin) {
    await appStore.fetchDepartments();
  }

  if (userId.value) {
    if (userId.value === authStore.user?.id) {
      toast.error('Use a página de Perfil para editar seus dados.');
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
      router.push({ name: 'users.index' });
      return;
    }

    form.value = {
      name: user.name ?? '',
      email: user.email ?? '',
      password: '',
      password_confirmation: '',
      operation_password: '',
      operation_pin: '',
      department_ids: user.department_ids ?? [],
      primary_department_id: user.primary_department_id ?? user.department_id ?? null,
      roles: Array.isArray(user.roles) ? user.roles : (user.role ? [user.role] : []),
    };
    signaturePreviewUrl.value = user.signature_url || null;
  } catch (error) {
    toast.error('Erro ao carregar usuário.');
    router.push({ name: 'users.index' });
  } finally {
    loading.value = false;
  }
}

function toggleDepartmentId(deptId) {
  const index = form.value.department_ids.indexOf(deptId);
  if (index > -1) {
    form.value.department_ids.splice(index, 1);
    if (form.value.primary_department_id === deptId) {
      form.value.primary_department_id = form.value.department_ids[0] || null;
    }
  } else {
    form.value.department_ids.push(deptId);
    if (form.value.department_ids.length === 1) {
      form.value.primary_department_id = deptId;
    }
  }
}

function removeDepartmentId(deptId) {
  const index = form.value.department_ids.indexOf(deptId);
  if (index > -1) {
    form.value.department_ids.splice(index, 1);
    if (form.value.primary_department_id === deptId) {
      form.value.primary_department_id = form.value.department_ids[0] || null;
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
  if (!form.value.roles || form.value.roles.length === 0) {
    toast.error('Selecione pelo menos um perfil.');
    return;
  }
  if (authStore.user?.is_super_admin && (!form.value.department_ids || form.value.department_ids.length === 0)) {
    toast.error('Selecione pelo menos uma secretaria.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      roles: form.value.roles,
    };

    if (authStore.user?.is_super_admin) {
      payload.department_ids = form.value.department_ids;
      payload.primary_department_id = form.value.primary_department_id || form.value.department_ids[0];
    }

    if (form.value.password) {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
    }

    if (form.value.operation_password && !userId.value) {
      payload.operation_password = form.value.operation_password;
    }

    if (form.value.roles && (form.value.roles.includes('admin') || form.value.roles.includes('super-admin'))) {
      payload.operation_pin = form.value.operation_pin?.trim() || null;
    }

    if (userId.value) {
      payload.operation_password = form.value.operation_password === '' ? null : (form.value.operation_password || null);
      if (Object.prototype.hasOwnProperty.call(payload, 'password') && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
      }
    }

    const hasSignature = signatureFile.value;
    if (hasSignature) {
      const formData = new FormData();
      Object.entries(payload).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          value.forEach((v) => formData.append(key + '[]', v));
        } else if (value != null && value !== '') {
          formData.append(key, value);
        }
      });
      formData.append('signature', signatureFile.value);

      if (userId.value) {
        await api.put(`/users/${userId.value}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success('Usuário atualizado com sucesso.');
      } else {
        await api.post('/users', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        toast.success('Usuário criado com sucesso.');
      }
    } else {
      if (userId.value) {
        await userStore.updateUser(userId.value, payload);
        toast.success('Usuário atualizado com sucesso.');
      } else {
        await userStore.createUser(payload);
        toast.success('Usuário criado com sucesso.');
      }
    }

    router.push({ name: 'users.index' });
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
