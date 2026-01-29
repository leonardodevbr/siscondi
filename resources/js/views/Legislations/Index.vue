<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Legislações (Cargos)</h1>
      <router-link
        to="/legislations/create"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
      >
        Nova Legislação
      </router-link>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cargo</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lei Nº</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor Diária</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="item in legislations" :key="item.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.code }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.law_number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R$ {{ formatCurrency(item.daily_value) }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="item.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 text-xs rounded-full">
                {{ item.is_active ? 'Ativo' : 'Inativo' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const legislations = ref([])

const fetchLegislations = async () => {
  try {
    const { data } = await api.get('/legislations')
    legislations.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar legislações:', error)
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2 }).format(value)
}

onMounted(() => {
  fetchLegislations()
})
</script>
