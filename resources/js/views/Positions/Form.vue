<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
        @click="$router.push({ name: 'positions.index' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar Cargo' : 'Novo Cargo' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize o cargo e os itens da lei vinculados' : 'Cadastre o símbolo do cargo e vincule aos itens da legislação' }}
        </p>
      </div>
    </div>

    <form class="card p-6" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BriefcaseIcon class="h-5 w-5 text-slate-500" />
            Dados do Cargo
          </h3>
        </div>

        <div>
          <Input v-model="form.name" label="Nome *" required placeholder="Ex: Secretário Municipal" />
        </div>

        <div>
          <Input v-model="form.symbol" label="Símbolo *" required placeholder="Ex: 101, 201" />
        </div>

        <div v-if="isSuperAdmin" class="lg:col-span-2">
          <SelectInput
            v-model="form.municipality_id"
            label="Município"
            :options="municipalityOptions"
            placeholder="Selecione o município"
            :searchable="municipalityOptions.length > 10"
          />
        </div>

        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <ShieldCheckIcon class="h-5 w-5 text-slate-500" />
            Perfil (role)
          </h3>
          <p class="text-xs text-slate-500 mb-3">Vincule este cargo ao perfil de acesso no sistema. O usuário que receber este cargo terá as permissões do perfil escolhido.</p>
          <SelectInput
            v-model="form.role"
            label="Perfil no sistema"
            :options="roleOptions"
            placeholder="Selecione o perfil"
            :searchable="false"
          />
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
        <Button type="button" variant="outline" @click="$router.push({ name: 'positions.index' })">
          Cancelar
        </Button>
        <Button type="submit" :loading="saving">
          {{ isEdit ? 'Atualizar Cargo' : 'Criar Cargo' }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAlert } from '@/composables/useAlert'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import SelectInput from '@/components/Common/SelectInput.vue'
import { ArrowLeftIcon, BriefcaseIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { success, error: showError } = useAlert()
const isEdit = ref(false)
const saving = ref(false)

const isSuperAdmin = computed(() => authStore.hasRole('super-admin'))

const roles = ref([])
const roleLabels = {
  admin: 'Administrador',
  requester: 'Requerente',
  validator: 'Validador',
  authorizer: 'Concedente',
  payer: 'Pagador',
  beneficiary: 'Beneficiário',
  'super-admin': 'Super Admin',
}
const roleOptions = computed(() =>
  (roles.value || []).map((r) => ({
    value: r.name,
    label: roleLabels[r.name] ?? r.name,
  }))
)

const form = ref({
  name: '',
  symbol: '',
  municipality_id: null,
  role: null
})

const municipalities = ref([])

const municipalityOptions = computed(() =>
  (municipalities.value || []).map((m) => ({ value: m.id, label: m.name }))
)

const fetchData = async () => {
  try {
    const [municipalitiesRes, rolesRes] = await Promise.all([
      isSuperAdmin.value ? api.get('/municipalities?all=1') : Promise.resolve({ data: {} }),
      api.get('/config/roles'),
    ])
    if (isSuperAdmin.value) {
      municipalities.value = municipalitiesRes.data?.data ?? municipalitiesRes.data ?? []
    }
    roles.value = rolesRes.data?.data ?? rolesRes.data ?? []
  } catch (e) {
    console.error(e)
  }
}

const fetchPosition = async () => {
  try {
    const { data } = await api.get(`/positions/${route.params.id}`)
    const payload = data.data ?? data
    form.value = {
      symbol: payload.symbol ?? '',
      name: payload.name ?? '',
      municipality_id: payload.municipality_id ?? null,
      role: payload.role ?? null,
      legislation_item_ids: payload.legislation_item_ids ?? []
    }
  } catch (e) {
    console.error(e)
    showError('Erro', 'Não foi possível carregar o cargo.')
  }
}

const handleSubmit = async () => {
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      symbol: form.value.symbol,
      municipality_id: form.value.municipality_id || undefined,
      role: form.value.role || undefined
    }
    if (isEdit.value) {
      await api.put(`/positions/${route.params.id}`, payload)
    } else {
      await api.post('/positions', payload)
    }
    await success('Salvo', 'Cargo salvo com sucesso.')
    router.push({ name: 'positions.index' })
  } catch (e) {
    const msg = e.response?.data?.message ?? e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : 'Erro ao salvar cargo.'
    showError('Erro', msg)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchData()
  if (route.params.id) {
    isEdit.value = true
    await fetchPosition()
  }
})
</script>
