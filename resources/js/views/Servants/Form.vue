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
      <!-- Erros de validação da API -->
      <div
        v-if="formErrors && Object.keys(formErrors).length > 0"
        class="mb-6 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm"
      >
        <p class="font-medium mb-1">Corrija os erros abaixo:</p>
        <ul class="list-disc list-inside space-y-0.5">
          <li v-for="(msgs, field) in (formErrors || {})" :key="field">
            <template v-for="(msg, i) in (Array.isArray(msgs) ? msgs : [msgs])" :key="i">{{ msg }}</template>
          </li>
        </ul>
      </div>

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
            v-model="form.department_id"
            label="Secretaria (Lotação) *"
            :options="departmentOptions"
            placeholder="Selecione..."
            :searchable="departmentOptions.length > 10"
          />
        </div>

        <div>
          <SelectInput
            v-model="form.cargo_id"
            label="Cargo *"
            :options="cargoOptions"
            placeholder="Selecione um cargo"
            :searchable="cargoOptions.length > 10"
            mode="single"
          />
        </div>

        <div class="lg:col-span-2">
          <p class="text-xs text-slate-500 -mt-2">Vincule o cargo ao servidor. Os cargos são definidos no cadastro de Cargos e vinculados aos itens da legislação.</p>
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

        <!-- Senha apenas no cadastro: gera usuário de acesso com o mesmo e-mail -->
        <template v-if="!isEdit">
          <div>
            <Input
              v-model="form.password"
              label="Senha (acesso ao sistema)"
              type="password"
              :required="!!form.email"
              autocomplete="new-password"
              placeholder="Preencha se quiser criar usuário de acesso"
              :error="(Array.isArray(formErrors?.password) ? formErrors.password[0] : formErrors?.password) || ''"
            />
            <p class="mt-1 text-xs text-slate-500">Se o e-mail for preenchido, um usuário será criado com este e-mail e esta senha.</p>
          </div>
          <div>
            <Input
              v-model="form.password_confirmation"
              label="Confirmação da Senha"
              type="password"
              :required="!!form.email"
              autocomplete="new-password"
              placeholder="Repita a senha"
              :error="(Array.isArray(formErrors?.password_confirmation) ? formErrors.password_confirmation[0] : formErrors?.password_confirmation) || ''"
            />
          </div>
        </template>

        <div class="lg:col-span-2 pt-2">
          <Toggle v-model="form.is_active" label="Servidor Ativo" />
        </div>

        <!-- Dados de acesso ao sistema (usuário vinculado) -->
        <div v-if="linkedUser" class="lg:col-span-2 border-t border-slate-200 pt-6">
          <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
              <KeyIcon class="h-5 w-5 text-slate-500" />
              Dados de acesso ao sistema
            </h3>
            <router-link
              :to="{ name: 'users.edit', params: { id: linkedUser.id } }"
              class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-800"
            >
              <PencilSquareIcon class="h-4 w-4" />
              Editar usuário
            </router-link>
          </div>
          <p class="text-xs text-slate-500 mb-4">Este servidor está vinculado a um usuário e pode acessar o sistema.</p>
          <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">E-mail (login)</span>
              <span class="block text-sm text-slate-900 mt-0.5">{{ linkedUser.email }}</span>
            </div>
            <div>
              <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">Perfil</span>
              <span class="block text-sm text-slate-900 mt-0.5">{{ roleLabel(linkedUser.role) }}</span>
            </div>
            <div v-if="linkedUser.department" class="md:col-span-2">
              <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">Secretaria principal</span>
              <span class="block text-sm text-slate-900 mt-0.5">{{ linkedUser.department.name }}</span>
            </div>
            <div v-if="linkedUser.departments && linkedUser.departments.length" class="md:col-span-2">
              <span class="block text-xs font-medium text-slate-500 uppercase tracking-wider">Secretarias com acesso</span>
              <div class="flex flex-wrap gap-1.5 mt-1.5">
                <span
                  v-for="d in linkedUser.departments"
                  :key="d.id"
                  class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-slate-200 text-slate-700"
                >
                  {{ d.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
        <div v-else-if="isEdit" class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <KeyIcon class="h-5 w-5 text-slate-500" />
            Dados de acesso ao sistema
          </h3>
          <p class="text-sm text-slate-500">Este servidor não está vinculado a nenhum usuário. Não possui acesso ao sistema.</p>
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
  KeyIcon,
  PencilSquareIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = ref(false)
const saving = ref(false)
const linkedUser = ref(null)
const formErrors = ref({})

const form = ref({
  name: '',
  cpf: '',
  rg: '',
  organ_expeditor: '',
  matricula: '',
  legislation_item_id: null,
  department_id: null,
  cargo_id: null,
  bank_name: '',
  agency_number: '',
  account_number: '',
  account_type: null,
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  is_active: true
})

const accountTypeOptions = [
  { value: 'corrente', label: 'Corrente' },
  { value: 'poupanca', label: 'Poupança' },
]

const departments = ref([])
const cargos = ref([])

const departmentOptions = computed(() =>
  (departments.value || []).map((d) => ({ value: d.id, label: d.name }))
)

const cargoOptions = computed(() =>
  (cargos.value || []).map((c) => ({ value: c.id, label: c.name ? `${c.name} (${c.symbol})` : c.symbol }))
)

const fetchData = async () => {
  try {
    const [deptData, cargosData] = await Promise.all([
      api.get('/departments?all=1'),
      api.get('/cargos?all=1')
    ])
    departments.value = deptData.data?.data ?? deptData.data ?? []
    cargos.value = cargosData.data?.data ?? cargosData.data ?? []
  } catch (error) {
    console.error('Erro ao carregar dados:', error)
  }
}

const roleLabels = {
  admin: 'Administrador',
  requester: 'Requerente',
  validator: 'Validador',
  authorizer: 'Concedente',
  payer: 'Pagador',
  beneficiary: 'Beneficiário',
  'super-admin': 'Super Admin',
}

function roleLabel(role) {
  return role ? (roleLabels[role] || role) : '–'
}

const fetchServant = async () => {
  try {
    const { data } = await api.get(`/servants/${route.params.id}`)
    const payload = data.data || data
    form.value = {
      ...form.value,
      ...payload,
      department_id: payload.department_id ?? null,
      account_type: payload.account_type ?? null,
      cargo_id: payload.cargo_id ?? null
    }
    linkedUser.value = payload.user || null
  } catch (error) {
    console.error('Erro ao carregar servidor:', error)
  }
}

const handleSubmit = async () => {
  formErrors.value = {}
  if (!form.value.cargo_id) {
    showError('Erro', 'Selecione o cargo.')
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value }
    payload.cpf = (form.value.cpf || '').replace(/\D/g, '').slice(0, 11)
    if (isEdit.value) {
      delete payload.password
      delete payload.password_confirmation
      await api.put(`/servants/${route.params.id}`, payload)
    } else {
      await api.post('/servants', payload)
    }
    await success('Salvo', 'Servidor salvo com sucesso.')
    router.push({ name: 'servants.index' })
  } catch (error) {
    const data = error.response?.data
    if (data?.errors && typeof data.errors === 'object') {
      formErrors.value = {}
      for (const [key, val] of Object.entries(data.errors)) {
        formErrors.value[key] = Array.isArray(val) ? val : [val]
      }
    }
    const msg = data?.message ?? (data?.errors ? Object.values(data.errors).flat().join(' ') : null) ?? 'Erro ao salvar servidor.'
    showError('Erro', msg)
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
