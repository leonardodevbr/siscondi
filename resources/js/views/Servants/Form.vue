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
            <label class="block text-sm font-medium text-gray-700">Cargo *</label>
            <select v-model="form.legislation_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
              <option value="">Selecione...</option>
              <option v-for="leg in legislations" :key="leg.id" :value="leg.id">
                {{ leg.code }} - {{ leg.title }}
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

      <div class="flex items-center pt-4">
        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
        <label class="ml-2 block text-sm text-gray-900">Servidor Ativo</label>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Salvar</button>
        <router-link to="/servants" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const isEdit = ref(false)

const form = ref({
  name: '',
  cpf: '',
  rg: '',
  organ_expeditor: '',
  matricula: '',
  legislation_id: '',
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

const fetchData = async () => {
  try {
    const [legData, deptData] = await Promise.all([
      axios.get('/api/legislations?all=1'),
      axios.get('/api/branches?all=1')
    ])
    legislations.value = legData.data.data || legData.data
    departments.value = deptData.data.data || deptData.data
  } catch (error) {
    console.error('Erro ao carregar dados:', error)
  }
}

const fetchServant = async () => {
  try {
    const { data } = await axios.get(`/api/servants/${route.params.id}`)
    form.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar servidor:', error)
  }
}

const handleSubmit = async () => {
  try {
    if (isEdit.value) {
      await axios.put(`/api/servants/${route.params.id}`, form.value)
    } else {
      await axios.post('/api/servants', form.value)
    }
    router.push('/servants')
  } catch (error) {
    console.error('Erro ao salvar:', error)
    alert('Erro ao salvar servidor')
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
