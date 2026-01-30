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
      <div class="flex-1 min-w-0">
        <h2 class="text-lg font-semibold text-slate-800">Detalhes da Solicitação #{{ request?.id }}</h2>
        <p class="text-xs text-slate-500">Visualização e assinatura conforme seu perfil</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <router-link
          v-if="request?.is_editable && authStore.can('daily-requests.edit')"
          :to="{ name: 'daily-requests.edit', params: { id: request.id } }"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200"
        >
          <PencilSquareIcon class="h-4 w-4" />
          Editar
        </router-link>
        <button
          v-if="request?.can_generate_pdf"
          type="button"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
          @click="openPdf"
        >
          <DocumentArrowDownIcon class="h-4 w-4" />
          Ver PDF
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="card p-12 text-center">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-blue-600"></div>
      <p class="text-slate-500 mt-3">Carregando...</p>
    </div>

    <div v-else-if="request" class="card p-6">
      <!-- Status -->
      <div class="mb-6 pb-4 border-b border-slate-200">
        <span :class="statusClass" class="inline-flex px-3 py-1 text-sm font-medium rounded-full">
          {{ request.status_label }}
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Servidor e Destino -->
        <div class="lg:col-span-2">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <UserCircleIcon class="h-5 w-5 text-slate-500" />
            Servidor e destino
          </h3>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Servidor</p>
          <p class="mt-1 text-slate-900">{{ request.servant?.name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Tipo de destino</p>
          <p class="mt-1 text-slate-900">{{ request.destination_type || '—' }}</p>
        </div>

        <!-- Local e período -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <MapPinIcon class="h-5 w-5 text-slate-500" />
            Local e período
          </h3>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Cidade / UF</p>
          <p class="mt-1 text-slate-900">{{ request.destination_city }} / {{ request.destination_state }}</p>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Período</p>
          <p class="mt-1 text-slate-900">{{ formatDate(request.departure_date) }} a {{ formatDate(request.return_date) }}</p>
        </div>

        <!-- Quantidade e valor -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <CurrencyDollarIcon class="h-5 w-5 text-slate-500" />
            Quantidade e valor
          </h3>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Quantidade de diárias</p>
          <p class="mt-1 text-slate-900">{{ request.quantity_days }}</p>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Valor total</p>
          <p class="mt-1 text-lg font-semibold text-slate-900">{{ formatCurrency(request.total_value) }}</p>
        </div>

        <!-- Motivo -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-6">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <DocumentTextIcon class="h-5 w-5 text-slate-500" />
            Motivo da viagem
          </h3>
          <p class="text-slate-700 whitespace-pre-wrap">{{ request.reason || '—' }}</p>
        </div>
      </div>

      <!-- Assinaturas (conforme usuário logado) -->
      <div class="mt-8 pt-6 border-t border-slate-200">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
          Assinaturas
        </h3>
        <div class="flex flex-wrap gap-2">
          <template v-if="request.status === 'requested' && authStore.can('daily-requests.validate')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'validate'"
              @click="doValidate"
            >
              <CheckIcon class="h-4 w-4" />
              Validar (Secretário)
            </Button>
          </template>
          <template v-if="request.status === 'validated' && authStore.can('daily-requests.authorize')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'authorize'"
              @click="doAuthorize"
            >
              <CheckIcon class="h-4 w-4" />
              Conceder (Prefeito)
            </Button>
          </template>
          <template v-if="request.status === 'authorized' && authStore.can('daily-requests.pay')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'pay'"
              @click="doPay"
            >
              <BanknotesIcon class="h-4 w-4" />
              Pagar (Tesouraria)
            </Button>
          </template>
          <template v-if="request.is_cancellable && authStore.can('daily-requests.cancel')">
            <Button
              type="button"
              variant="outline"
              class="inline-flex items-center gap-2 text-red-700 border-red-200 hover:bg-red-50"
              :loading="actionLoading === 'cancel'"
              @click="doCancel"
            >
              <XMarkIcon class="h-4 w-4" />
              Indeferir
            </Button>
          </template>
          <p v-if="!hasAnySignatureAction" class="text-sm text-slate-500">
            Nenhuma ação de assinatura disponível para você nesta solicitação.
          </p>
        </div>
      </div>

      <!-- Linha do tempo -->
      <div class="mt-8 pt-6 border-t border-slate-200">
        <h3 class="text-sm font-semibold text-slate-800 mb-6 flex items-center gap-2">
          <ClockIcon class="h-5 w-5 text-slate-500" />
          Linha do tempo
        </h3>
        <div v-if="timelineLoading" class="text-sm text-slate-500">Carregando...</div>
        <div v-else-if="timeline.length" class="flex flex-col">
          <div
            v-for="(log, index) in timeline"
            :key="log.id"
            class="flex gap-3 mb-8 last:mb-0"
          >
            <!-- Coluna esquerda: bolinha na ponta da linha + trecho da linha -->
            <div class="relative w-7 shrink-0 flex flex-col items-center">
              <div
                class="relative z-10 w-6 h-6 rounded-full border-2 border-slate-300 bg-white flex items-center justify-center shrink-0"
                :class="index === 0 ? 'border-blue-500 bg-blue-50' : ''"
              >
                <div
                  class="w-2 h-2 rounded-full"
                  :class="index === 0 ? 'bg-blue-500' : 'bg-slate-400'"
                />
              </div>
              <div
                v-if="index < timeline.length - 1"
                class="w-0.5 flex-1 min-h-4 bg-slate-200"
              />
            </div>
            <!-- Card à direita da linha -->
            <div class="flex-1 min-w-0 rounded-lg border border-slate-200 bg-slate-50/50 p-4 shadow-sm">
              <div class="mb-2">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ formatTimelineDate(log.created_at) }}</span>
              </div>
              <p class="font-semibold text-slate-800 mb-3">{{ log.action_label }}</p>
              <div class="space-y-1 text-sm">
                <p v-if="log.user_name" class="text-slate-700">
                  <span class="text-slate-500 font-medium">Responsável:</span> {{ log.user_name }}
                </p>
                <p v-if="log.ip" class="text-slate-500 text-xs">
                  <span class="font-medium">IP:</span> {{ log.ip }}
                </p>
              </div>
              <p v-if="log.metadata?.description" class="mt-3 pt-3 border-t border-slate-200 text-sm text-slate-600 italic">{{ log.metadata.description }}</p>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-slate-500">Nenhum registro na linha do tempo.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency } from '@/utils/format'
import Button from '@/components/Common/Button.vue'
import { useAuthStore } from '@/stores/auth'
import {
  ArrowLeftIcon,
  UserCircleIcon,
  MapPinIcon,
  CurrencyDollarIcon,
  DocumentTextIcon,
  ClockIcon,
  CheckIcon,
  BanknotesIcon,
  XMarkIcon,
  PencilSquareIcon,
  DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { success, error: showError, confirm } = useAlert()
const loading = ref(true)
const request = ref(null)
const actionLoading = ref(null)
const timeline = ref([])
const timelineLoading = ref(false)

const hasAnySignatureAction = computed(() => {
  if (!request.value) return false
  const r = request.value
  return (
    (r.status === 'requested' && authStore.can('daily-requests.validate')) ||
    (r.status === 'validated' && authStore.can('daily-requests.authorize')) ||
    (r.status === 'authorized' && authStore.can('daily-requests.pay')) ||
    (r.is_cancellable && authStore.can('daily-requests.cancel'))
  )
})

const statusClass = computed(() => {
  const s = request.value?.status
  const map = {
    draft: 'bg-gray-100 text-gray-800',
    requested: 'bg-blue-100 text-blue-800',
    validated: 'bg-yellow-100 text-yellow-800',
    authorized: 'bg-green-100 text-green-800',
    paid: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800',
  }
  return map[s] ?? 'bg-slate-100 text-slate-800'
})

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

function formatTimelineDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

async function loadRequest() {
  const id = route.params.id
  if (!id) return
  loading.value = true
  try {
    const { data } = await api.get(`/daily-requests/${id}`)
    request.value = data?.data ?? data
    await fetchTimeline()
  } catch {
    showError('Erro', 'Não foi possível carregar a solicitação.')
    router.push({ name: 'daily-requests.index' })
  } finally {
    loading.value = false
  }
}

async function fetchTimeline() {
  const id = route.params.id
  if (!id) return
  timelineLoading.value = true
  try {
    const { data } = await api.get(`/daily-requests/${id}/timeline`)
    timeline.value = data?.data ?? data ?? []
  } catch {
    timeline.value = []
  } finally {
    timelineLoading.value = false
  }
}

async function openPdf() {
  const id = route.params.id
  if (!id) return
  try {
    const { data } = await api.get(`/daily-requests/${id}/pdf`, { responseType: 'blob' })
    if (data instanceof Blob && data.size > 0) {
      const url = URL.createObjectURL(data)
      window.open(url, '_blank')
    } else {
      showError('Erro', 'PDF não disponível.')
    }
  } catch (e) {
    showError('Erro', e.response?.data?.message || 'Não foi possível abrir o PDF.')
  }
}

async function doValidate() {
  const id = route.params.id
  if (!id) return
  actionLoading.value = 'validate'
  try {
    await api.post(`/daily-requests/${id}/validate`)
    success('Sucesso', 'Solicitação validada.')
    await loadRequest()
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Não foi possível validar.')
  } finally {
    actionLoading.value = null
  }
}

async function doAuthorize() {
  const id = route.params.id
  if (!id) return
  actionLoading.value = 'authorize'
  try {
    await api.post(`/daily-requests/${id}/authorize`)
    success('Sucesso', 'Solicitação concedida.')
    await loadRequest()
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Não foi possível conceder.')
  } finally {
    actionLoading.value = null
  }
}

async function doPay() {
  const id = route.params.id
  if (!id) return
  actionLoading.value = 'pay'
  try {
    await api.post(`/daily-requests/${id}/pay`)
    success('Sucesso', 'Pagamento registrado.')
    await loadRequest()
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Não foi possível registrar o pagamento.')
  } finally {
    actionLoading.value = null
  }
}

async function doCancel() {
  const id = route.params.id
  if (!id) return
  const ok = await confirm('Indeferir solicitação', 'Deseja realmente indeferir/cancelar esta solicitação?')
  if (!ok) return
  actionLoading.value = 'cancel'
  try {
    await api.post(`/daily-requests/${id}/cancel`)
    success('Sucesso', 'Solicitação indeferida.')
    await loadRequest()
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Não foi possível indeferir.')
  } finally {
    actionLoading.value = null
  }
}

onMounted(() => {
  loadRequest()
})
</script>
