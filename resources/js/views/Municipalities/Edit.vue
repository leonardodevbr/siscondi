<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Editar município</h2>
      <p class="text-xs text-slate-500">Super Admin. Edição de município.</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <p class="text-slate-500">Carregando...</p>
    </div>
    <div v-else class="space-y-4">
      <form @submit.prevent="save" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
          <input v-model="form.name" type="text" class="input-base w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nome para exibição em documentos</label>
          <input v-model="form.display_name" placeholder="Ex: Prefeitura Municipal de Cafarnaum" type="text" class="input-base w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">CNPJ</label>
          <input
            :value="form.cnpj"
            type="text"
            class="input-base w-full"
            maxlength="18"
            placeholder="00.000.000/0001-00"
            @input="form.cnpj = formatCnpj($event.target.value)"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">UF</label>
          <input v-model="form.state" type="text" class="input-base w-full max-w-[4rem]" maxlength="2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nome para exibição em documentos</label>
          <input v-model="form.display_state" placeholder="Ex: Estado da Bahia" type="text" class="input-base w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Endereço</label>
          <input v-model="form.address" type="text" class="input-base w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
          <input v-model="form.email" type="email" class="input-base w-full" />
        </div>
        <div>
          <LogoUpload
            v-model="form.logo_path"
            type="municipality"
            :entity-id="id"
            label="Brasão / Logo"
            size-class="h-32 w-32 min-h-[120px]"
          />
        </div>
        <div class="flex justify-end gap-2 pt-4">
          <router-link
            :to="{ name: 'municipalities.index' }"
            class="px-4 py-2 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors"
          >
            Voltar
          </router-link>
          <button
            type="submit"
            :disabled="saving"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="saving">Salvando...</span>
            <span v-else>Salvar</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { formatCnpj } from '@/utils/format';
import LogoUpload from '@/components/Common/LogoUpload.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const id = computed(() => route.params.id);
const form = reactive({
  name: '',
  display_name: '',
  cnpj: '',
  state: '',
  display_state: '',
  address: '',
  email: '',
  logo_path: '',
});

const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get(`/municipalities/${id.value}`);
    const m = data?.data ?? data ?? {};
    form.name = m.name ?? '';
    form.display_name = m.display_name ?? '';
    form.cnpj = formatCnpj(m.cnpj ?? '');
    form.state = m.state ?? '';
    form.display_state = m.display_state ?? '';
    form.address = m.address ?? '';
    form.email = m.email ?? '';
    form.logo_path = m.logo_path ?? '';
  } catch (error) {
    toast.error('Erro ao carregar município.');
  } finally {
    loading.value = false;
  }
};

const save = async () => {
  saving.value = true;
  try {
    await api.post(`/municipalities/${id.value}/update`, form);
    toast.success('Município atualizado.');
    router.push({ name: 'municipalities.index' });
  } catch (error) {
    toast.error(error.response?.data?.message ?? 'Erro ao salvar.');
  } finally {
    saving.value = false;
  }
};

onMounted(() => load());
</script>
