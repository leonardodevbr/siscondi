<template>
  <div class="space-y-6">
    <!-- Header (padrão do form de usuários) -->
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
        @click="$router.push({ name: 'servants.index' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar Servidor' : 'Novo Servidor' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize os dados do servidor' : 'Preencha os dados para criar um novo servidor' }}
        </p>
      </div>
    </div>

    <form class="card p-6" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Dados Pessoais -->
        <div class="lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <UserIcon class="h-5 w-5 text-slate-500" />
            Dados Pessoais
          </h3>
        </div>

        <div class="lg:col-span-2">
          <Input v-model="form.name" label="Nome Completo *" required placeholder="Nome completo" />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">CPF *</label>
          <input
            :value="form.cpf"
            type="text"
            required
            maxlength="14"
            placeholder="000.000.000-00"
            class="input-base w-full"
            @input="form.cpf = formatCpf($event.target.value)"
          />
        </div>

        <div>
          <Input v-model="form.rg" label="RG *" required />
        </div>

        <div>
          <Input v-model="form.organ_expeditor" label="Órgão Expeditor *" required placeholder="Ex: SSP/BA" />
        </div>

        <div>
          <Input v-model="form.matricula" label="Matrícula *" required />
        </div>

        <!-- Vínculo -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BriefcaseIcon class="h-5 w-5 text-slate-500" />
            Vínculo
          </h3>
        </div>

        <div>
          <SelectInput
            v-model="form.legislation_item_id"
            label="Cargo (item da legislação) *"
            :options="legislationItemOptions"
            placeholder="Selecione..."
            :searchable="legislationItemOptions.length > 10"
          />
        </div>

        <div>
          <SelectInput
            v-model="form.department_id"
            label="Secretaria (Lotação) *"
            :options="departmentOptions"
            placeholder="Selecione..."
            :searchable="departmentOptions.length > 10"
          />
        </div>

        <!-- Dados Bancários -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <BanknotesIcon class="h-5 w-5 text-slate-500" />
            Dados Bancários
          </h3>
        </div>

        <div class="lg:col-span-2">
          <Input v-model="form.bank_name" label="Banco" placeholder="Nome do banco" />
        </div>

        <div>
          <Input v-model="form.agency_number" label="Agência" placeholder="Ex: 1696-9" />
        </div>

        <div>
          <Input v-model="form.account_number" label="Conta" placeholder="Ex: 35038-9" />
        </div>

        <div>
          <SelectInput
            v-model="form.account_type"
            label="Tipo de Conta"
            :options="accountTypeOptions"
            placeholder="Selecione..."
            :searchable="false"
          />
        </div>

        <!-- Contato -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <EnvelopeIcon class="h-5 w-5 text-slate-500" />
            Contato
          </h3>
        </div>

        <div>
          <Input v-model="form.email" label="E-mail" type="email" placeholder="email@exemplo.gov.br" />
        </div>

        <div>
          <Input v-model="form.phone" label="Telefone" placeholder="(00) 00000-0000" />
        </div>

        <div class="lg:col-span-2 pt-2">
          <Toggle v-model="form.is_active" label="Servidor Ativo" />
        </div>
      </div>

      <!-- Actions (padrão do form de usuários) -->
      <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-200">
        <Button type="button" variant="outline" @click="$router.push({ name: 'servants.index' })">
          Cancelar
        </Button>
        <Button type="submit" :loading="saving">
          {{ isEdit ? 'Atualizar Servidor' : 'Criar Servidor' }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { formatCpf } from '@/utils/format'
import { useAlert } from '@/composables/useAlert'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import SelectInput from '@/components/Common/SelectInput.vue'
import Toggle from '@/components/Common/Toggle.vue'
import {
  ArrowLeftIcon,
  UserIcon,
  BriefcaseIcon,
  BanknotesIcon,
  EnvelopeIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = ref(false)
const saving = ref(false)

const form = ref({
  name: '',
  cpf: '',
  rg: '',
  organ_expeditor: '',
  matricula: '',
  legislation_item_id: null,
  department_id: null,
  bank_name: '',
  agency_number: '',
  account_number: '',
  account_type: null,
  email: '',
  phone: '',
  is_active: true
})

const accountTypeOptions = [
  { value: 'corrente', label: 'Corrente' },
  { value: 'poupanca', label: 'Poupança' },
]

const legislations = ref([])
const departments = ref([])

const legislationItemOptions = computed(() => {
  const list = []
  for (const leg of legislations.value) {
    const items = leg.items || []
    for (const item of items) {
      list.push({
        value: item.id,
        label: `${item.functional_category} (${item.daily_class})`
      })
    }
  }
  return list
})

const departmentOptions = computed(() =>
  (departments.value || []).map((d) => ({ value: d.id, label: d.name }))
)

const fetchData = async () => {
  try {
    const [legData, deptData] = await Promise.all([
      api.get('/legislations?all=1'),
      api.get('/departments?all=1')
    ])
    legislations.value = legData.data.data || legData.data
    departments.value = deptData.data.data || deptData.data
  } catch (error) {
    console.error('Erro ao carregar dados:', error)
  }
}

const fetchServant = async () => {
  try {
    const { data } = await api.get(`/servants/${route.params.id}`)
    const payload = data.data || data
    form.value = {
      ...form.value,
      ...payload,
      legislation_item_id: payload.legislation_item_id ?? null,
      department_id: payload.department_id ?? null,
      account_type: payload.account_type ?? null,
    }
  } catch (error) {
    console.error('Erro ao carregar servidor:', error)
  }
}

const handleSubmit = async () => {
  saving.value = true
  try {
    const payload = { ...form.value }
    payload.cpf = (form.value.cpf || '').replace(/\D/g, '').slice(0, 11)
    if (isEdit.value) {
      await api.put(`/servants/${route.params.id}`, payload)
    } else {
      await api.post('/servants', payload)
    }
    await success('Salvo', 'Servidor salvo com sucesso.')
    router.push({ name: 'servants.index' })
  } catch (error) {
    console.error('Erro ao salvar:', error)
    showError('Erro', 'Erro ao salvar servidor.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchData()
  if (route.params.id) {
    isEdit.value = true
    await fetchServant()
  }
})
</script>
