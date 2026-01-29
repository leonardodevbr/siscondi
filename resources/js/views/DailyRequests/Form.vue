<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Nova Solicitação de Diária</h1>

    <form @submit.prevent="handleSubmit" class="bg-white rounded-lg shadow p-6 space-y-6">
      <!-- Servidor -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Servidor *</label>
        <select v-model="form.servant_id" @change="onServantChange" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
          <option value="">Selecione o servidor...</option>
          <option v-for="servant in servants" :key="servant.id" :value="servant.id">
            {{ servant.matricula }} - {{ servant.name }} ({{ servant.legislation_item ? servant.legislation_item.functional_category : '' }})
          </option>
        </select>
      </div>

      <!-- Tipo de Destino (define o valor da diária; opções vêm da lei do servidor) -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Destino *</label>
        <select v-model="form.destination_type" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
          <option value="">Selecione...</option>
          <option v-for="label in destinationLabels" :key="label" :value="label">{{ label }}</option>
        </select>
        <p v-if="selectedServant && form.destination_type" class="mt-2 text-sm text-gray-600">
          Valor da diária para este destino: <span class="font-semibold">{{ formatCurrency(unitValueForDestination) }}</span>
        </p>
      </div>

      <!-- Destino -->
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700">Cidade de Destino *</label>
          <input v-model="form.destination_city" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Estado *</label>
          <input v-model="form.destination_state" type="text" required maxlength="2" placeholder="UF" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Período -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Data de Saída *</label>
          <input v-model="form.departure_date" @change="calculateDays" type="date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Data de Retorno *</label>
          <input v-model="form.return_date" @change="calculateDays" type="date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Quantidade de Diárias -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Quantidade de Diárias *</label>
          <input v-model.number="form.quantity_days" type="number" step="0.5" min="0.5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
          <p class="mt-1 text-xs text-gray-500">Aceita meia diária (ex: 2.5)</p>
        </div>
        <div class="flex items-end">
          <div class="w-full">
            <label class="block text-sm font-medium text-gray-700">Valor Total Previsto</label>
            <div class="mt-1 p-2 bg-gray-50 rounded-md text-lg font-semibold text-green-600">
              {{ formatCurrency(calculatedTotal) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Motivo -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Motivo da Viagem *</label>
        <textarea v-model="form.reason" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Enviar Solicitação</button>
        <router-link to="/daily-requests" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancelar</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency } from '@/utils/format'

const router = useRouter()
const { success, error: showError } = useAlert()

const form = ref({
  servant_id: '',
  destination_type: '',
  destination_city: '',
  destination_state: '',
  departure_date: '',
  return_date: '',
  quantity_days: 1,
  reason: ''
})

const servants = ref([])
const selectedServant = computed(() =>
  servants.value.find(s => s.id === form.value.servant_id)
)

/** Labels de destino definidos na legislação do servidor selecionado */
const destinationLabels = computed(() => {
  const servant = selectedServant.value
  const values = servant?.legislation_item?.values
  if (!values || typeof values !== 'object') return []
  return Object.keys(values)
})

/** Valor unitário da diária para o destino selecionado (em centavos) */
const unitValueForDestination = computed(() => {
  const servant = selectedServant.value
  const label = form.value.destination_type
  if (!servant?.legislation_item?.values || !label) return 0
  return Number(servant.legislation_item.values[label]) || 0
})

const calculatedTotal = computed(() => {
  if (!form.value.quantity_days) return 0
  return unitValueForDestination.value * form.value.quantity_days
})

const fetchServants = async () => {
  try {
    const { data } = await api.get('/servants?all=1&is_active=1')
    servants.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar servidores:', error)
  }
}

const onServantChange = () => {
  form.value.destination_type = ''
  calculateDays()
}

const calculateDays = () => {
  if (form.value.departure_date && form.value.return_date) {
    const start = new Date(form.value.departure_date)
    const end = new Date(form.value.return_date)
    const diffTime = Math.abs(end - start)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    form.value.quantity_days = diffDays > 0 ? diffDays : 1
  }
}

const handleSubmit = async () => {
  try {
    await api.post('/daily-requests', form.value)
    await success('Sucesso', 'Solicitação enviada com sucesso.')
    router.push('/daily-requests')
  } catch (error) {
    console.error('Erro ao salvar:', error)
    showError('Erro', error.response?.data?.message || 'Erro ao enviar solicitação.')
  }
}

onMounted(() => {
  fetchServants()
})
</script>
