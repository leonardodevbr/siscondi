<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Servidores Públicos</h1>
      <router-link
        to="/servants/create"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        Novo Servidor
      </router-link>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome, CPF ou matrícula..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
      </div>

      <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matrícula</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPF</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cargo</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lotação</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="sticky right-0 z-10 bg-gray-50 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase border-l border-gray-200">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="servant in servants" :key="servant.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ servant.matricula }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ servant.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ servant.formatted_cpf || servant.cpf }}</td>
              <td class="px-6 py-4 text-sm text-gray-900">{{ servant.cargo ? (servant.cargo.name ? `${servant.cargo.name} (${servant.cargo.symbol})` : servant.cargo.symbol) : '–' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ servant.department?.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="servant.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 text-xs rounded-full">
                  {{ servant.is_active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="sticky right-0 z-10 bg-white px-6 py-4 whitespace-nowrap text-right border-l border-gray-200">
                <router-link
                  :to="`/servants/${servant.id}/edit`"
                  class="inline-flex p-1.5 text-blue-600 hover:text-blue-900 rounded hover:bg-blue-50 transition-colors"
                  title="Editar"
                >
                  <PencilSquareIcon class="h-5 w-5" />
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-sm text-slate-500">Mostrando {{ pagination.from }} a {{ pagination.to }} de {{ pagination.total }} resultados</div>
        <div class="flex gap-2">
          <button 
            v-if="pagination.current_page > 1" 
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
            @click="fetchServants({ page: pagination.current_page - 1 })"
          >
            Anterior
          </button>
          <button 
            v-if="pagination.current_page < pagination.last_page" 
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
            @click="fetchServants({ page: pagination.current_page + 1 })"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'

const servants = ref([])
const searchQuery = ref('')
const pagination = ref(null)
let searchTimeout = null

const fetchServants = async (params = {}) => {
  try {
    const p = { ...params }
    if (searchQuery.value) p.search = searchQuery.value
    
    const { data } = await api.get('/servants', { params: p })
    servants.value = data.data || data
    if (data.meta) {
      pagination.value = data.meta
    } else if (data.current_page) {
      pagination.value = data
    }
  } catch (error) {
    console.error('Erro ao carregar servidores:', error)
  }
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchServants(), 500)
}

onMounted(() => {
  fetchServants()
})
</script>
