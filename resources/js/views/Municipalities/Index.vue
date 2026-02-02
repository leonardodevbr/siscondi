<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-800">Municípios</h2>
      <p class="text-xs text-slate-500">Gestão dos dados dos municípios.</p>
    </div>
    <div class="card p-4 sm:p-6">
      <div v-if="loading" class="text-center py-8 text-slate-500">Carregando...</div>
      <div v-else-if="municipalities.length === 0" class="text-center py-8 text-slate-500">Nenhum município.</div>
      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nome</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">CNPJ</th>
              <th class="sticky right-0 z-10 bg-slate-50 px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase border-l border-slate-200">Ações</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="m in municipalities" :key="m.id">
              <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ m.name }}</td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ m.cnpj || '—' }}</td>
              <td class="sticky right-0 z-10 bg-white px-6 py-4 text-right border-l border-slate-200">
                <router-link :to="{ name: 'municipalities.edit', params: { id: m.id } }" class="inline-flex p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Editar">
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
        @page-change="(page) => load({ page })"
        @per-page-change="onPerPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import PaginationBar from '@/components/Common/PaginationBar.vue';

const municipalities = ref([]);
const loading = ref(true);
const pagination = ref(null);
const perPageRef = ref(15);

const load = async (params = {}) => {
  loading.value = true;
  try {
    const p = { per_page: perPageRef.value, ...params };
    const { data } = await api.get('/municipalities', { params: p });
    municipalities.value = Array.isArray(data) ? data : (data?.data ?? []);
    if (data?.meta) {
      pagination.value = data.meta;
      perPageRef.value = data.meta.per_page ?? perPageRef.value;
    } else if (data?.current_page) {
      pagination.value = data;
      perPageRef.value = data.per_page ?? perPageRef.value;
    } else {
      pagination.value = null;
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

function onPerPageChange(perPage) {
  perPageRef.value = perPage;
  load({ page: 1, per_page: perPage });
}

onMounted(() => load());
</script>
