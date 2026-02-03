<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Relatórios</h2>
      <p class="text-xs text-slate-500">Gere relatórios detalhados com filtros avançados</p>
    </div>

    <!-- Seletor de tipo de relatório -->
    <div class="card p-4 sm:p-6">
      <div class="flex items-center gap-4 mb-6">
        <button
          v-for="type in reportTypes"
          :key="type.id"
          type="button"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
            selectedType === type.id
              ? 'bg-blue-600 text-white'
              : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
          ]"
          @click="selectType(type.id)"
        >
          {{ type.label }}
        </button>
      </div>

      <!-- Filtros de Diárias -->
      <div v-if="selectedType === 'daily-requests'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Secretaria</label>
            <select
              v-model="filters.department_id"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todas</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Servidor</label>
            <select
              v-model="filters.servant_id"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todos</option>
              <option v-for="s in servants" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select
              v-model="filters.status"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todos</option>
              <option value="pending">Pendente</option>
              <option value="validated">Validado</option>
              <option value="authorized">Concedido</option>
              <option value="paid">Pago</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data Solicitação (Início)</label>
            <input
              v-model="filters.start_date"
              type="date"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data Solicitação (Fim)</label>
            <input
              v-model="filters.end_date"
              type="date"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data Partida (Início)</label>
            <input
              v-model="filters.departure_start"
              type="date"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Data Partida (Fim)</label>
            <input
              v-model="filters.departure_end"
              type="date"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Cidade Destino</label>
            <input
              v-model="filters.destination_city"
              type="text"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: Salvador"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">UF Destino</label>
            <input
              v-model="filters.destination_state"
              type="text"
              maxlength="2"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="BA"
            />
          </div>
        </div>

        <div class="flex justify-between items-center pt-4 border-t border-slate-200">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200"
            @click="clearFilters"
          >
            Limpar Filtros
          </button>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="loading"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              @click="loadReport"
            >
              {{ loading ? 'Carregando...' : 'Gerar Relatório' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Filtros de Servidores -->
      <div v-if="selectedType === 'servants'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Secretaria</label>
            <select
              v-model="filters.department_id"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todas</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Cargo</label>
            <select
              v-model="filters.position_id"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todos</option>
              <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select
              v-model="filters.is_active"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="null">Todos</option>
              <option :value="true">Ativos</option>
              <option :value="false">Inativos</option>
            </select>
          </div>

          <div class="md:col-span-2 lg:col-span-3">
            <label class="block text-sm font-medium text-slate-700 mb-1">Buscar</label>
            <input
              v-model="filters.search"
              type="text"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Nome, CPF ou matrícula..."
            />
          </div>
        </div>

        <div class="flex justify-between items-center pt-4 border-t border-slate-200">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200"
            @click="clearFilters"
          >
            Limpar Filtros
          </button>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="loading"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              @click="loadReport"
            >
              {{ loading ? 'Carregando...' : 'Gerar Relatório' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Resultados -->
    <div v-if="reportData" class="card p-4 sm:p-6">
      <!-- Estatísticas -->
      <div v-if="stats" class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 bg-blue-50 rounded-lg">
          <div class="text-xs font-medium text-blue-600 uppercase">Total</div>
          <div class="text-2xl font-bold text-blue-900">{{ stats.total || 0 }}</div>
        </div>
        <div v-if="stats.total_value !== undefined" class="p-4 bg-green-50 rounded-lg">
          <div class="text-xs font-medium text-green-600 uppercase">Valor Total</div>
          <div class="text-2xl font-bold text-green-900">{{ formatCurrency(stats.total_value) }}</div>
        </div>
        <div v-if="stats.active !== undefined" class="p-4 bg-green-50 rounded-lg">
          <div class="text-xs font-medium text-green-600 uppercase">Ativos</div>
          <div class="text-2xl font-bold text-green-900">{{ stats.active || 0 }}</div>
        </div>
        <div v-if="stats.inactive !== undefined" class="p-4 bg-slate-50 rounded-lg">
          <div class="text-xs font-medium text-slate-600 uppercase">Inativos</div>
          <div class="text-2xl font-bold text-slate-900">{{ stats.inactive || 0 }}</div>
        </div>
      </div>

      <!-- Ações de exportação -->
      <div class="flex justify-end gap-2 mb-4">
        <button
          type="button"
          :disabled="exporting"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 disabled:opacity-50"
          @click="exportCsv"
        >
          <DocumentArrowDownIcon class="h-4 w-4" />
          {{ exporting ? 'Exportando...' : 'Exportar CSV' }}
        </button>
        <button
          type="button"
          :disabled="exporting"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
          @click="exportPdf"
        >
          <DocumentArrowDownIcon class="h-4 w-4" />
          {{ exporting ? 'Exportando...' : 'Exportar PDF' }}
        </button>
      </div>

      <!-- Tabela de Diárias -->
      <div v-if="selectedType === 'daily-requests' && reportData.length > 0" class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">ID</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Servidor</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Secretaria</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Destino</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Período</th>
              <th class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Valor</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="item in reportData" :key="item.id">
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.id }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ formatDate(item.created_at) }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.servant?.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.servant?.department?.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.destination_city }} - {{ item.destination_state }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">
                {{ formatDate(item.departure_date) }} a {{ formatDate(item.return_date) }}
              </td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900 text-right">{{ formatCurrency(item.total_value) }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm">
                <span :class="getStatusClass(item.status)" class="inline-flex px-2 py-1 text-xs font-medium rounded-full">
                  {{ item.status_label }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tabela de Servidores -->
      <div v-if="selectedType === 'servants' && reportData.length > 0" class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nome</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">CPF</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Matrícula</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cargo</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Secretaria</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">E-mail</th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="item in reportData" :key="item.id">
              <td class="px-4 sm:px-6 py-4 text-sm font-medium text-slate-900">{{ item.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.cpf }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.matricula || '—' }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.position?.name || '—' }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.department?.name }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm text-slate-900">{{ item.email || '—' }}</td>
              <td class="px-4 sm:px-6 py-4 text-sm">
                <span
                  :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'"
                  class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
                >
                  {{ item.is_active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="reportData && reportData.length === 0" class="text-center py-12 text-slate-500">
        Nenhum resultado encontrado com os filtros aplicados
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const authStore = useAuthStore()

const reportTypes = [
  { id: 'daily-requests', label: 'Solicitações de Diárias' },
  { id: 'servants', label: 'Servidores' }
]

const selectedType = ref('daily-requests')
const loading = ref(false)
const exporting = ref(false)
const reportData = ref(null)
const stats = ref(null)
const departments = ref([])
const servants = ref([])
const positions = ref([])

const filters = reactive({
  // Diárias
  department_id: null,
  servant_id: null,
  status: null,
  start_date: null,
  end_date: null,
  departure_start: null,
  departure_end: null,
  destination_city: null,
  destination_state: null,
  // Servidores
  position_id: null,
  is_active: null,
  search: null,
})

function selectType(type) {
  selectedType.value = type
  clearFilters()
  reportData.value = null
  stats.value = null
}

function clearFilters() {
  Object.keys(filters).forEach(key => {
    filters[key] = null
  })
}

async function loadReport() {
  loading.value = true
  try {
    const endpoint = selectedType.value === 'daily-requests'
      ? '/reports/daily-requests'
      : '/reports/servants'

    const params = {}
    Object.keys(filters).forEach(key => {
      if (filters[key] !== null && filters[key] !== '') {
        params[key] = filters[key]
      }
    })

    const { data } = await api.get(endpoint, { params })
    reportData.value = data.data || []
    stats.value = data.stats || null
  } catch (error) {
    console.error('Erro ao gerar relatório:', error)
    toast.error('Erro ao gerar relatório')
  } finally {
    loading.value = false
  }
}

async function exportCsv() {
  if (exporting.value) return
  exporting.value = true
  try {
    const endpoint = selectedType.value === 'daily-requests'
      ? '/reports/daily-requests/export/csv'
      : '/reports/servants/export/csv'

    const params = {}
    Object.keys(filters).forEach(key => {
      if (filters[key] !== null && filters[key] !== '') {
        params[key] = filters[key]
      }
    })

    const { data } = await api.get(endpoint, { params, responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = `relatorio-${selectedType.value}-${Date.now()}.csv`
    link.click()
    URL.revokeObjectURL(url)
    toast.success('CSV exportado com sucesso')
  } catch (error) {
    console.error('Erro ao exportar CSV:', error)
    toast.error('Erro ao exportar CSV')
  } finally {
    exporting.value = false
  }
}

async function exportPdf() {
  if (exporting.value) return
  exporting.value = true
  try {
    const endpoint = selectedType.value === 'daily-requests'
      ? '/reports/daily-requests/export/pdf'
      : '/reports/servants/export/pdf'

    const params = {}
    Object.keys(filters).forEach(key => {
      if (filters[key] !== null && filters[key] !== '') {
        params[key] = filters[key]
      }
    })

    const { data } = await api.get(endpoint, { params, responseType: 'blob' })
    const url = URL.createObjectURL(data)
    window.open(url, '_blank')
    toast.success('PDF gerado com sucesso')
  } catch (error) {
    console.error('Erro ao exportar PDF:', error)
    toast.error('Erro ao exportar PDF')
  } finally {
    exporting.value = false
  }
}

async function loadDepartments() {
  try {
    const { data } = await api.get('/departments', { params: { all: 1 } })
    departments.value = data.data ?? data ?? []
  } catch {
    departments.value = []
  }
}

async function loadServants() {
  try {
    const { data } = await api.get('/servants', { params: { all: 1 } })
    servants.value = data.data ?? data ?? []
  } catch {
    servants.value = []
  }
}

async function loadPositions() {
  try {
    const { data } = await api.get('/positions', { params: { all: 1 } })
    positions.value = data.data ?? data ?? []
  } catch {
    positions.value = []
  }
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('pt-BR')
}

function formatCurrency(cents) {
  if (cents === null || cents === undefined) return 'R$ 0,00'
  return 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function getStatusClass(status) {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    validated: 'bg-blue-100 text-blue-800',
    authorized: 'bg-purple-100 text-purple-800',
    paid: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
  }
  return classes[status] || 'bg-slate-100 text-slate-800'
}

onMounted(() => {
  loadDepartments()
  loadServants()
  loadPositions()
})
</script>
