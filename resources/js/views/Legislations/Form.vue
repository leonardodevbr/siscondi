<template>
  <div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">
      {{ isEdit ? 'Editar Legislação' : 'Nova Legislação' }}
    </h1>

    <form @submit.prevent="handleSubmit" class="bg-white rounded-lg shadow p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Código *</label>
        <input
          v-model="form.code"
          type="text"
          required
          placeholder="Ex: CC-1"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Cargo/Título *</label>
        <input
          v-model="form.title"
          type="text"
          required
          placeholder="Ex: Secretário Municipal"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Lei Nº *</label>
        <input
          v-model="form.law_number"
          type="text"
          required
          placeholder="Ex: Lei 001/2024"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Valor da Diária (R$) *</label>
        <input
          v-model="form.daily_value"
          type="number"
          step="0.01"
          required
          placeholder="350.00"
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
        />
      </div>

      <div class="flex items-center">
        <input
          v-model="form.is_active"
          type="checkbox"
          class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <label class="ml-2 block text-sm text-gray-900">Ativo</label>
      </div>

      <div class="flex gap-3 pt-4">
        <button
          type="submit"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Salvar
        </button>
        <router-link
          to="/legislations"
          class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
        >
          Cancelar
        </router-link>
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
  code: '',
  title: '',
  law_number: '',
  daily_value: '',
  is_active: true
})

const fetchLegislation = async () => {
  try {
    const { data } = await axios.get(`/api/legislations/${route.params.id}`)
    form.value = data.data || data
  } catch (error) {
    console.error('Erro ao carregar legislação:', error)
  }
}

const handleSubmit = async () => {
  try {
    if (isEdit.value) {
      await axios.put(`/api/legislations/${route.params.id}`, form.value)
    } else {
      await axios.post('/api/legislations', form.value)
    }
    router.push('/legislations')
  } catch (error) {
    console.error('Erro ao salvar:', error)
    alert('Erro ao salvar legislação')
  }
}

onMounted(() => {
  if (route.params.id) {
    isEdit.value = true
    fetchLegislation()
  }
})
</script>
