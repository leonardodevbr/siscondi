<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">
      {{ isEdit ? 'Editar Legislação' : 'Nova Legislação' }}
    </h1>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Título da Lei *</label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="Ex: ANEXO ÚNICO - Diárias"
            class="input-base w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lei Nº *</label>
          <input
            v-model="form.law_number"
            type="text"
            required
            placeholder="Ex: Lei 001/2024"
            class="input-base w-full"
          />
        </div>
      </div>

      <Toggle v-model="form.is_active" label="Ativo" />

      <!-- Destinos desta lei (cada lei pode ter seus próprios destinos) -->
      <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/50">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
          <h2 class="text-lg font-semibold text-gray-900">Destinos desta lei</h2>
          <button type="button" @click="addDestination" class="px-3 py-1.5 text-sm bg-gray-600 text-white rounded-lg hover:bg-gray-700 shrink-0">
            Adicionar destino
          </button>
        </div>
        <p class="text-sm text-gray-500 mb-3">Defina as colunas de valor por destino (ex.: Até 200 km, Capital Estado). Arraste o destino pelo ícone para reordenar; a tabela abaixo acompanha a ordem.</p>
        <div class="flex flex-wrap gap-2">
          <div
            v-for="(dest, idx) in form.destinations"
            :key="idx"
            data-dest-chip
            class="dest-chip flex w-52 min-w-[11rem] rounded-lg border border-slate-300 bg-white overflow-hidden focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-100 transition-opacity"
            :class="{ 'opacity-50': draggedDestIndex === idx, 'dest-chip-dragging': draggedDestIndex === idx }"
            @dragover.prevent="onDestDragOver($event, idx)"
            @drop.prevent="onDestDrop($event, idx)"
          >
            <span
              draggable="true"
              class="dest-drag-handle flex items-center justify-center shrink-0 w-8 cursor-grab active:cursor-grabbing border-r border-slate-200 text-slate-400 hover:bg-slate-50 hover:text-slate-600 select-none"
              title="Arrastar para reordenar"
              @dragstart="onDestDragStart($event, idx)"
              @dragend="onDestDragEnd"
            >
              <Bars3Icon class="w-4 h-4" />
            </span>
            <input
              v-model="form.destinations[idx]"
              type="text"
              required
              placeholder="Ex: Até 200 km"
              class="flex-1 min-w-0 border-0 bg-transparent px-2 py-2 text-sm text-slate-900 focus:outline-none focus:ring-0"
            />
            <button
              type="button"
              @click="removeDestination(idx)"
              class="flex items-center justify-center shrink-0 w-9 border-l border-slate-300 text-red-600 hover:bg-red-50 hover:text-red-800 transition-colors"
              title="Remover destino"
            >
              ×
            </button>
          </div>
        </div>
      </div>

      <div>
        <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
          <h2 class="text-lg font-semibold text-gray-900">Itens da tabela de valores (por categoria e destino)</h2>
          <button type="button" @click="addItem" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 shrink-0">
            Adicionar item
          </button>
        </div>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria Funcional</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap w-52">Cargos vinculados</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Classe da Diária</th>
                <th v-for="(dest, idx) in form.destinations" :key="idx" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                  {{ dest || `Destino ${idx + 1}` }}
                </th>
                <th class="px-4 py-3 w-12"></th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(item, idx) in form.items" :key="idx">
                <td class="px-4 py-3 align-top">
                  <input v-model="item.functional_category" type="text" placeholder="Ex: Prefeito e Vice-Prefeito" class="block w-full min-w-[12rem] rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-100" />
                </td>
                <td class="px-4 py-3 align-top">
                  <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-600 whitespace-nowrap">
                      {{ getPositionCountLabel(item) }}
                    </span>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 px-2 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 shrink-0"
                      @click="openPositionModal(idx)"
                    >
                      <PencilSquareIcon class="w-3.5 h-3.5" />
                      Selecionar
                    </button>
                  </div>
                </td>
                <td class="px-4 py-3 align-top">
                  <input v-model="item.daily_class" type="text" placeholder="Ex: Classe A" class="block w-full min-w-[6rem] rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-100" />
                </td>
                <td v-for="(dest, dIdx) in form.destinations" :key="dIdx" class="px-4 py-3 align-top">
                  <input
                    v-model.number="item.valueByIndex[dIdx]"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0"
                    class="block w-full min-w-[5.5rem] rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-100"
                  />
                </td>
                <td class="px-4 py-3 align-top">
                  <button type="button" @click="removeItem(idx)" class="text-red-600 hover:text-red-800 p-2 rounded hover:bg-red-50" title="Remover">×</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="form.items.length === 0" class="mt-2 text-sm text-gray-500">Nenhum item. Clique em "Adicionar item" para incluir uma linha da tabela de diárias.</p>
      </div>

      <!-- Modal: Selecionar cargos -->
      <Modal
        :is-open="positionModalOpen"
        title="Selecionar cargos"
        @close="closePositionModal"
      >
        <div class="space-y-4">
          <p v-if="positionModalCategoryLabel" class="text-slate-600 text-sm font-medium border-b border-slate-200 pb-2 -mt-1">
            {{ positionModalCategoryLabel }}
          </p>
          <input
            v-model="positionSearch"
            type="search"
            placeholder="Pesquisar cargos..."
            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-100"
          />
          <div class="max-h-80 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label
              v-for="opt in filteredPositionOptions"
              :key="opt.value"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="positionModalSelectedIds.includes(opt.value)"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                @change="togglePositionModalSelection(opt.value)"
              />
              <span class="text-sm text-slate-800 flex-1">{{ opt.label }}</span>
              <span
                v-if="countItemsLinkedToPosition(opt.value) > 0"
                class="shrink-0 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-xs font-medium bg-slate-200 text-slate-700"
                :title="countItemsLinkedToPosition(opt.value) + ' item(ns) vinculado(s)'"
              >
                {{ countItemsLinkedToPosition(opt.value) }}
              </span>
            </label>
            <p v-if="filteredPositionOptions.length === 0" class="px-3 py-4 text-sm text-slate-500">
              Nenhum cargo encontrado.
            </p>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200"
              @click="closePositionModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
              @click="applyPositionSelection"
            >
              Aplicar
            </button>
          </div>
        </div>
      </Modal>

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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Bars3Icon, PencilSquareIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import Toggle from '@/components/Common/Toggle.vue'
import Modal from '@/components/Common/Modal.vue'

const DEFAULT_DESTINATIONS = ['Até 200 km', 'Acima 200 km', 'Capital Estado', 'Demais Capitais/DF', 'Exterior']

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = ref(false)
const positions = ref([])
const positionOptions = ref([])
const positionModalOpen = ref(false)
const positionModalItemIndex = ref(null)
const positionSearch = ref('')
const positionModalSelectedIds = ref([])

const form = ref({
  title: '',
  law_number: '',
  is_active: true,
  destinations: [...DEFAULT_DESTINATIONS],
  items: []
})

function valueArrayForDestinations(destinations) {
  return (destinations || []).map(() => '')
}

function addDestination() {
  form.value.destinations.push(`Novo destino ${form.value.destinations.length + 1}`)
  form.value.items.forEach((item) => {
    item.valueByIndex.push('')
  })
}

function removeDestination(idx) {
  form.value.destinations.splice(idx, 1)
  form.value.items.forEach((item) => {
    item.valueByIndex.splice(idx, 1)
  })
}

const draggedDestIndex = ref(null)
let dragGhostEl = null

function onDestDragStart(e, idx) {
  draggedDestIndex.value = idx
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(idx))

  const chip = e.currentTarget.closest('[data-dest-chip]')
  if (chip) {
    const rect = chip.getBoundingClientRect()
    const clone = chip.cloneNode(true)
    clone.classList.add('dest-drag-ghost')
    clone.style.cssText = `
      position: fixed; top: -9999px; left: 0;
      width: ${rect.width}px; min-width: ${rect.width}px;
      pointer-events: none; opacity: 0.95;
      box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.12), 0 4px 6px -4px rgb(0 0 0 / 0.08);
      z-index: 9999; border-radius: 0.5rem; background: white;
    `
    document.body.appendChild(clone)
    dragGhostEl = clone
    e.dataTransfer.setDragImage(clone, rect.width / 2, rect.height / 2)
  }
}

function onDestDragEnd() {
  draggedDestIndex.value = null
  if (dragGhostEl && dragGhostEl.parentNode) {
    dragGhostEl.parentNode.removeChild(dragGhostEl)
    dragGhostEl = null
  }
}

function onDestDragOver(e, toIdx) {
  e.dataTransfer.dropEffect = 'move'
}

function onDestDrop(e, toIdx) {
  const fromIdx = parseInt(e.dataTransfer.getData('text/plain'), 10)
  if (fromIdx === toIdx) return
  moveDestination(fromIdx, toIdx)
  draggedDestIndex.value = null
}

function moveDestination(fromIdx, toIdx) {
  const dest = form.value.destinations.splice(fromIdx, 1)[0]
  form.value.destinations.splice(toIdx, 0, dest)
  form.value.items.forEach((item) => {
    const val = item.valueByIndex.splice(fromIdx, 1)[0]
    item.valueByIndex.splice(toIdx, 0, val ?? '')
  })
}

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
    const destinations = Array.isArray(payload.destinations) && payload.destinations.length
      ? payload.destinations
      : [...DEFAULT_DESTINATIONS]

    form.value.title = payload.title ?? ''
    form.value.law_number = payload.law_number ?? ''
    form.value.is_active = payload.is_active ?? true
    form.value.destinations = [...destinations]
    const normalizePositionIds = (arr) => {
      if (!Array.isArray(arr)) return []
      return arr.map((c) => (c != null && typeof c === 'object' && 'id' in c ? c.id : c))
    }
    form.value.items = Array.isArray(payload.items)
      ? payload.items.map((it) => {
          const rawValues = it.values ?? {}
          const valueByIndex = destinations.map((d) => (Number(rawValues[d]) || 0) / 100)
          return {
            id: it.id ?? null,
            functional_category: it.functional_category ?? '',
            daily_class: it.daily_class ?? '',
            valueByIndex,
            position_ids: normalizePositionIds(it.position_ids)
          }
        })
      : []
  } catch (err) {
    console.error('Erro ao carregar legislação:', err)
    showError('Erro', 'Não foi possível carregar a legislação.')
  }
}

const addItem = () => {
  form.value.items.push({
    id: null,
    functional_category: '',
    daily_class: '',
    valueByIndex: valueArrayForDestinations(form.value.destinations),
    position_ids: []
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

function getPositionCount(item) {
  return (Array.isArray(item.position_ids) ? item.position_ids : []).length
}

function getPositionCountLabel(item) {
  const n = getPositionCount(item)
  if (n === 0) return 'Nenhum'
  if (n === 1) return '1 cargo'
  return `${n} cargos`
}

/** Quantidade de itens da legislação (linhas da tabela) vinculados a um cargo */
function countItemsLinkedToPosition(positionId) {
  const items = form.value.items || []
  let count = 0
  for (const item of items) {
    const ids = Array.isArray(item.position_ids) ? item.position_ids : []
    const normalized = ids.map((c) => (c != null && typeof c === 'object' && 'value' in c ? c.value : c))
    if (normalized.includes(positionId)) count++
  }
  return count
}

const filteredPositionOptions = computed(() => {
  const q = (positionSearch.value || '').trim().toLowerCase()
  const list = positionOptions.value || []
  if (!q) return list
  return list.filter((o) => (o.label || '').toLowerCase().includes(q) || String(o.value || '').includes(q))
})

const positionModalCategoryLabel = computed(() => {
  const idx = positionModalItemIndex.value
  if (idx == null || !form.value.items[idx]) return ''
  const item = form.value.items[idx]
  const cat = (item.functional_category || '').trim()
  const cls = (item.daily_class || '').trim()
  if (cat && cls) return `Categoria: ${cat} — ${cls}`
  if (cat) return `Categoria: ${cat}`
  return ''
})

function openPositionModal(itemIndex) {
  positionModalItemIndex.value = itemIndex
  const item = form.value.items[itemIndex]
  const ids = Array.isArray(item?.position_ids) ? item.position_ids : []
  positionModalSelectedIds.value = ids.map((c) => (c != null && typeof c === 'object' && 'value' in c ? c.value : c))
  positionSearch.value = ''
  positionModalOpen.value = true
}

function closePositionModal() {
  positionModalOpen.value = false
  positionModalItemIndex.value = null
  positionModalSelectedIds.value = []
  positionSearch.value = ''
}

function togglePositionModalSelection(id) {
  const idx = positionModalSelectedIds.value.indexOf(id)
  if (idx === -1) {
    positionModalSelectedIds.value = [...positionModalSelectedIds.value, id]
  } else {
    positionModalSelectedIds.value = positionModalSelectedIds.value.filter((x) => x !== id)
  }
}

function applyPositionSelection() {
  const idx = positionModalItemIndex.value
  if (idx == null || !form.value.items[idx]) return
  form.value.items[idx].position_ids = [...positionModalSelectedIds.value]
  closePositionModal()
}

const reaisToCents = (val) => Math.round(100 * (Number(val) || 0))

const toPayload = () => {
  const destinations = form.value.destinations.filter(Boolean)
  const items = (form.value.items || []).map((it) => {
    const values = {}
    destinations.forEach((d, i) => {
      values[d] = reaisToCents(it.valueByIndex?.[i])
    })
    const payloadItem = {
      functional_category: it.functional_category || '',
      daily_class: it.daily_class || '',
      values
    }
    if (it.id != null && it.id !== '') {
      payloadItem.id = it.id
    }
    const positionIds = Array.isArray(it.position_ids) ? it.position_ids : []
    payloadItem.position_ids = positionIds.map((c) => (c != null && typeof c === 'object' && 'value' in c ? c.value : c))
    return payloadItem
  })
  return {
    title: form.value.title,
    law_number: form.value.law_number,
    is_active: form.value.is_active,
    destinations,
    items
  }
}

const handleSubmit = async () => {
  if (!form.value.destinations.length || form.value.destinations.every((d) => !d)) {
    showError('Erro', 'Adicione ao menos um destino à lei.')
    return
  }
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

const fetchPositions = async () => {
  try {
    const { data } = await api.get('/positions?all=1')
    const list = data.data ?? data ?? []
    positionOptions.value = list.map((c) => ({ value: c.id, label: c.name ? `${c.name} (${c.symbol})` : c.symbol }))
  } catch (e) {
    console.error(e)
  }
}

onMounted(async () => {
  await fetchPositions()
  const id = route.params.id
  if (id && id !== 'create' && String(Number(id)) === String(id)) {
    isEdit.value = true
    fetchLegislation()
  }
})
</script>
