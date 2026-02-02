<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Servidores Públicos</h1>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          @click="openImportModal"
        >
          <ArrowUpTrayIcon class="h-5 w-5" />
          Importar Planilha
        </button>
        <router-link
          to="/servants/create"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Novo Servidor
        </router-link>
      </div>
    </div>

    <!-- Barra de progresso FORA do modal: só quando modal fechado -->
    <div
      v-if="importProgress && !importModalOpen"
      class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
    >
      <div class="flex items-center justify-between text-sm">
        <span class="font-medium text-slate-700">{{ importProgress.message }}</span>
        <div class="flex items-center gap-3">
          <span class="text-slate-600">{{ importProgress.progress }}%</span>
          <button
            v-if="importProgress.progress === 100"
            type="button"
            class="text-xs px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded transition-colors"
            @click="clearImportProgress"
          >
            Fechar
          </button>
        </div>
      </div>
      <div class="mt-2 w-full overflow-hidden rounded-full bg-slate-200">
        <div
          class="h-2.5 rounded-full transition-all duration-300"
          :class="{
            'bg-blue-600': importProgress.status === 'processing',
            'bg-green-600': importProgress.status === 'completed',
            'bg-red-600': importProgress.status === 'error'
          }"
          :style="{ width: importProgress.progress + '%' }"
        />
      </div>
      <div v-if="importProgress.processed" class="mt-1 text-center text-xs text-slate-500">
        {{ importProgress.created }} criados • {{ importProgress.updated }} atualizados
      </div>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome, CPF ou matrícula..."
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="debouncedSearch"
        />
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
              <td class="px-6 py-4 text-sm text-gray-900">{{ servant.position ? (servant.position.name ? `${servant.position.name} (${servant.position.symbol})` : servant.position.symbol) : '–' }}</td>
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

      <!-- Paginação -->
      <PaginationBar
        v-if="pagination"
        :pagination="pagination"
        @page-change="(page) => fetchServants({ page })"
        @per-page-change="onPerPageChange"
      />
    </div>

    <!-- Modal de Importação -->
    <Modal :is-open="importModalOpen" title="Importar Servidores" @close="closeImportModal">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">
          Faça upload de uma planilha (.xlsx, .xls ou .csv) com os dados dos servidores. Se já existir um servidor com o mesmo nome, os dados serão atualizados.
        </p>

        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-xs font-medium text-blue-800 mb-2">Instruções:</p>
          <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
            <li>Baixe o modelo de planilha e preencha com os dados dos servidores.</li>
            <li>Colunas obrigatórias: Nome Completo, CPF, RG, Órgão Expeditor, Matrícula, ID Cargo/Posição, ID Secretaria.</li>
            <li>Para saber os IDs de cargos e secretarias, consulte as respectivas telas.</li>
            <li>Faça upload da planilha preenchida para validar antes de importar.</li>
          </ol>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100"
            @click="downloadTemplate"
          >
            <ArrowDownTrayIcon class="h-4 w-4" />
            Baixar Modelo
          </button>
        </div>

        <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center">
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx,.xls,.csv"
            class="hidden"
            @change="handleFileChange"
          />
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
            @click="$refs.fileInput.click()"
          >
            <DocumentArrowUpIcon class="h-5 w-5" />
            Selecionar Arquivo
          </button>
          <p v-if="selectedFile" class="mt-2 text-sm text-slate-600">{{ selectedFile.name }}</p>
        </div>

        <div v-if="validationResult" class="space-y-3">
          <div class="p-3 rounded-lg" :class="validationResult.errors.length > 0 ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
            <p class="text-sm font-medium" :class="validationResult.errors.length > 0 ? 'text-red-800' : 'text-green-800'">
              {{ validationResult.summary.total_rows }} linhas processadas:
              <span class="font-bold">{{ validationResult.summary.to_create }}</span> novos,
              <span class="font-bold">{{ validationResult.summary.to_update }}</span> atualizações,
              <span v-if="validationResult.summary.errors_count > 0" class="font-bold text-red-600">{{ validationResult.summary.errors_count }} erros</span>
            </p>
          </div>

          <div v-if="validationResult.errors.length > 0" class="max-h-48 overflow-y-auto border border-red-200 rounded-lg bg-red-50/50">
            <div v-for="(err, idx) in validationResult.errors" :key="idx" class="p-2 text-sm border-b border-red-100 last:border-b-0">
              <p class="font-medium text-red-800">Linha {{ err.line }}: {{ err.name }}</p>
              <ul class="list-disc list-inside text-xs text-red-600 ml-2">
                <li v-for="(msg, i) in err.errors" :key="i">{{ msg }}</li>
              </ul>
            </div>
          </div>

          <div v-else class="max-h-60 overflow-y-auto border border-slate-200 rounded-lg">
            <table class="min-w-full text-xs">
              <thead class="bg-slate-50 sticky top-0">
                <tr>
                  <th class="px-2 py-1 text-left text-slate-600">Linha</th>
                  <th class="px-2 py-1 text-left text-slate-600">Nome</th>
                  <th class="px-2 py-1 text-left text-slate-600">Ação</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(item, idx) in validationResult.preview" :key="idx">
                  <td class="px-2 py-1 text-slate-500">{{ item.line }}</td>
                  <td class="px-2 py-1 text-slate-900">{{ item.name }}</td>
                  <td class="px-2 py-1">
                    <span v-if="item.action === 'created'" class="text-green-600 font-medium">Criar</span>
                    <span v-else-if="item.action === 'updated'" class="text-blue-600 font-medium">Atualizar</span>
                    <span v-else class="text-red-600 font-medium">Erro</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Barra de progresso DENTRO do modal: só quando modal aberto -->
        <div v-if="importProgress && importModalOpen" class="space-y-2 pt-2">
          <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-slate-700">{{ importProgress.message }}</span>
            <span class="text-slate-600">{{ importProgress.progress }}%</span>
          </div>
          <div class="w-full overflow-hidden rounded-full bg-slate-200">
            <div
              class="h-2.5 rounded-full transition-all duration-300"
              :class="{
                'bg-blue-600': importProgress.status === 'processing',
                'bg-green-600': importProgress.status === 'completed',
                'bg-red-600': importProgress.status === 'error'
              }"
              :style="{ width: importProgress.progress + '%' }"
            />
          </div>
          <div v-if="importProgress.processed" class="text-center text-xs text-slate-500">
            {{ importProgress.created }} criados • {{ importProgress.updated }} atualizados
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <Button 
            type="button" 
            variant="outline" 
            :disabled="importing || (importProgress && importProgress.status === 'processing')"
            @click="closeImportModal"
          >
            {{ (importing || (importProgress && importProgress.status === 'processing')) ? 'Processando...' : 'Fechar' }}
          </Button>
          <Button
            v-if="!validationResult && !importProgress"
            :disabled="!selectedFile"
            :loading="validating"
            @click="validateFile"
          >
            Validar Planilha
          </Button>
          <Button
            v-else-if="validationResult && !importProgress"
            :disabled="validationResult.errors.length > 0"
            :loading="importing"
            @click="confirmImport"
          >
            Confirmar Importação
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'
import { PencilSquareIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline'
import PaginationBar from '@/components/Common/PaginationBar.vue'
import Modal from '@/components/Common/Modal.vue'
import Button from '@/components/Common/Button.vue'
import { useAlert } from '@/composables/useAlert'
import { getEcho } from '@/echo'
import { useAuthStore } from '@/stores/auth'

const { success, error: showError } = useAlert()
const authStore = useAuthStore()

const servants = ref([])
const searchQuery = ref('')
const pagination = ref(null)
const perPageRef = ref(15)
let searchTimeout = null

const importModalOpen = ref(false)
const fileInput = ref(null)
const selectedFile = ref(null)
const validating = ref(false)
const importing = ref(false)
const validationResult = ref(null)
const importProgress = ref(null)
const importChannel = ref(null)

const fetchServants = async (params = {}) => {
  try {
    const p = { per_page: perPageRef.value, ...params }
    if (searchQuery.value) p.search = searchQuery.value
    
    const { data } = await api.get('/servants', { params: p })
    servants.value = data.data || data
    if (data.meta) {
      pagination.value = data.meta
      perPageRef.value = data.meta.per_page ?? perPageRef.value
    } else if (data.current_page) {
      pagination.value = data
      perPageRef.value = data.per_page ?? perPageRef.value
    }
  } catch (error) {
    console.error('Erro ao carregar servidores:', error)
  }
}

function onPerPageChange(perPage) {
  perPageRef.value = perPage
  fetchServants({ page: 1, per_page: perPage })
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchServants(), 500)
}

function openImportModal() {
  importModalOpen.value = true
  selectedFile.value = null
  validationResult.value = null
  importProgress.value = null
  subscribeToImportChannel()
}

function closeImportModal() {
  importModalOpen.value = false
  selectedFile.value = null
  validationResult.value = null
  importProgress.value = null
  unsubscribeFromImportChannel()
}

function subscribeToImportChannel() {
  const echo = getEcho()
  if (!echo) return

  const userId = authStore.user?.id
  if (!userId) return

  const channelName = `servant-import.${userId}`
  if (importChannel.value) return
  importChannel.value = echo.private(channelName)
  importChannel.value.listen('.import.progress', (data) => {
    importProgress.value = data

    if (data.status === 'completed') {
      fetchServants()
      success('Sucesso', data.message || 'Importação concluída!')
    } else if (data.status === 'error') {
      showError('Erro', data.message || 'Erro na importação.')
    }
  })
}

function unsubscribeFromImportChannel() {
  if (importChannel.value) {
    const echo = getEcho()
    const userId = authStore.user?.id
    if (echo && userId) {
      echo.leave(`servant-import.${userId}`)
    }
    importChannel.value = null
  }
}

async function downloadTemplate() {
  try {
    const response = await api.get('/servants/import/template', { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'modelo-importacao-servidores.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    console.error('Erro ao baixar modelo:', err)
    showError('Erro', 'Não foi possível baixar o modelo.')
  }
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (file) {
    selectedFile.value = file
    validationResult.value = null
  }
}

async function validateFile() {
  if (!selectedFile.value) return
  validating.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    const { data } = await api.post('/servants/import/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    validationResult.value = data
  } catch (err) {
    console.error('Erro ao validar:', err)
    showError('Erro', err.response?.data?.message || 'Erro ao validar a planilha.')
  } finally {
    validating.value = false
  }
}

async function confirmImport() {
  if (!selectedFile.value || !validationResult.value || validationResult.value.errors.length > 0) return
  importing.value = true
  importProgress.value = { status: 'processing', progress: 0, message: 'Aguardando início...' }

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    await api.post('/servants/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // Resposta voltou; job está na fila. Modal fica aberto; barra atualiza via WebSocket.
  } catch (err) {
    console.error('Erro ao importar:', err)
    showError('Erro', err.response?.data?.message || 'Erro ao importar servidores.')
    importProgress.value = null
  } finally {
    importing.value = false
  }
}

async function fetchImportStatus() {
  try {
    const { data } = await api.get('/servants/import/status')
    if (data && data.status) {
      importProgress.value = data
    }
  } catch (_) {
    // ignora
  }
}

function clearImportProgress() {
  importProgress.value = null
}

onMounted(() => {
  fetchServants()
  fetchImportStatus()
  subscribeToImportChannel()
})

onUnmounted(() => {
  unsubscribeFromImportChannel()
})
</script>
