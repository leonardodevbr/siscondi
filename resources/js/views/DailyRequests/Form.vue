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

    <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <!-- Formulário à esquerda -->
      <form class="card p-6 overflow-visible" @submit.prevent="handleSubmit">
        <div class="space-y-6 overflow-visible">
          <!-- Servidor e Destino -->
          <div>
            <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
              <UserCircleIcon class="h-5 w-5 text-slate-500" />
              Servidor e destino
            </h3>
            <div class="space-y-4">
              <SelectInput
                v-model="form.servant_id"
                label="Servidor *"
                :options="servantOptions"
                placeholder="Selecione o servidor..."
                :searchable="true"
                @update:model-value="onServantChange"
              />

              <div>
                <SelectInput
                  v-model="form.destination_type"
                  label="Tipo de destino *"
                  :options="destinationOptions"
                  placeholder="Selecione o tipo de destino..."
                  :searchable="allDestinationTypes.length > 10"
                />
                <p v-if="selectedServant && form.destination_type && unitValueForDestination" class="mt-1 text-xs text-slate-500">
                  Valor da diária para este destino: <span class="font-semibold text-slate-800">{{ formatCurrency(unitValueForDestination) }}</span>
                </p>
                <p v-else-if="selectedServant && form.destination_type && !unitValueForDestination" class="mt-1 text-xs text-amber-600">
                  Servidor sem valor de diária definido para este destino na legislação.
                </p>
              </div>
            </div>
          </div>

          <!-- Local e período -->
          <div class="border-t border-slate-200 pt-6 overflow-visible">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
              <MapPinIcon class="h-5 w-5 text-slate-500" />
              Local e período
            </h3>
            <div class="space-y-4 overflow-visible">
              <SelectInput
                v-model="form.destination_state"
                label="Estado (UF) *"
                :options="stateOptions"
                placeholder="Selecione o estado..."
                :searchable="true"
                @update:model-value="onStateChange"
              />

              <SelectInput
                v-model="form.destination_city"
                label="Cidade de destino *"
                :options="cityOptions"
                placeholder="Selecione a cidade..."
                :searchable="true"
                :disabled="!form.destination_state || loadingCities"
              />

              <DateRangePicker
                label="Período (saída e retorno) *"
                :departure-date="form.departure_date"
                :return-date="form.return_date"
                required
                placeholder="Selecione as datas"
                @update:departure-date="form.departure_date = $event; calculateDays()"
                @update:return-date="form.return_date = $event; calculateDays()"
              />
            </div>
          </div>

          <!-- Quantidade e valor -->
          <div class="border-t border-slate-200 pt-6">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
              <CurrencyDollarIcon class="h-5 w-5 text-slate-500" />
              Quantidade e valor
            </h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Quantidade de diárias *</label>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="flex items-center justify-center w-10 h-10 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 active:bg-slate-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.quantity_days <= 0.5"
                    title="Diminuir 0,5 diária"
                    @click="decrementDays"
                  >
                    <MinusIcon class="h-5 w-5 text-slate-700" />
                  </button>
                  <input
                    v-model.number="form.quantity_days"
                    type="number"
                    step="0.5"
                    min="0.5"
                    required
                    class="input-base flex-1 text-center font-semibold"
                    @change="updateDatesFromQuantity"
                  />
                  <button
                    type="button"
                    class="flex items-center justify-center w-10 h-10 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 active:bg-slate-100 transition-colors"
                    title="Aumentar 0,5 diária"
                    @click="incrementDays"
                  >
                    <PlusIcon class="h-5 w-5 text-slate-700" />
                  </button>
                </div>
                <p class="mt-1 text-xs text-slate-500">Use os botões ou digite o valor (aceita 0,5 diária)</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Valor total previsto</label>
                <div class="input-base w-full bg-slate-50 text-slate-900 font-semibold flex items-center min-h-[42px]">
                  {{ formatCurrency(calculatedTotal) }}
                </div>
                <p v-if="form.servant_id && form.destination_type" class="mt-1 text-xs text-slate-500">
                  <template v-if="unitValueForDestination">
                    {{ formatCurrency(unitValueForDestination) }} × {{ formatQuantityDays(form.quantity_days) }} diária(s) = {{ formatCurrency(calculatedTotal) }}
                  </template>
                  <template v-else>
                    Selecione um servidor com valor de diária definido para este destino para ver o total.
                  </template>
                </p>
              </div>
            </div>
          </div>

          <!-- Finalidade e Motivo -->
          <div class="border-t border-slate-200 pt-6">
            <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
              <DocumentTextIcon class="h-5 w-5 text-slate-500" />
              Finalidade e Motivo
            </h3>
            <div class="space-y-4">
              <div>
                <Input
                  v-model="form.purpose"
                  label="Finalidade"
                  placeholder="Ex: Custeio de despesas com locomoção, hospedagem e alimentação."
                />
                <p class="mt-1 text-xs text-slate-500">Objetivo formal da diária (aparece no PDF).</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Motivo da viagem *</label>
                <textarea
                  v-model="form.reason"
                  rows="4"
                  required
                  placeholder="Descreva o motivo da viagem..."
                  class="input-base w-full resize-y"
                />
                <p class="mt-1 text-xs text-slate-500">Descrição detalhada do motivo da viagem.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Ações de assinatura (apenas na visualização/detalhes) -->
        <div v-if="isEdit && requestDetail" class="mt-8 pt-6 border-t border-slate-200">
          <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            Assinaturas
          </h3>
          <div class="flex flex-wrap gap-2">
            <template v-if="requestDetail.status === 'requested' && authStore.can('daily-requests.validate')">
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
            <template v-if="requestDetail.status === 'validated' && authStore.can('daily-requests.authorize')">
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
            <template v-if="requestDetail.status === 'authorized' && authStore.can('daily-requests.pay')">
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
            <template v-if="requestDetail.is_cancellable && authStore.can('daily-requests.cancel')">
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

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
          <Button type="button" variant="outline" @click="$router.push({ name: 'daily-requests.index' })">
            Cancelar
          </Button>
          <Button type="submit" :loading="saving">
            {{ isEdit ? 'Atualizar solicitação' : 'Enviar solicitação' }}
          </Button>
        </div>

        <!-- Linha do tempo (auditoria) – visível na edição -->
        <div v-if="isEdit && route.params.id" class="mt-8 pt-6 border-t border-slate-200">
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
      </form>

      <!-- Espaço reservado para preview (à direita) -->
      <div class="hidden xl:block">
        <div class="card p-6 h-full flex items-center justify-center bg-slate-50">
          <div class="text-center text-slate-400">
            <DocumentTextIcon class="h-16 w-16 mx-auto mb-3 opacity-50" />
            <p class="text-sm font-medium">Preview em desenvolvimento</p>
            <p class="text-xs mt-1">Em breve você poderá visualizar<br />o PDF aqui ao lado</p>
          </div>
        </div>
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

        <!-- Preview da assinatura do usuário logado (quando tiver imagem cadastrada) -->
        <div class="rounded-lg border border-slate-200 p-3 bg-slate-50/80">
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Sua assinatura</p>
          <div v-if="authStore.user?.signature_url" class="flex items-center flex-col gap-3">
            <img
              :src="authStore.user.signature_url"
              alt="Sua assinatura"
              class="max-h-16 object-contain bg-white p-1 w-full border border-slate-100 rounded shadow-sm"
            />
            <p class="text-[10px] text-slate-400 text-center uppercase font-bold tracking-wider">Esta imagem será registrada no documento</p>
          </div>
          <p v-else class="text-sm text-slate-500">
            Você não tem imagem de assinatura cadastrada. 
            <router-link v-if="authStore.can('users.edit')" :to="{ name: 'users.index' }" class="text-blue-600 hover:underline font-medium">Cadastrar assinatura</router-link>
          </p>
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
            @input="signModalPin = signModalPin.replace(/[^0-9]/g, '')"
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
            Confirmar e Assinar
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAlert } from '@/composables/useAlert'
import { formatCurrency, formatQuantityDays } from '@/utils/format'
import Input from '@/components/Common/Input.vue'
import Button from '@/components/Common/Button.vue'
import SelectInput from '@/components/Common/SelectInput.vue'
import DateRangePicker from '@/components/Common/DateRangePicker.vue'
import Modal from '@/components/Common/Modal.vue'
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
  MinusIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { success, error: showError, confirm } = useAlert()
const isEdit = computed(() => !!route.params.id && route.params.id !== 'create')
const loading = ref(false)
const saving = ref(false)
const actionLoading = ref(null)
const requestDetail = ref(null)
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
  const id = route.params.id
  const a = signModalAction.value
  if (a === 'validate') return `Solicitação #${id ?? '—'} — Validar. Revise os dados e confirme a ação.`
  if (a === 'authorize') return `Solicitação #${id ?? '—'} — Conceder. Revise os dados e confirme a ação.`
  if (a === 'pay') return `Solicitação #${id ?? '—'} — Registrar pagamento. Revise os dados e confirme a ação.`
  return ''
})

const hasAnySignatureAction = computed(() => {
  if (!requestDetail.value) return false
  const r = requestDetail.value
  return (
    (r.status === 'requested' && authStore.can('daily-requests.validate')) ||
    (r.status === 'validated' && authStore.can('daily-requests.authorize')) ||
    (r.status === 'authorized' && authStore.can('daily-requests.pay')) ||
    (r.is_cancellable && authStore.can('daily-requests.cancel'))
  )
})

const form = ref({
  servant_id: null,
  destination_type: '',
  destination_city: '',
  destination_state: '',
  departure_date: '',
  return_date: '',
  quantity_days: 1,
  purpose: 'Custeio de despesas com locomoção, hospedagem e alimentação.',
  reason: ''
})

const servants = ref([])
const allDestinationTypes = ref([])
const cities = ref([])
const loadingCities = ref(false)

// Lista fixa de estados brasileiros
const states = [
  { sigla: 'AC', nome: 'Acre' },
  { sigla: 'AL', nome: 'Alagoas' },
  { sigla: 'AP', nome: 'Amapá' },
  { sigla: 'AM', nome: 'Amazonas' },
  { sigla: 'BA', nome: 'Bahia' },
  { sigla: 'CE', nome: 'Ceará' },
  { sigla: 'DF', nome: 'Distrito Federal' },
  { sigla: 'ES', nome: 'Espírito Santo' },
  { sigla: 'GO', nome: 'Goiás' },
  { sigla: 'MA', nome: 'Maranhão' },
  { sigla: 'MT', nome: 'Mato Grosso' },
  { sigla: 'MS', nome: 'Mato Grosso do Sul' },
  { sigla: 'MG', nome: 'Minas Gerais' },
  { sigla: 'PA', nome: 'Pará' },
  { sigla: 'PB', nome: 'Paraíba' },
  { sigla: 'PR', nome: 'Paraná' },
  { sigla: 'PE', nome: 'Pernambuco' },
  { sigla: 'PI', nome: 'Piauí' },
  { sigla: 'RJ', nome: 'Rio de Janeiro' },
  { sigla: 'RN', nome: 'Rio Grande do Norte' },
  { sigla: 'RS', nome: 'Rio Grande do Sul' },
  { sigla: 'RO', nome: 'Rondônia' },
  { sigla: 'RR', nome: 'Roraima' },
  { sigla: 'SC', nome: 'Santa Catarina' },
  { sigla: 'SP', nome: 'São Paulo' },
  { sigla: 'SE', nome: 'Sergipe' },
  { sigla: 'TO', nome: 'Tocantins' },
]

const servantOptions = computed(() =>
  servants.value.map((s) => {
    const positionLabel = s.position
      ? ` — ${s.position.name || s.position.symbol}`
      : ''
    return {
      value: s.id,
      label: `${s.matricula} - ${s.name}${positionLabel}`,
    }
  })
)

const selectedServant = computed(() => {
  const id = form.value.servant_id
  if (id == null || id === '') return undefined
  return servants.value.find((s) => Number(s.id) === Number(id))
})

const destinationOptions = computed(() =>
  allDestinationTypes.value.map((label) => ({ value: label, label }))
)

const stateOptions = computed(() =>
  states.map((state) => ({ value: state.sigla, label: `${state.sigla} - ${state.nome}` }))
)

const cityOptions = computed(() =>
  cities.value.map((city) => ({ value: city.nome, label: city.nome }))
)

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

async function fetchDestinationTypes() {
  try {
    const { data } = await api.get('/legislations/destination-types')
    const list = data?.data ?? data ?? []
    allDestinationTypes.value = Array.isArray(list) ? list : []
  } catch (e) {
    console.error('Erro ao carregar tipos de destino:', e)
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
    const diffTime = end - start
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    // Se for o mesmo dia (0 dias), considera 1 diária
    form.value.quantity_days = diffDays >= 0 ? (diffDays === 0 ? 1 : diffDays) : 1
  }
}

function incrementDays() {
  const currentQuantity = form.value.quantity_days
  
  // Adiciona 0.5 à quantidade
  const newQuantity = Number((currentQuantity + 0.5).toFixed(1))
  form.value.quantity_days = newQuantity
  
  // Se a NOVA quantidade é número inteiro, incrementa a data de retorno
  if (newQuantity % 1 === 0 && form.value.return_date) {
    console.log('AEEE - Incremento virou inteiro!')
    console.log('Data de retorno ANTES:', form.value.return_date)
    
    // Cria nova data baseada na data de retorno atual
    const returnDate = new Date(form.value.return_date + 'T00:00:00')
    console.log('Data parseada:', returnDate)
    
    // Adiciona 1 dia
    returnDate.setDate(returnDate.getDate() + 1)
    console.log('Data após +1 dia:', returnDate)
    
    const year = returnDate.getFullYear()
    const month = String(returnDate.getMonth() + 1).padStart(2, '0')
    const day = String(returnDate.getDate()).padStart(2, '0')
    const newReturnDate = `${year}-${month}-${day}`
    
    console.log('Nova data formatada:', newReturnDate)
    form.value.return_date = newReturnDate
    
    console.log('Data de retorno DEPOIS:', form.value.return_date)
  }
}

function decrementDays() {
  if (form.value.quantity_days <= 0.5) return
  
  const currentQuantity = form.value.quantity_days
  
  // Remove 0.5 da quantidade
  const newQuantity = Number((currentQuantity - 0.5).toFixed(1))
  form.value.quantity_days = newQuantity
  
  // Se a NOVA quantidade é número inteiro, decrementa a data de retorno
  if (newQuantity % 1 === 0 && form.value.return_date && form.value.departure_date) {
    console.log('AEEE - Decremento virou inteiro!')
    
    const returnDate = new Date(form.value.return_date)
    const departureDate = new Date(form.value.departure_date)
    
    // Calcula a nova data (1 dia antes)
    const newReturnDate = new Date(returnDate)
    newReturnDate.setDate(returnDate.getDate() - 1)
    
    // Só atualiza se a nova data for >= data de partida
    if (newReturnDate >= departureDate) {
      const year = newReturnDate.getFullYear()
      const month = String(newReturnDate.getMonth() + 1).padStart(2, '0')
      const day = String(newReturnDate.getDate()).padStart(2, '0')
      form.value.return_date = `${year}-${month}-${day}`
      
      console.log('Data de retorno DECREMENTADA para:', form.value.return_date)
    } else {
      console.log('Data de retorno mantida (limite = data de partida):', form.value.return_date)
    }
  }
}

function updateDatesFromQuantity() {
  // Só atualiza as datas se a data de partida estiver preenchida
  if (!form.value.departure_date) return
  
  const quantity = form.value.quantity_days
  const startDate = new Date(form.value.departure_date)
  
  // Verifica se é meia diária (tem decimal .5)
  const isHalfDay = quantity % 1 !== 0
  
  // Calcula quantos dias inteiros
  const fullDays = Math.floor(quantity)
  
  // Para 0.5 ou 1 diária = mesmo dia
  // Para 1.5 ou 2 diárias = 1 dia depois
  // Para 2.5 ou 3 diárias = 2 dias depois
  const daysToAdd = isHalfDay ? fullDays : Math.max(0, fullDays - 1)
  
  const returnDate = new Date(startDate)
  returnDate.setDate(startDate.getDate() + daysToAdd)
  
  // Formata para YYYY-MM-DD
  const year = returnDate.getFullYear()
  const month = String(returnDate.getMonth() + 1).padStart(2, '0')
  const day = String(returnDate.getDate()).padStart(2, '0')
  form.value.return_date = `${year}-${month}-${day}`
}

async function fetchCities(uf) {
  if (!uf) {
    cities.value = []
    return
  }
  loadingCities.value = true
  try {
    const response = await fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`)
    cities.value = await response.json()
  } catch (e) {
    console.error('Erro ao carregar cidades:', e)
    cities.value = []
  } finally {
    loadingCities.value = false
  }
}

function onStateChange(uf) {
  form.value.destination_city = ''
  cities.value = []
  if (uf) {
    fetchCities(uf)
  }
}

async function loadRequest() {
  if (!route.params.id || route.params.id === 'create') return
  loading.value = true
  try {
    const { data } = await api.get(`/daily-requests/${route.params.id}`)
    const r = data?.data ?? data
    requestDetail.value = r
    form.value = {
      servant_id: r.servant_id ?? null,
      destination_type: r.destination_type ?? '',
      destination_city: r.destination_city ?? '',
      destination_state: r.destination_state ?? '',
      departure_date: r.departure_date ? r.departure_date.slice(0, 10) : '',
      return_date: r.return_date ? r.return_date.slice(0, 10) : '',
      quantity_days: Number(r.quantity_days) ?? 1,
      purpose: r.purpose ?? 'Custeio de despesas com locomoção, hospedagem e alimentação.',
      reason: r.reason ?? '',
    }
    // Carregar cidades do estado se já tiver estado selecionado
    if (form.value.destination_state) {
      await fetchCities(form.value.destination_state)
    }
    if (isEdit.value) {
      fetchTimeline()
    }
  } catch (e) {
    showError('Erro', 'Não foi possível carregar a solicitação.')
    router.push({ name: 'daily-requests.index' })
  } finally {
    loading.value = false
  }
}

async function fetchTimeline() {
  if (!route.params.id || route.params.id === 'create') return
  timelineLoading.value = true
  try {
    const { data } = await api.get(`/daily-requests/${route.params.id}/timeline`)
    timeline.value = data?.data ?? data ?? []
  } catch {
    timeline.value = []
  } finally {
    timelineLoading.value = false
  }
}

function formatTimelineDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
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
    const msg = err.response?.data?.message ?? (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(' ') : 'Não foi possível concluir a ação.')
    showError('Erro', msg)
  } finally {
    actionLoading.value = null
  }
}

async function doCancel() {
  if (!route.params.id) return
  const ok = await confirm('Indeferir solicitação', 'Deseja realmente indeferir/cancelar esta solicitação?')
  if (!ok) return
  actionLoading.value = 'cancel'
  try {
    await api.post(`/daily-requests/${route.params.id}/cancel`)
    success('Sucesso', 'Solicitação indeferida.')
    await loadRequest()
  } catch (err) {
    showError('Erro', err.response?.data?.message || 'Não foi possível indeferir.')
  } finally {
    actionLoading.value = null
  }
}

async function handleSubmit() {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (isEdit.value) {
      await api.post(`/daily-requests/${route.params.id}/update`, payload)
      success('Sucesso', 'Solicitação atualizada.')
      await loadRequest()
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
  await Promise.all([fetchServants(), fetchDestinationTypes()])
  if (isEdit.value) {
    await loadRequest()
  }
})
</script>
