<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Solicitações de Diárias</h1>
      <router-link
        to="/daily-requests/create"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        <PlusIcon class="h-5 w-5" />
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

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servidor</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="sticky right-0 z-10 bg-gray-50 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase border-l border-gray-200">Ações</th>
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
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(request.total_value) }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="getStatusClass(request.status)" class="px-2 py-1 text-xs rounded-full">
                {{ request.status_label }}
              </span>
            </td>
            <td class="sticky right-0 z-10 bg-white px-6 py-4 whitespace-nowrap border-l border-gray-200">
              <div class="flex items-center justify-end gap-2">
                <router-link
                  :to="{ name: 'daily-requests.show', params: { id: request.id } }"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                  title="Ver detalhes"
                >
                  <EyeIcon class="h-4 w-4" />
                  Detalhes
                </router-link>
                <button
                  v-if="request.can_generate_pdf"
                  type="button"
                  class="inline-flex items-center p-1.5 text-slate-600 hover:text-slate-900 rounded hover:bg-slate-100 transition-colors"
                  title="Ver PDF"
                  @click="openPdf(request.id)"
                >
                  <DocumentArrowDownIcon class="h-5 w-5" />
                </button>
                <span
                  v-else
                  class="inline-flex items-center p-1.5 text-slate-400 cursor-not-allowed"
                  title="PDF disponível após assinatura do prefeito (concedente)"
                >
                  <DocumentArrowDownIcon class="h-5 w-5" />
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <PaginationBar
      v-if="pagination"
      :pagination="pagination"
      @page-change="(page) => fetchRequests({ page })"
      @per-page-change="onPerPageChange"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency } from '@/utils/format'
import {
  EyeIcon,
  DocumentArrowDownIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import PaginationBar from '@/components/Common/PaginationBar.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { error: showError } = useAlert()
const requests = ref([])
const pagination = ref(null)
const perPageRef = ref(15)
const filters = ref({ status: route.query.status || '' })

const fetchRequests = async (params = {}) => {
  try {
    const p = { ...filters.value, ...params, per_page: perPageRef.value }
    const { data } = await api.get('/daily-requests', { params: p })
    requests.value = data.data || data
    if (data.meta) {
      pagination.value = data.meta
      perPageRef.value = data.meta.per_page ?? perPageRef.value
    } else if (data.current_page) {
      pagination.value = data
      perPageRef.value = data.per_page ?? perPageRef.value
    } else {
      pagination.value = null
    }
  } catch (err) {
    console.error('Erro ao carregar solicitações:', err)
    showError('Erro', err.response?.data?.message || 'Não foi possível carregar as solicitações.')
  }
}

const openPdf = async (id) => {
  try {
    const { data } = await api.get(`/daily-requests/${id}/pdf`, { responseType: 'blob' })
    if (data instanceof Blob && data.size > 0) {
      const url = URL.createObjectURL(data)
      window.open(url, '_blank')
    } else {
      showError('Erro', 'PDF não disponível.')
    }
  } catch (e) {
    console.error('Erro ao abrir PDF:', e)
    showError('Erro', e.response?.status === 500 ? 'Falha ao gerar o PDF. Tente novamente.' : (e.response?.data?.message || 'Não foi possível abrir o PDF.'))
  }
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

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('pt-BR')
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage
  fetchRequests({ page: 1, per_page: perPage })
}

const applyQueryFilter = () => {
  const status = route.query.status
  if (status && filters.value.status !== status) {
    filters.value.status = status
  }
}

onMounted(() => {
  applyQueryFilter()
  fetchRequests()
})

watch(() => route.query.status, (newStatus) => {
  filters.value.status = newStatus || ''
  fetchRequests()
})
</script>
