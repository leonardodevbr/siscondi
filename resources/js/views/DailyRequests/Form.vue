<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button
        type="button"
        class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
        @click="$router.push({ name: 'daily-requests.index' })"
      >
        <ArrowLeftIcon class="h-5 w-5 text-slate-600" />
      </button>
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar Solicitação' : 'Nova Solicitação de Diária' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize os dados da solicitação' : 'Preencha os dados para criar uma nova solicitação' }}
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card p-12 text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-blue-600"></div>
      <p class="text-slate-500 mt-3">Carregando...</p>
    </div>

    <form v-else class="card p-6" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Servidor e Destino -->
        <div class="lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <UserCircleIcon class="h-5 w-5 text-slate-500" />
            Servidor e destino
          </h3>
        </div>

        <div>
          <SelectInput
            v-model="form.servant_id"
            label="Servidor *"
            :options="servantOptions"
            placeholder="Selecione o servidor..."
            :searchable="servants.length > 10"
            @update:model-value="onServantChange"
          />
        </div>

        <div>
          <SelectInput
            v-model="form.destination_type"
            label="Tipo de destino *"
            :options="destinationOptions"
            placeholder="Selecione o tipo de destino..."
            :searchable="destinationOptions.length > 10"
            :disabled="!selectedServant?.destination_options?.length"
          />
          <p v-if="selectedServant && form.destination_type && unitValueForDestination" class="mt-1 text-xs text-slate-500">
            Valor da diária para este destino: <span class="font-semibold text-slate-800">{{ formatCurrency(unitValueForDestination) }}</span>
          </p>
        </div>

        <!-- Local e período -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <MapPinIcon class="h-5 w-5 text-slate-500" />
            Local e período
          </h3>
        </div>

        <div>
          <Input
            v-model="form.destination_city"
            label="Cidade de destino *"
            required
            placeholder="Ex: Salvador"
          />
        </div>

        <div>
          <Input
            v-model="form.destination_state"
            label="Estado (UF) *"
            type="text"
            required
            maxlength="2"
            placeholder="UF"
          />
        </div>

        <div>
          <Input
            v-model="form.departure_date"
            label="Data de saída *"
            type="date"
            required
            @update:model-value="calculateDays"
          />
        </div>

        <div>
          <Input
            v-model="form.return_date"
            label="Data de retorno *"
            type="date"
            required
            @update:model-value="calculateDays"
          />
        </div>

        <!-- Quantidade e valor -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <CurrencyDollarIcon class="h-5 w-5 text-slate-500" />
            Quantidade e valor
          </h3>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Quantidade de diárias *</label>
          <input
            v-model.number="form.quantity_days"
            type="number"
            step="0.5"
            min="0.5"
            required
            class="input-base w-full"
          />
          <p class="mt-1 text-xs text-slate-500">Aceita meia diária (ex: 2,5)</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Valor total previsto</label>
          <div class="input-base w-full bg-slate-50 text-slate-900 font-semibold flex items-center min-h-[42px]">
            {{ formatCurrency(calculatedTotal) }}
          </div>
        </div>

        <!-- Motivo -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <DocumentTextIcon class="h-5 w-5 text-slate-500" />
            Motivo da viagem
          </h3>
        </div>

        <div class="lg:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-2">Motivo da viagem *</label>
          <textarea
            v-model="form.reason"
            rows="4"
            required
            placeholder="Descreva o motivo da viagem..."
            class="input-base w-full resize-y"
          />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
        <Button type="button" variant="outline" @click="$router.push({ name: 'daily-requests.index' })">
          Cancelar
        </Button>
        <Button type="submit" :loading="saving">
          {{ isEdit ? 'Atualizar solicitação' : 'Enviar solicitação' }}
        </Button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency } from '@/utils/format'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import SelectInput from '@/components/Common/SelectInput.vue'
import {
  ArrowLeftIcon,
  UserCircleIcon,
  MapPinIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { success, error: showError } = useAlert()
const isEdit = computed(() => !!route.params.id && route.params.id !== 'create')
const loading = ref(false)
const saving = ref(false)

const form = ref({
  servant_id: null,
  destination_type: '',
  destination_city: '',
  destination_state: '',
  departure_date: '',
  return_date: '',
  quantity_days: 1,
  reason: ''
})

const servants = ref([])
const servantOptions = computed(() =>
  servants.value.map((s) => ({
    value: s.id,
    label: `${s.matricula} - ${s.name}${s.cargos?.length ? ` (${s.cargos.map((c) => c.symbol || c.name).join(', ')})` : ''}`,
  }))
)

const selectedServant = computed(() =>
  servants.value.find((s) => s.id === form.value.servant_id)
)

const destinationOptions = computed(() => {
  const opts = selectedServant.value?.destination_options
  if (!opts || typeof opts !== 'object') return []
  return Object.keys(opts).map((label) => ({ value: label, label }))
})

const unitValueForDestination = computed(() => {
  const opts = selectedServant.value?.destination_options
  const label = form.value.destination_type
  if (!opts || !label) return 0
  return Number(opts[label]) || 0
})

const calculatedTotal = computed(() => {
  if (!form.value.quantity_days) return 0
  return unitValueForDestination.value * form.value.quantity_days
})

async function fetchServants() {
  try {
    const { data } = await api.get('/servants?all=1&is_active=1&for_daily_form=1')
    servants.value = data?.data ?? data ?? []
  } catch (e) {
    console.error('Erro ao carregar servidores:', e)
  }
}

function onServantChange() {
  form.value.destination_type = ''
  calculateDays()
}

function calculateDays() {
  if (form.value.departure_date && form.value.return_date) {
    const start = new Date(form.value.departure_date)
    const end = new Date(form.value.return_date)
    const diffTime = Math.abs(end - start)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    form.value.quantity_days = diffDays > 0 ? diffDays : 1
  }
}

async function loadRequest() {
  if (!route.params.id || route.params.id === 'create') return
  loading.value = true
  try {
    const { data } = await api.get(`/daily-requests/${route.params.id}`)
    const r = data?.data ?? data
    form.value = {
      servant_id: r.servant_id ?? null,
      destination_type: r.destination_type ?? '',
      destination_city: r.destination_city ?? '',
      destination_state: r.destination_state ?? '',
      departure_date: r.departure_date ? r.departure_date.slice(0, 10) : '',
      return_date: r.return_date ? r.return_date.slice(0, 10) : '',
      quantity_days: Number(r.quantity_days) ?? 1,
      reason: r.reason ?? '',
    }
  } catch (e) {
    showError('Erro', 'Não foi possível carregar a solicitação.')
    router.push({ name: 'daily-requests.index' })
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (isEdit.value) {
      await api.put(`/daily-requests/${route.params.id}`, payload)
      success('Sucesso', 'Solicitação atualizada.')
    } else {
      await api.post('/daily-requests', payload)
      success('Sucesso', 'Solicitação enviada com sucesso.')
    }
    router.push({ name: 'daily-requests.index' })
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Erro ao salvar solicitação.')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchServants()
  if (isEdit.value) {
    await loadRequest()
  }
})
</script>
