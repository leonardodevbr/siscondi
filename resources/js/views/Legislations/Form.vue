<template>
  <div class="p-6 max-w-6xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">
      {{ isEdit ? 'Editar Legislação' : 'Nova Legislação' }}
    </h1>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Título da Lei *</label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="Ex: ANEXO ÚNICO - Diárias"
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
      </div>

      <Toggle v-model="form.is_active" label="Ativo" />

      <div>
        <div class="flex justify-between items-center mb-3">
          <h2 class="text-lg font-semibold text-gray-900">Itens da tabela de valores (por categoria e destino)</h2>
          <button type="button" @click="addItem" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Adicionar item
          </button>
        </div>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categoria Funcional</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Classe da Diária</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Até 200 km</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acima 200 km</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Capital Estado</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Demais Capitais/DF</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Exterior</th>
                <th class="px-3 py-2 w-10"></th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(item, idx) in form.items" :key="idx">
                <td class="px-3 py-2">
                  <input v-model="item.functional_category" type="text" placeholder="Ex: Prefeito e Vice-Prefeito" class="block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model="item.daily_class" type="text" placeholder="Ex: Classe A" class="block w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.value_up_to_200km" type="number" step="0.01" min="0" placeholder="0" title="Valor em R$ (reais)" class="block w-20 rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.value_above_200km" type="number" step="0.01" min="0" placeholder="0" class="block w-20 rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.value_state_capital" type="number" step="0.01" min="0" placeholder="0" class="block w-20 rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.value_other_capitals_df" type="number" step="0.01" min="0" placeholder="0" class="block w-20 rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.value_exterior" type="number" step="0.01" min="0" placeholder="0" class="block w-20 rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" />
                </td>
                <td class="px-3 py-2">
                  <button type="button" @click="removeItem(idx)" class="text-red-600 hover:text-red-800 p-1" title="Remover">×</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="form.items.length === 0" class="mt-2 text-sm text-gray-500">Nenhum item. Clique em "Adicionar item" para incluir uma linha da tabela de diárias.</p>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <router-link to="/legislations" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
          Cancelar
        </router-link>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Salvar
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import Toggle from '@/components/Common/Toggle.vue'

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = ref(false)

const form = ref({
  title: '',
  law_number: '',
  is_active: true,
  items: []
})

const fetchLegislation = async () => {
  const id = route.params.id
  if (!id) return
  try {
    const { data } = await api.get(`/legislations/${id}`)
    const payload = data?.data ?? data ?? {}
    if (!payload.id && !payload.title) {
      showError('Erro', 'Dados da legislação não encontrados.')
      return
    }
    form.value = {
      title: payload.title ?? '',
      law_number: payload.law_number ?? '',
      is_active: payload.is_active ?? true,
      items: Array.isArray(payload.items)
        ? payload.items.map((it) => ({
            functional_category: it.functional_category ?? '',
            daily_class: it.daily_class ?? '',
            value_up_to_200km: (Number(it.value_up_to_200km) || 0) / 100,
            value_above_200km: (Number(it.value_above_200km) || 0) / 100,
            value_state_capital: (Number(it.value_state_capital) || 0) / 100,
            value_other_capitals_df: (Number(it.value_other_capitals_df) || 0) / 100,
            value_exterior: (Number(it.value_exterior) || 0) / 100
          }))
        : []
    }
  } catch (err) {
    console.error('Erro ao carregar legislação:', err)
    showError('Erro', 'Não foi possível carregar a legislação.')
  }
}

const addItem = () => {
  form.value.items.push({
    functional_category: '',
    daily_class: '',
    value_up_to_200km: '',
    value_above_200km: '',
    value_state_capital: '',
    value_other_capitals_df: '',
    value_exterior: ''
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const toPayload = () => {
  const items = (form.value.items || []).map((it) => ({
    functional_category: it.functional_category || '',
    daily_class: it.daily_class || '',
    value_up_to_200km: Number(it.value_up_to_200km) || 0,
    value_above_200km: Number(it.value_above_200km) || 0,
    value_state_capital: Number(it.value_state_capital) || 0,
    value_other_capitals_df: Number(it.value_other_capitals_df) || 0,
    value_exterior: Number(it.value_exterior) || 0
  }))
  return { title: form.value.title, law_number: form.value.law_number, is_active: form.value.is_active, items }
}

const handleSubmit = async () => {
  try {
    const payload = toPayload()
    if (isEdit.value) {
      await api.put(`/legislations/${route.params.id}`, payload)
    } else {
      await api.post('/legislations', payload)
    }
    await success('Salvo', 'Legislação salva com sucesso.')
    router.push('/legislations')
  } catch (err) {
    console.error('Erro ao salvar:', err)
    showError('Erro', err.response?.data?.message || 'Erro ao salvar legislação.')
  }
}

onMounted(() => {
  const id = route.params.id
  if (id && id !== 'create' && String(Number(id)) === String(id)) {
    isEdit.value = true
    fetchLegislation()
  }
})
</script>
