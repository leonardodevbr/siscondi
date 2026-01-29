<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">
      {{ isEdit ? 'Editar Servidor' : 'Novo Servidor' }}
    </h1>

    <form @submit.prevent="handleSubmit" class="bg-white rounded-lg shadow p-6 space-y-6">
      <!-- Dados Pessoais -->
      <div class="border-b pb-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Dados Pessoais</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700">Nome Completo *</label>
            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">CPF * (apenas números)</label>
            <input v-model="form.cpf" type="text" required maxlength="11" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">RG *</label>
            <input v-model="form.rg" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Órgão Expeditor *</label>
            <input v-model="form.organ_expeditor" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Matrícula *</label>
            <input v-model="form.matricula" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
        </div>
      </div>

      <!-- Vínculo -->
      <div class="border-b pb-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Vínculo</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Cargo (item da legislação) *</label>
            <select v-model="form.legislation_item_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">Selecione...</option>
              <option v-for="opt in legislationItemOptions" :key="opt.id" :value="opt.id">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Secretaria (Lotação) *</label>
            <select v-model="form.department_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">Selecione...</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                {{ dept.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Dados Bancários -->
      <div class="border-b pb-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Dados Bancários</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700">Banco</label>
            <input v-model="form.bank_name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Agência</label>
            <input v-model="form.agency_number" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Conta</label>
            <input v-model="form.account_number" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de Conta</label>
            <select v-model="form.account_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">Selecione...</option>
              <option value="corrente">Corrente</option>
              <option value="poupanca">Poupança</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Contato -->
      <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Contato</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">E-mail</label>
            <input v-model="form.email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Telefone</label>
            <input v-model="form.phone" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          </div>
        </div>
      </div>

      <div class="pt-4">
        <Toggle v-model="form.is_active" label="Servidor Ativo" />
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Salvar</button>
        <router-link to="/servants" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import Toggle from '@/components/Common/Toggle.vue'

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = ref(false)

const form = ref({
  name: '',
  cpf: '',
  rg: '',
  organ_expeditor: '',
  matricula: '',
  legislation_item_id: '',
  department_id: '',
  bank_name: '',
  agency_number: '',
  account_number: '',
  account_type: '',
  email: '',
  phone: '',
  is_active: true
})

const legislations = ref([])
const departments = ref([])

const legislationItemOptions = computed(() => {
  const list = []
  for (const leg of legislations.value) {
    const items = leg.items || []
    for (const item of items) {
    list.push({
      id: item.id,
      label: `${leg.title} – ${item.functional_category} (${item.daily_class})`
    })
    }
  }
  return list
})

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
    form.value = { ...form.value, ...payload, legislation_item_id: payload.legislation_item_id }
  } catch (error) {
    console.error('Erro ao carregar servidor:', error)
  }
}

const handleSubmit = async () => {
  try {
    if (isEdit.value) {
      await api.put(`/servants/${route.params.id}`, form.value)
    } else {
      await api.post('/servants', form.value)
    }
    await success('Salvo', 'Servidor salvo com sucesso.')
    router.push('/servants')
  } catch (error) {
    console.error('Erro ao salvar:', error)
    showError('Erro', 'Erro ao salvar servidor.')
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
