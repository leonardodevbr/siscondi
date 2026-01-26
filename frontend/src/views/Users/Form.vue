<template>
  <form class="space-y-4" @submit.prevent="submit">
    <Input v-model="form.name" label="Nome" required />
    <Input v-model="form.email" label="E-mail" type="email" required />
    <div v-if="!user">
      <Input v-model="form.password" label="Senha (login)" type="password" required autocomplete="new-password" />
      <Input v-model="form.password_confirmation" label="Confirmação da senha" type="password" required autocomplete="new-password" />
    </div>
    <div v-else>
      <Input v-model="form.password" label="Nova senha (deixe em branco para não alterar)" type="password" autocomplete="new-password" />
      <Input v-model="form.password_confirmation" label="Confirmação da nova senha" type="password" autocomplete="new-password" />
    </div>
    <Input v-model="form.operation_password" label="Senha de operação (opcional)" type="password" autocomplete="off" />
    <SelectInput
      v-if="authStore.user?.is_super_admin"
      v-model="form.branch_id"
      label="Filial"
      :options="branchOptions"
      placeholder="Selecione a filial"
    />
    <SelectInput v-model="form.role" label="Cargo" :options="roleOptions" placeholder="Selecione o cargo" />
    <div class="flex justify-end gap-2 pt-4">
      <Button type="button" variant="outline" @click="$emit('close')">Cancelar</Button>
      <Button type="submit" :loading="saving">{{ user ? 'Atualizar' : 'Criar' }} usuário</Button>
    </div>
  </form>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import Input from '@/components/Common/Input.vue';
import Button from '@/components/Common/Button.vue';
import SelectInput from '@/components/Common/SelectInput.vue';

const roleOptions = [
  { value: 'seller', label: 'Vendedor' },
  { value: 'stockist', label: 'Estoquista' },
  { value: 'manager', label: 'Gerente' },
  { value: 'super-admin', label: 'Super Admin' },
];

const props = defineProps({
  user: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const toast = useToast();
const userStore = useUserStore();
const authStore = useAuthStore();
const appStore = useAppStore();
const saving = ref(false);

const branchOptions = computed(() =>
  (appStore.branches || []).map((b) => ({ value: b.id, label: b.name }))
);

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  operation_password: '',
  branch_id: null,
  role: null,
});

onMounted(() => {
  if (authStore.user?.is_super_admin) {
    appStore.fetchBranches();
  }
});

watch(
  () => props.user,
  (u) => {
    if (u) {
      form.value = {
        name: u.name ?? '',
        email: u.email ?? '',
        password: '',
        password_confirmation: '',
        operation_password: '',
        branch_id: u.branch_id ?? u.branch?.id ?? null,
        role: u.role ?? null,
      };
    } else {
      form.value = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        operation_password: '',
        branch_id: authStore.user?.is_super_admin ? (appStore.currentBranch?.id ?? null) : null,
        role: null,
      };
    }
  },
  { immediate: true }
);

async function submit() {
  if (!form.value.name || !form.value.email) {
    toast.error('Nome e e-mail são obrigatórios.');
    return;
  }
  if (!props.user && (!form.value.password || form.value.password !== form.value.password_confirmation)) {
    toast.error('Senha e confirmação devem ser iguais.');
    return;
  }
  if (props.user && form.value.password && form.value.password !== form.value.password_confirmation) {
    toast.error('Nova senha e confirmação devem ser iguais.');
    return;
  }
  if (!form.value.role) {
    toast.error('Selecione o cargo.');
    return;
  }
  if (authStore.user?.is_super_admin && !form.value.branch_id) {
    toast.error('Selecione a filial.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
    };
    if (authStore.user?.is_super_admin && form.value.branch_id) {
      payload.branch_id = form.value.branch_id;
    }
    if (form.value.password) {
      payload.password = form.value.password;
      payload.password_confirmation = form.value.password_confirmation;
    }
    if (form.value.operation_password && !props.user) {
      payload.operation_password = form.value.operation_password;
    }
    if (props.user) {
      payload.operation_password = form.value.operation_password === '' ? null : (form.value.operation_password || null);
      if (Object.prototype.hasOwnProperty.call(payload, 'password') && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
      }
      await userStore.updateUser(props.user.id, payload);
      toast.success('Usuário atualizado com sucesso.');
    } else {
      await userStore.createUser(payload);
      toast.success('Usuário criado com sucesso.');
    }
    emit('saved');
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
