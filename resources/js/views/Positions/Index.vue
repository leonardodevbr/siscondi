<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Cargos</h2>
        <p class="text-xs text-slate-500">Gerencie os cargos (símbolo) e vincule aos itens da legislação</p>
      </div>
      <router-link to="/positions/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
        Novo Cargo
      </router-link>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por símbolo ou nome..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
      </div>

      <div v-if="loading" class="text-center py-8">
        <p class="text-slate-500">Carregando cargos...</p>
      </div>
      <div v-else-if="positions.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhum cargo encontrado</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Símbolo</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Itens da lei vinculados</th>
              <th class="sticky right-0 z-10 bg-slate-50 px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider border-l border-slate-200">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="position in positions" :key="position.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">{{ position.symbol }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-700">{{ position.name || '–' }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm text-slate-600">
                  <template v-if="position.legislation_items && position.legislation_items.length">
                    <span v-for="item in position.legislation_items" :key="item.id" class="inline-block mr-1 mb-1 px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs">
                      {{ item.functional_category }} ({{ item.daily_class }})
                    </span>
                  </template>
                  <span v-else class="text-slate-400">Nenhum</span>
                </div>
              </td>
              <td class="sticky right-0 z-10 bg-white px-6 py-4 whitespace-nowrap text-right text-sm font-medium border-l border-slate-200">
                <div class="flex items-center justify-end gap-1">
                  <button type="button" class="p-1.5 text-red-600 hover:text-red-900 rounded hover:bg-red-50 transition-colors" title="Excluir" @click="deletePosition(position)">
                    <TrashIcon class="h-5 w-5" />
                  </button>
                  <router-link :to="`/positions/${position.id}/edit`" class="p-1.5 text-blue-600 hover:text-blue-900 rounded hover:bg-blue-50 transition-colors" title="Editar">
                    <PencilSquareIcon class="h-5 w-5" />
                  </router-link>
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
        @page-change="(page) => fetchPositions({ page })"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useAlert } from '@/composables/useAlert'
import PaginationBar from '@/components/Common/PaginationBar.vue'

const { success, confirm, error: showError } = useAlert()
const positions = ref([])
const loading = ref(true)
const searchQuery = ref('')
const pagination = ref(null)
const perPageRef = ref(15)
let searchTimeout = null

const fetchPositions = async (params = {}) => {
  loading.value = true
  try {
    const p = { per_page: perPageRef.value, ...params }
    if (searchQuery.value) p.search = searchQuery.value

    const { data } = await api.get('/positions', { params: p })
    positions.value = data.data ?? data
    if (data.meta) {
      pagination.value = data.meta
      perPageRef.value = data.meta.per_page ?? perPageRef.value
    } else if (data.current_page) {
      pagination.value = data
      perPageRef.value = data.per_page ?? perPageRef.value
    }
  } catch (e) {
    console.error(e)
    showError('Erro', 'Não foi possível carregar os cargos.')
  } finally {
    loading.value = false
  }
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage
  fetchPositions({ page: 1, per_page: perPage })
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchPositions(), 500)
}

const deletePosition = async (position) => {
  const ok = await confirm(`Excluir o cargo "${position.symbol}"?`, 'Essa ação não pode ser desfeita.')
  if (!ok) return
  try {
    await api.delete(`/positions/${position.id}`)
    await success('Excluído', 'Cargo excluído com sucesso.')
    await fetchPositions()
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Erro ao excluir cargo.'
    showError('Erro', msg)
  }
}

onMounted(() => {
  fetchPositions()
})
</script>
