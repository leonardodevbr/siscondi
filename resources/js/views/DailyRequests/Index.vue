<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Solicitações de Diárias</h1>
      <router-link
        to="/daily-requests/create"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        Nova Solicitação
      </router-link>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-4 mb-4 flex gap-4">
      <select v-model="filters.status" @change="fetchRequests" class="rounded-md border-gray-300">
        <option value="">Todos os Status</option>
        <option value="draft">Rascunho</option>
        <option value="requested">Solicitado</option>
        <option value="validated">Validado</option>
        <option value="authorized">Concedido</option>
        <option value="paid">Pago</option>
        <option value="cancelled">Cancelado</option>
      </select>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servidor</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="request in requests" :key="request.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ request.id }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ request.servant?.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ request.destination_city }}/{{ request.destination_state }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ formatDate(request.departure_date) }} - {{ formatDate(request.return_date) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R$ {{ formatCurrency(request.total_value) }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="getStatusClass(request.status)" class="px-2 py-1 text-xs rounded-full">
                {{ request.status_label }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <router-link :to="`/daily-requests/${request.id}`" class="text-blue-600 hover:text-blue-900 mr-2">Ver</router-link>
              <button v-if="request.is_editable" @click="editRequest(request.id)" class="text-green-600 hover:text-green-900">Editar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const requests = ref([])
const filters = ref({ status: '' })

const fetchRequests = async () => {
  try {
    const { data } = await axios.get('/api/daily-requests', { params: filters.value })
    requests.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar solicitações:', error)
  }
}

const editRequest = (id) => {
  router.push(`/daily-requests/${id}/edit`)
}

const getStatusClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    requested: 'bg-blue-100 text-blue-800',
    validated: 'bg-yellow-100 text-yellow-800',
    authorized: 'bg-green-100 text-green-800',
    paid: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(value)
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('pt-BR')
}

onMounted(() => {
  fetchRequests()
})
</script>
