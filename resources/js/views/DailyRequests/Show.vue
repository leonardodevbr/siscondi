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
          :disabled="pdfLoading"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-70 disabled:cursor-not-allowed"
          @click="openPdf"
        >
          <span v-if="pdfLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
          <DocumentArrowDownIcon v-else class="h-4 w-4" />
          {{ pdfLoading ? 'Gerando PDF...' : 'Ver PDF' }}
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
          <p class="mt-1 text-slate-900">
            {{ request.servant?.name ?? '—' }}
            <span v-if="request.servant?.position" class="text-slate-500 text-xs">
              ({{ request.servant.position.name || request.servant.position.symbol }})
            </span>
          </p>
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
          <p class="mt-1 text-slate-900">{{ formatQuantityDays(request.quantity_days) }}</p>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Valor total</p>
          <p class="mt-1 text-lg font-semibold text-slate-900">{{ formatCurrency(request.total_value) }}</p>
        </div>

        <!-- Finalidade e Motivo -->
        <div class="lg:col-span-2 border-t border-slate-200 pt-4 space-y-2">
          <p class="text-sm text-slate-700">
            <span class="font-medium text-slate-800">Finalidade:</span>
            {{ request.purpose || 'Custeio de despesas com locomoção, hospedagem e alimentação.' }}
          </p>
          <p class="text-sm text-slate-700">
            <span class="font-medium text-slate-800">Motivo da viagem:</span>
            {{ request.reason || '—' }}
          </p>
        </div>
      </div>

      <!-- Assinaturas: detalhes de quem assinou + ações -->
      <div class="mt-8 pt-6 border-t border-slate-200">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
          Assinaturas
        </h3>

        <!-- Resumo das três assinaturas: Requerente, Validador, Concedente. Tesouraria só dá baixa e registra na linha do tempo, sem assinatura. -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div
            class="rounded-lg border p-4 transition-all duration-300"
            :class="request.requester_id
              ? 'bg-emerald-50 border-emerald-200 shadow-sm'
              : 'bg-slate-50/50 border-slate-200'"
          >
            <p
              class="text-xs font-bold uppercase tracking-wider mb-2"
              :class="request.requester_id ? 'text-emerald-700' : 'text-slate-500'"
            >
              Requerente
            </p>
            <template v-if="request.requester">
              <p class="font-bold text-slate-800">{{ request.requester.name }}</p>
              <p class="text-[10px] font-medium text-slate-500 mt-0.5">{{ formatDateTime(request.created_at) }}</p>
              <div v-if="request.requester.signature_url" class="mt-3 p-2 bg-white rounded border border-emerald-100/50">
                <img
                  :src="request.requester.signature_url"
                  alt="Assinatura do requerente"
                  class="max-h-12 w-full object-contain"
                />
              </div>
            </template>
            <template v-else>
              <p class="text-sm text-slate-500">—</p>
              <p v-if="request.created_at" class="text-[10px] text-slate-500 mt-0.5">{{ formatDateTime(request.created_at) }}</p>
            </template>
          </div>
          <div
            class="rounded-lg border p-4 transition-all duration-300"
            :class="request.validator_id
              ? 'bg-emerald-50 border-emerald-200 shadow-sm'
              : 'bg-slate-50/50 border-slate-200'"
          >
            <p
              class="text-xs font-bold uppercase tracking-wider mb-2"
              :class="request.validator_id ? 'text-emerald-700' : 'text-slate-500'"
            >
              Validador
            </p>
            <template v-if="request.validator">
              <p class="font-bold text-slate-800">{{ request.validator.name }}</p>
              <p class="text-[10px] font-medium text-slate-500 mt-0.5">{{ formatDateTime(request.validated_at) }}</p>
              <div v-if="request.validator.signature_url" class="mt-3 p-2 bg-white rounded border border-emerald-100/50">
                <img
                  :src="request.validator.signature_url"
                  alt="Assinatura do validador"
                  class="max-h-12 w-full object-contain"
                />
              </div>
            </template>
            <p v-else class="text-sm text-slate-400 italic">Pendente</p>
          </div>
          <div
            class="rounded-lg border p-4 transition-all duration-300"
            :class="request.authorizer_id
              ? 'bg-emerald-50 border-emerald-200 shadow-sm'
              : 'bg-slate-50/50 border-slate-200'"
          >
            <p
              class="text-xs font-bold uppercase tracking-wider mb-2"
              :class="request.authorizer_id ? 'text-emerald-700' : 'text-slate-500'"
            >
              Concedente
            </p>
            <template v-if="request.authorizer">
              <p class="font-bold text-slate-800">{{ request.authorizer.name }}</p>
              <p class="text-[10px] font-medium text-slate-500 mt-0.5">{{ formatDateTime(request.authorized_at) }}</p>
              <div v-if="request.authorizer.signature_url" class="mt-3 p-2 bg-white rounded border border-emerald-100/50">
                <img
                  :src="request.authorizer.signature_url"
                  alt="Assinatura do concedente"
                  class="max-h-12 w-full object-contain"
                />
              </div>
            </template>
            <p v-else class="text-sm text-slate-400 italic">Pendente</p>
          </div>
        </div>

        <!-- Ações de assinatura -->
        <div class="flex flex-wrap gap-2">
          <template v-if="request.status === 'requested' && authStore.can('daily-requests.validate')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'validate'"
              @click="openSignModal('validate')"
            >
              <CheckIcon class="h-4 w-4" />
              Validar
            </Button>
          </template>
          <template v-if="request.status === 'validated' && authStore.can('daily-requests.authorize')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'authorize'"
              @click="openSignModal('authorize')"
            >
              <CheckIcon class="h-4 w-4" />
              Conceder
            </Button>
          </template>
          <template v-if="request.status === 'authorized' && authStore.can('daily-requests.pay')">
            <Button
              type="button"
              class="inline-flex items-center gap-2"
              :loading="actionLoading === 'pay'"
              @click="openSignModal('pay')"
            >
              <BanknotesIcon class="h-4 w-4" />
              Pagar
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

      <!-- Modal de confirmação para assinar (senha/PIN + preview da assinatura) -->
      <Modal
        :is-open="signModalOpen"
        :title="signModalTitle"
        :closable="!actionLoading"
        @close="closeSignModal"
      >
        <div class="space-y-4">
          <p class="text-slate-600">{{ signModalSummary }}</p>

          <!-- Resumo detalhado da solicitação -->
          <div v-if="request" class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 text-sm">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Resumo da solicitação</p>
            <dl class="grid grid-cols-1 gap-2">
              <div>
                <dt class="text-slate-500">Servidor</dt>
                <dd class="font-medium text-slate-800">{{ request.servant?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Destino</dt>
                <dd class="font-medium text-slate-800">{{ request.destination_type ?? '—' }} — {{ request.destination_city ?? '' }} / {{ request.destination_state ?? '' }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Período</dt>
                <dd class="font-medium text-slate-800">{{ formatDate(request.departure_date) }} a {{ formatDate(request.return_date) }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Quantidade de diárias</dt>
                <dd class="font-medium text-slate-800">{{ formatQuantityDays(request.quantity_days) }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Valor total</dt>
                <dd class="font-semibold text-slate-800">{{ formatCurrency(request.total_value) }}</dd>
              </div>
              <div v-if="request.reason">
                <dt class="text-slate-500 mb-0.5">Motivo</dt>
                <dd class="text-slate-700 text-xs leading-relaxed line-clamp-3">{{ request.reason }}</dd>
              </div>
            </dl>
          </div>

          <!-- Preview da assinatura do usuário logado (quando tiver imagem cadastrada) -->
          <div class="rounded-lg border border-slate-200 p-3 bg-slate-50/80">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Sua assinatura</p>
            <div v-if="authStore.user?.signature_url" class="flex items-center flex-col gap-3">
              <img
                :src="authStore.user.signature_url"
                alt="Sua assinatura"
                class="max-h-16 object-contain bg-white p-1 w-full border border-slate-100 rounded"
              />
              <p class="text-xs text-slate-500">Esta imagem será registrada no documento ao confirmar.</p>
            </div>
            <p v-else class="text-sm text-slate-500">Você não tem imagem de assinatura cadastrada. Para incluir nos documentos, cadastre em Usuários (edição do seu usuário). <router-link v-if="authStore.can('users.edit')" :to="{ name: 'users.index' }" class="text-blue-600 hover:underline">Ir para Usuários</router-link></p>
          </div>

          <!-- Senha e PIN quando o usuário tem cadastrado -->
          <template v-if="authStore.user?.requires_operation_credentials_to_sign">
            <Input
              v-if="authStore.user?.has_operation_pin"
              v-model="signModalPin"
              label="PIN de autorização"
              type="text"
              inputmode="numeric"
              placeholder="Informe seu PIN"
              autocomplete="off"
            />
            <Input
              v-if="authStore.user?.has_operation_password"
              v-model="signModalPassword"
              label="Senha de operação"
              type="password"
              placeholder="Informe sua senha de operação"
              autocomplete="off"
            />
          </template>

          <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" :disabled="actionLoading" @click="closeSignModal">
              Cancelar
            </Button>
            <Button :loading="actionLoading" @click="confirmSign">
              Confirmar
            </Button>
          </div>
        </div>
      </Modal>

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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency, formatQuantityDays } from '@/utils/format'
import Button from '@/components/Common/Button.vue'
import Input from '@/components/Common/Input.vue'
import Modal from '@/components/Common/Modal.vue'
import { useAuthStore } from '@/stores/auth'
import { getEcho } from '@/echo'
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
const pdfLoading = ref(false)
const timeline = ref([])
const timelineLoading = ref(false)

const signModalOpen = ref(false)
const signModalAction = ref(null)
const signModalPin = ref('')
const signModalPassword = ref('')

const signModalTitle = computed(() => {
  const a = signModalAction.value
  if (a === 'validate') return 'Confirmar validação'
  if (a === 'authorize') return 'Confirmar concessão'
  if (a === 'pay') return 'Confirmar pagamento'
  return 'Confirmar'
})

const signModalSummary = computed(() => {
  const id = request.value?.id
  const a = signModalAction.value
  if (a === 'validate') return `Solicitação #${id ?? '—'} — Validar. Revise os dados e confirme a ação.`
  if (a === 'authorize') return `Solicitação #${id ?? '—'} — Conceder. Revise os dados e confirme a ação.`
  if (a === 'pay') return `Solicitação #${id ?? '—'} — Registrar pagamento. Revise os dados e confirme a ação.`
  return ''
})

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

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function formatTimelineDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function openSignModal(action) {
  signModalAction.value = action
  signModalPin.value = ''
  signModalPassword.value = ''
  signModalOpen.value = true
}

function closeSignModal() {
  signModalOpen.value = false
  signModalAction.value = null
  signModalPin.value = ''
  signModalPassword.value = ''
}

async function confirmSign() {
  const action = signModalAction.value
  const id = route.params.id
  if (!id || !action) return

  const needsCreds = authStore.user?.requires_operation_credentials_to_sign
  if (needsCreds) {
    if (authStore.user?.has_operation_pin && !signModalPin.value?.trim()) {
      showError('Campo obrigatório', 'Informe seu PIN de autorização.')
      return
    }
    if (authStore.user?.has_operation_password && !signModalPassword.value) {
      showError('Campo obrigatório', 'Informe sua senha de operação.')
      return
    }
  }

  actionLoading.value = action
  try {
    const payload = {}
    if (authStore.user?.has_operation_pin && signModalPin.value?.trim()) payload.operation_pin = signModalPin.value.trim()
    if (authStore.user?.has_operation_password && signModalPassword.value) payload.operation_password = signModalPassword.value

    const url = `/daily-requests/${id}/${action === 'validate' ? 'validate' : action === 'authorize' ? 'authorize' : 'pay'}`
    await api.post(url, payload)

    if (action === 'validate') success('Sucesso', 'Solicitação validada.')
    else if (action === 'authorize') success('Sucesso', 'Solicitação concedida.')
    else success('Sucesso', 'Pagamento registrado.')

    closeSignModal()
    await loadRequest()
  } catch (err) {
    const msg = err.response?.data?.message ?? err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : 'Não foi possível concluir a ação.'
    showError('Erro', msg)
  } finally {
    actionLoading.value = null
  }
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
  if (!id || pdfLoading.value) return
  pdfLoading.value = true
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
  } finally {
    pdfLoading.value = false
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

const CHANNEL_NAME = 'private-daily-requests-pending'

onMounted(() => {
  loadRequest()

  if (authStore.can('daily-requests.view')) {
    const echo = getEcho()
    if (echo) {
      echo.private('daily-requests-pending').listen('.pending-signatures.updated', () => {
        loadRequest()
      })
    }
  }
})

onUnmounted(() => {
  const echo = getEcho()
  if (echo) {
    echo.leave(CHANNEL_NAME)
  }
})
</script>
