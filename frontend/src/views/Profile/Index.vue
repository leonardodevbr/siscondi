<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Meu Perfil</h2>
      <p class="text-xs text-slate-500">Gerencie suas informações pessoais e configurações</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="card p-12 text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-blue-600"></div>
      <p class="text-slate-500 mt-3">Carregando...</p>
    </div>

    <!-- Profile Form -->
    <form v-else class="card p-6" @submit.prevent="submit">
      <div class="space-y-6">
        <!-- Informações Pessoais -->
        <div>
          <h3 class="text-sm font-semibold text-slate-800 mb-4">Informações Pessoais</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input v-model="form.name" label="Nome Completo" required />
            <Input v-model="form.email" label="E-mail" type="email" required />
          </div>
        </div>

        <!-- Alterar Senha -->
        <div class="border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4">Alterar Senha</h3>
          <p class="text-xs text-slate-500 mb-4">Deixe em branco se não quiser alterar</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input
              v-model="form.password"
              label="Nova Senha"
              type="password"
              autocomplete="new-password"
              placeholder="Mínimo 8 caracteres"
            />
            <Input
              v-model="form.password_confirmation"
              label="Confirmar Nova Senha"
              type="password"
              autocomplete="new-password"
              placeholder="Repita a nova senha"
            />
          </div>
        </div>

        <!-- Informações do Sistema (Read-only) -->
        <div class="border-t pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4">Informações do Sistema</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Cargo</label>
              <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700">
                {{ roleLabel }}
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Filial Principal</label>
              <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700">
                {{ user?.branch?.name ?? '—' }}
              </div>
            </div>
          </div>
          
          <!-- Múltiplas Filiais -->
          <div v-if="user?.branch_ids && user.branch_ids.length > 1" class="mt-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">Filiais com Acesso</label>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="branchId in user.branch_ids"
                :key="branchId"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-full text-xs font-medium text-blue-700"
              >
                {{ getBranchName(branchId) }}
                <span v-if="user.primary_branch_id === branchId" class="text-xs text-blue-600">(Principal)</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-6 border-t">
          <Button type="submit" :loading="saving">
            Salvar Alterações
          </Button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import { useUserStore } from '@/stores/user';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';

const ROLE_LABELS = {
  seller: 'Vendedor(a)',
  stockist: 'Estoquista',
  manager: 'Gerente',
  owner: 'Gestor(a) Geral',
  'super-admin': 'Super Admin',
};

const toast = useToast();
const authStore = useAuthStore();
const appStore = useAppStore();
const userStore = useUserStore();

const loading = ref(false);
const saving = ref(false);
const user = ref(null);

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const roleLabel = computed(() => {
  return user.value?.role ? (ROLE_LABELS[user.value.role] ?? user.value.role) : '—';
});

function getBranchName(branchId) {
  const branch = appStore.branches?.find(b => b.id === branchId);
  return branch?.name ?? `Filial #${branchId}`;
}

onMounted(async () => {
  await loadProfile();
  if (appStore.branches.length === 0) {
    await appStore.fetchBranches();
  }
});

async function loadProfile() {
  loading.value = true;
  try {
    user.value = await userStore.fetchUser(authStore.user.id);
    form.value.name = user.value.name;
    form.value.email = user.value.email;
  } catch (error) {
    toast.error('Erro ao carregar perfil.');
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!form.value.name || !form.value.email) {
    toast.error('Nome e e-mail são obrigatórios.');
    return;
  }

  if (form.value.password && form.value.password !== form.value.password_confirmation) {
    toast.error('Nova senha e confirmação devem ser iguais.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
    };

    if (form.value.password) {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
    }

    await userStore.updateUser(authStore.user.id, payload);
    
    // Atualiza dados do authStore
    authStore.user.name = form.value.name;
    authStore.user.email = form.value.email;
    
    toast.success('Perfil atualizado com sucesso.');
    
    // Limpa campos de senha
    form.value.password = '';
    form.value.password_confirmation = '';
    
    // Recarrega perfil
    await loadProfile();
  } catch (err) {
    const msg = err.response?.data?.message ?? err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : 'Erro ao atualizar perfil.';
    toast.error(msg);
  } finally {
    saving.value = false;
  }
}
</script>
