<template>
  <div class="space-y-6">
    <!-- Filtros obrigatórios -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm no-print">
      <p class="text-sm font-medium text-slate-700 mb-3">Filtros obrigatórios</p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <SelectInput
            v-model="filters.year"
            label="Exercício (ano)"
            :options="yearOptions"
            placeholder="Ano"
            :searchable="true"
          />
        </div>
        <div>
          <SelectInput
            v-model="filters.month_start"
            label="Mês inicial"
            :options="monthOptions"
            placeholder="Selecione o mês"
            :searchable="monthOptions.length > 10"
          />
        </div>
        <div>
          <SelectInput
            v-model="filters.month_end"
            label="Mês final"
            :options="monthOptions"
            placeholder="Selecione o mês"
            :searchable="monthOptions.length > 10"
          />
        </div>
      </div>
    </div>

    <!-- Filtros padrão -->
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm no-print">
      <p class="text-sm font-medium text-slate-700 mb-3">Filtros padrão</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <SelectInput
            v-model="filters.department_id"
            label="Gestão (secretaria)"
            :options="departmentOptions"
            placeholder="Todas"
            :searchable="departmentOptions.length > 8"
          />
        </div>
        <div>
          <SelectInput
            v-model="filters.destination"
            label="Destino"
            :options="destinationOptions"
            placeholder="Cidade ou UF"
            :searchable="true"
          />
        </div>
        <div>
          <SelectInput
            v-model="filters.servant_id"
            label="Servidor"
            :options="servantOptions"
            placeholder="Nome do servidor"
            :searchable="true"
          />
        </div>
      </div>
    </div>

    <!-- Botões: esquerda Pesquisar/Limpar, direita Imprimir/Exportar CSV -->
    <div class="flex flex-wrap items-center justify-between gap-4 no-print">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm"
          :disabled="loading"
          @click="search(1)"
        >
          Pesquisar
        </button>
        <button
          type="button"
          class="px-4 py-2 border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm"
          @click="clearFilters"
        >
          Limpar
        </button>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm"
          :disabled="pdfLoading || !config?.municipality?.id"
          @click="generatePdf"
        >
          <span v-if="pdfLoading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle" />
          {{ pdfLoading ? 'Gerando PDF...' : 'Imprimir' }}
        </button>
        <button
          type="button"
          class="px-4 py-2 border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm"
          :disabled="!meta.total"
          @click="exportCsv"
        >
          Exportar CSV
        </button>
      </div>
    </div>

    <!-- Tabela -->
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200" id="transparency-table">
          <thead class="bg-slate-100">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Gestão</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Servidor</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Matrícula</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Cargo</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Destino</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Data inicial</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Data final</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-slate-600 uppercase">Quant. diárias</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-slate-600 uppercase">Valor unit.</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-slate-600 uppercase">Valor</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-slate-600 uppercase">Data liq.</th>
              <th class="sticky right-0 z-10 bg-slate-100 px-4 py-2 text-center text-xs font-medium text-slate-600 uppercase no-print w-24 shadow-[-4px_0_8px_rgba(0,0,0,0.06)]">Ações</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-if="loading" class="bg-white">
              <td colspan="12" class="px-4 py-8 text-center text-slate-500">
                Carregando...
              </td>
            </tr>
            <tr v-else-if="!data.length" class="bg-white">
              <td colspan="12" class="px-4 py-8 text-center text-slate-500">
                Nenhum registro encontrado para os filtros informados.
              </td>
            </tr>
            <tr
              v-for="row in data"
              :key="row.id"
              class="group bg-white hover:bg-slate-50"
            >
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.gestao }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.servidor }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.matricula }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.cargo }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.destino }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.data_inicial }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.data_final }}</td>
              <td class="px-4 py-2 text-sm text-slate-800 text-right">{{ formatQty(row.quant_diarias) }}</td>
              <td class="px-4 py-2 text-sm text-slate-800 text-right">{{ formatMoney(row.valor_unitario) }}</td>
              <td class="px-4 py-2 text-sm text-slate-800 text-right font-medium">{{ formatMoney(row.valor_total) }}</td>
              <td class="px-4 py-2 text-sm text-slate-800">{{ row.data_liquidacao }}</td>
              <td class="sticky right-0 z-10 bg-white group-hover:bg-slate-50 px-4 py-2 text-center no-print shadow-[-4px_0_8px_rgba(0,0,0,0.06)]">
                <div class="flex items-center justify-center gap-1">
                  <button
                    type="button"
                    class="p-2 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-slate-800 transition-colors"
                    title="Consultar diária"
                    @click="openConsultaModal(row)"
                  >
                    <InformationCircleIcon class="h-5 w-5" />
                  </button>
                  <button
                    type="button"
                    class="p-2 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-slate-800 transition-colors"
                    title="Documentos anexos da diária/passagem"
                    @click="openAnexosModal(row)"
                  >
                    <PaperClipIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-2 bg-slate-50 border-t border-slate-200 text-sm text-slate-600 flex flex-wrap justify-between items-center gap-2">
        <span>
          Exibindo {{ fromRecord }} a {{ toRecord }} de {{ meta.total }} registro(s)
        </span>
        <span v-if="totalValue > 0">Total desta página: {{ formatMoney(totalValue) }}</span>
      </div>
    </div>

    <!-- Paginação com total -->
    <div v-if="meta.total > 0" class="flex flex-wrap items-center justify-between gap-4 no-print">
      <p class="text-sm text-slate-600">
        Total: <strong>{{ meta.total }}</strong> registro(s)
        <span v-if="meta.last_page > 1"> — Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      </p>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
          :disabled="meta.current_page <= 1"
          @click="goPage(meta.current_page - 1)"
        >
          Anterior
        </button>
        <span class="px-2 text-sm text-slate-600 min-w-[8rem] text-center">
          {{ meta.current_page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-50"
          :disabled="meta.current_page >= meta.last_page"
          @click="goPage(meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>

    <!-- Modal: Consultar diária -->
    <Modal
      :is-open="consultaModalOpen"
      title="Consulta diária - Portal da Transparência"
      @close="consultaModalOpen = false"
    >
      <div v-if="consultaRow" class="space-y-4 text-sm">
        <div>
          <p class="text-slate-500 font-medium">Beneficiário</p>
          <p class="text-slate-800">{{ consultaRow.servidor }}</p>
        </div>
        <div>
          <p class="text-slate-500 font-medium">Matrícula</p>
          <p class="text-slate-800">{{ consultaRow.matricula || '—' }}</p>
        </div>
        <div>
          <p class="text-slate-500 font-medium">Cargo</p>
          <p class="text-slate-800">{{ consultaRow.cargo }}</p>
        </div>
        <div>
          <p class="text-slate-500 font-medium">Gestão</p>
          <p class="text-slate-800">{{ consultaRow.gestao }}</p>
        </div>
        <div>
          <p class="text-slate-500 font-medium">Destino</p>
          <p class="text-slate-800">{{ consultaRow.destino }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-slate-500 font-medium">Data inicial</p>
            <p class="text-slate-800">{{ consultaRow.data_inicial }}</p>
          </div>
          <div>
            <p class="text-slate-500 font-medium">Data final</p>
            <p class="text-slate-800">{{ consultaRow.data_final }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-slate-500 font-medium">Quantidade de diárias</p>
            <p class="text-slate-800">{{ formatQty(consultaRow.quant_diarias) }}</p>
          </div>
          <div>
            <p class="text-slate-500 font-medium">Data liquidação</p>
            <p class="text-slate-800">{{ consultaRow.data_liquidacao }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-slate-500 font-medium">Valor unitário</p>
            <p class="text-slate-800">{{ formatMoney(consultaRow.valor_unitario) }}</p>
          </div>
          <div>
            <p class="text-slate-500 font-medium">Valor total</p>
            <p class="text-slate-800 font-semibold">{{ formatMoney(consultaRow.valor_total) }}</p>
          </div>
        </div>
        <div v-if="consultaRow.historico">
          <p class="text-slate-500 font-medium">Motivo / Finalidade</p>
          <p class="text-slate-800 whitespace-pre-wrap">{{ consultaRow.historico }}</p>
        </div>
      </div>
    </Modal>

    <!-- Modal: Documentos anexos -->
    <Modal
      :is-open="anexosModalOpen"
      title="Documentos anexos da diária/passagem"
      @close="anexosModalOpen = false"
    >
      <p class="text-slate-600">
        Não há documentos anexos para esta diária.
      </p>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue';
import api from '@/services/api';
import SelectInput from '@/components/Common/SelectInput.vue';
import Modal from '@/components/Common/Modal.vue';
import { InformationCircleIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

const config = inject('transparencyConfig', ref(null));

const filters = ref({
  year: new Date().getFullYear(),
  month_start: 1,
  month_end: 12,
  department_id: 0,
  destination: '',
  servant_id: null,
});

const yearOptions = computed(() => {
  const start = 2022;
  const end = 2030;
  const list = [];
  for (let y = end; y >= start; y--) list.push({ value: y, label: String(y) });
  return list;
});

const destinationOptions = ref([]);
const servantOptions = ref([]);

const data = ref([]);
const meta = ref({ total: 0, per_page: 15, current_page: 1, last_page: 1 });
const loading = ref(false);

const consultaModalOpen = ref(false);
const consultaRow = ref(null);
const anexosModalOpen = ref(false);
const pdfLoading = ref(false);

const monthOptions = computed(() => {
  const names = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
  return names.slice(1).map((label, i) => ({ value: i + 1, label }));
});

const departmentOptions = computed(() => {
  const depts = config.value?.departments || [];
  const list = [{ value: 0, label: 'Todas' }];
  depts.forEach((d) => {
    list.push({ value: d.id, label: d.code ? `${d.code} - ${d.name}` : d.name });
  });
  return list;
});

async function loadDestinationOptions() {
  const municipalityId = config.value?.municipality?.id;
  if (!municipalityId) {
    destinationOptions.value = [];
    return;
  }
  try {
    const res = await api.get('/public/transparency/destinations', { params: { municipality_id: municipalityId } });
    const raw = res.data.data || [];
    destinationOptions.value = raw.map((d) => ({ value: d, label: d }));
  } catch {
    destinationOptions.value = [];
  }
}

async function loadServantOptions() {
  const municipalityId = config.value?.municipality?.id;
  if (!municipalityId) {
    servantOptions.value = [];
    return;
  }
  try {
    const res = await api.get('/public/transparency/servants', { params: { municipality_id: municipalityId } });
    servantOptions.value = res.data.data || [];
  } catch {
    servantOptions.value = [];
  }
}

const totalValue = computed(() => data.value.reduce((acc, row) => acc + (row.valor_total || 0), 0));

const fromRecord = computed(() => {
  if (meta.value.total === 0) return 0;
  return (meta.value.current_page - 1) * meta.value.per_page + 1;
});

const toRecord = computed(() => {
  const end = meta.value.current_page * meta.value.per_page;
  return Math.min(end, meta.value.total);
});

function formatMoney(cents) {
  if (cents == null) return '—';
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}

function formatQty(n) {
  if (n == null) return '—';
  return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(n);
}

async function search(page = 1) {
  const municipalityId = config.value?.municipality?.id;
  if (!municipalityId) return;
  loading.value = true;
  try {
    const res = await api.get('/public/transparency/daily-allowances', {
      params: {
        municipality_id: municipalityId,
        year: filters.value.year,
        month_start: filters.value.month_start,
        month_end: filters.value.month_end,
        department_id: filters.value.department_id || undefined,
        destination: filters.value.destination || undefined,
        servant_id: filters.value.servant_id || undefined,
        page,
        per_page: 15,
      },
    });
    data.value = res.data.data || [];
    meta.value = res.data.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1 };
  } catch (e) {
    data.value = [];
    meta.value = { total: 0, per_page: 15, current_page: 1, last_page: 1 };
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  search(page);
}

function openConsultaModal(row) {
  consultaRow.value = row;
  consultaModalOpen.value = true;
}

function openAnexosModal() {
  anexosModalOpen.value = true;
}

function clearFilters() {
  filters.value = {
    year: new Date().getFullYear(),
    month_start: 1,
    month_end: 12,
    department_id: 0,
    destination: '',
    servant_id: null,
  };
  data.value = [];
  meta.value = { total: 0, per_page: 15, current_page: 1, last_page: 1 };
}

async function generatePdf() {
  const municipalityId = config.value?.municipality?.id;
  if (!municipalityId || pdfLoading.value) return;
  pdfLoading.value = true;
  try {
    const params = new URLSearchParams({
      municipality_id: municipalityId,
      year: filters.value.year,
      month_start: filters.value.month_start,
      month_end: filters.value.month_end,
    });
    if (filters.value.department_id) params.set('department_id', filters.value.department_id);
    if (filters.value.destination) params.set('destination', filters.value.destination);
    if (filters.value.servant_id) params.set('servant_id', filters.value.servant_id);
    const res = await api.get('/public/transparency/daily-allowances/export/pdf', {
      params: Object.fromEntries(params),
      responseType: 'blob',
    });
    const blob = new Blob([res.data], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch (e) {
    console.error('Erro ao gerar PDF:', e);
  } finally {
    pdfLoading.value = false;
  }
}

function exportCsv() {
  if (!data.value.length) return;
  const headers = ['Gestão', 'Servidor', 'Matrícula', 'Cargo', 'Destino', 'Data inicial', 'Data final', 'Quant. diárias', 'Valor unit.', 'Valor', 'Data liq.'];
  const rows = data.value.map((r) => [
    r.gestao,
    r.servidor,
    r.matricula,
    r.cargo,
    r.destino,
    r.data_inicial,
    r.data_final,
    r.quant_diarias,
    (r.valor_unitario / 100).toFixed(2),
    (r.valor_total / 100).toFixed(2),
    r.data_liquidacao,
  ]);
  const csv = [headers.join(';'), ...rows.map((row) => row.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';'))].join('\n');
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `transparencia-diarias-${filters.value.year}-${filters.value.month_start}-${filters.value.month_end}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
}

watch(
  () => config.value?.municipality?.id,
  (id) => {
    if (id) {
      loadDestinationOptions();
      loadServantOptions();
      search(1);
    }
  },
  { immediate: true }
);

let filterDebounce = null;
watch(
  filters,
  () => {
    if (!config.value?.municipality?.id) return;
    if (filterDebounce) clearTimeout(filterDebounce);
    filterDebounce = setTimeout(() => {
      search(1);
      filterDebounce = null;
    }, 400);
  },
  { deep: true }
);
</script>

<style scoped>
@media print {
  header, footer, .no-print, button, .no-print { display: none !important; }
  body { background: white; }
  #transparency-table { font-size: 0.75rem; }
  #transparency-table th.no-print,
  #transparency-table td.no-print { display: none !important; }
}
</style>
