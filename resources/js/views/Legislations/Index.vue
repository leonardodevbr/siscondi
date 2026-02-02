<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Legislações</h1>
      <router-link
        to="/legislations/create"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        Nova Legislação
      </router-link>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lei Nº</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="sticky right-0 z-10 bg-gray-50 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase border-l border-gray-200">Ações</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="item in legislations" :key="item.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.law_number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ (item.items || []).length }} categoria(s)</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 text-xs rounded-full">
                {{ item.is_active ? 'Ativo' : 'Inativo' }}
              </span>
            </td>
            <td class="sticky right-0 z-10 bg-white px-6 py-4 whitespace-nowrap text-right border-l border-gray-200">
              <router-link
                :to="`/legislations/${item.id}/edit`"
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
    <PaginationBar
      v-if="pagination"
      :pagination="pagination"
      @page-change="(page) => fetchLegislations({ page })"
      @per-page-change="onPerPageChange"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import PaginationBar from '@/components/Common/PaginationBar.vue'

const legislations = ref([])
const pagination = ref(null)
const perPageRef = ref(15)

const fetchLegislations = async (params = {}) => {
  try {
    const p = { per_page: perPageRef.value, ...params }
    const { data } = await api.get('/legislations', { params: p })
    legislations.value = data.data || data
    if (data.meta) {
      pagination.value = data.meta
      perPageRef.value = data.meta.per_page ?? perPageRef.value
    } else if (data.current_page) {
      pagination.value = data
      perPageRef.value = data.per_page ?? perPageRef.value
    } else {
      pagination.value = null
    }
  } catch (error) {
    console.error('Erro ao carregar legislações:', error)
  }
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage
  fetchLegislations({ page: 1, per_page: perPage })
}

onMounted(() => {
  fetchLegislations()
})
</script>
