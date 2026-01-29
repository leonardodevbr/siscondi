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
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ servant.legislation_item ? `${servant.legislation_item.functional_category} (${servant.legislation_item.daily_class})` : '–' }}</td>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'

const servants = ref([])

const fetchServants = async () => {
  try {
    const { data } = await api.get('/servants')
    servants.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar servidores:', error)
  }
}

onMounted(() => {
  fetchServants()
})
</script>
