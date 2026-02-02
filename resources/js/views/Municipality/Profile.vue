<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Dados do município</h2>
      <p class="text-xs text-slate-500">Dados do seu município. Apenas perfil admin.</p>
    </div>
    <div v-if="loading" class="py-12 text-center text-slate-500">Carregando...</div>
    <div v-else-if="!municipality" class="p-6 rounded-lg border border-slate-200 bg-slate-50 text-slate-600 text-center">
      Nenhum município vinculado. Defina uma secretaria principal no seu perfil.
    </div>
    <div v-else class="card p-6">
      <form @submit.prevent="save" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
          <input v-model="form.name" type="text" class="input-base w-full" required />
          <p class="mt-0.5 text-xs text-slate-500">Nome oficial do município (ex: Cafarnaum)</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nome de exibição</label>
          <input v-model="form.display_name" type="text" class="input-base w-full" placeholder="Ex: Prefeitura Municipal de Cafarnaum" />
          <p class="mt-0.5 text-xs text-slate-500">Nome completo para exibição em documentos e relatórios</p>
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
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">UF</label>
            <input v-model="form.state" type="text" class="input-base w-full" maxlength="2" placeholder="BA" />
            <p class="mt-0.5 text-xs text-slate-500">Sigla (ex: BA)</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Estado de exibição</label>
            <input v-model="form.display_state" type="text" class="input-base w-full" placeholder="Bahia" />
            <p class="mt-0.5 text-xs text-slate-500">Nome completo (ex: Bahia)</p>
          </div>
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
            :entity-id="municipality?.id ?? ''"
            label="Brasão / Logo"
            size-class="h-32 w-32 min-h-[120px]"
          />
        </div>
        <div class="pt-4 flex justify-end">
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ saving ? 'Salvando...' : 'Salvar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { formatCnpj } from '@/utils/format';
import LogoUpload from '@/components/Common/LogoUpload.vue';
const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const municipality = ref(null);
const form = reactive({ name: '', display_name: '', cnpj: '', state: '', display_state: '', address: '', email: '', logo_path: '' });
const load = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/municipalities/current');
    const payload = data?.data ?? data ?? null;
    municipality.value = payload;
    if (payload) {
      form.name = payload.name ?? '';
      form.display_name = payload.display_name ?? '';
      form.cnpj = formatCnpj(payload.cnpj ?? '');
      form.state = payload.state ?? '';
      form.display_state = payload.display_state ?? '';
      form.address = payload.address ?? '';
      form.email = payload.email ?? '';
      form.logo_path = payload.logo_path ?? '';
    }
  } catch (e) {
    if (e.response?.status === 404) municipality.value = null;
    else toast.error('Erro ao carregar.');
  } finally {
    loading.value = false;
  }
};
const save = async () => {
  saving.value = true;
  try {
    await api.post('/municipalities/current/update', form);
    toast.success('Dados salvos.');
    load();
  } catch (e) {
    toast.error(e.response?.data?.message ?? 'Erro ao salvar.');
  } finally {
    saving.value = false;
  }
};
onMounted(() => load());
</script>
