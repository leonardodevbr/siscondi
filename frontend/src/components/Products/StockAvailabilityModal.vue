<script setup>
import { ref, watch, computed, nextTick, onUnmounted } from 'vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import Modal from '@/components/Common/Modal.vue';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';

const props = defineProps({
  productId: {
    type: [Number, String],
    default: null,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: 'availability',
    validator: (v) => ['availability', 'price-check'].includes(v),
  },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const availability = ref(null);
const searchFilter = ref('');
const priceCheckInput = ref(null);
const priceCheckQuery = ref('');
const priceCheckLoading = ref(false);
const priceCheckError = ref('');
const priceCheckResult = ref(null);

const modalTitle = computed(() => {
  if (props.mode === 'price-check') return 'Consulta de Preço';
  if (!availability.value) return 'Estoque por filial';
  return `Estoque por filial - ${availability.value.product_name}`;
});

async function fetchAvailability() {
  if (!props.productId) {
    loading.value = false;
    return;
  }

  loading.value = true;
  error.value = '';
  availability.value = null;

  try {
    const { data } = await api.get(`/products/${props.productId}/availability`);
    availability.value = data;
  } catch (e) {
    error.value = 'Não foi possível carregar a disponibilidade de estoque.';
  } finally {
    loading.value = false;
  }
}

async function searchPriceCheck() {
  const code = priceCheckQuery.value.trim();
  if (!code) return;

  priceCheckLoading.value = true;
  priceCheckError.value = '';
  priceCheckResult.value = null;

  try {
    const { data } = await api.get('/inventory/scan', { params: { code } });
    priceCheckResult.value = {
      name: data.name,
      price: data.price ?? 0,
      quantity: data.current_stock ?? 0,
      sku: data.sku ?? null,
    };
    priceCheckQuery.value = '';
  } catch (e) {
    const msg = e.response?.data?.message;
    priceCheckError.value = msg || 'Produto não encontrado.';
  } finally {
    priceCheckLoading.value = false;
  }
}

function handlePriceCheckKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    searchPriceCheck();
  }
}

function handleEscKey(e) {
  if (e.key === 'Escape') {
    e.preventDefault();
    close();
  }
}

watch(
  () => props.isOpen,
  (isOpen) => {
    if (!isOpen) return;
    if (props.mode === 'price-check') {
      priceCheckQuery.value = '';
      priceCheckError.value = '';
      priceCheckResult.value = null;
      nextTick(() => {
        nextTick(() => priceCheckInput.value?.focus());
      });
      return;
    }
    loading.value = true;
    error.value = '';
    availability.value = null;
    searchFilter.value = '';
    fetchAvailability();
  },
  { immediate: true },
);

watch(
  () => [props.isOpen, props.mode],
  ([isOpen, mode]) => {
    if (isOpen && mode === 'price-check') {
      window.addEventListener('keydown', handleEscKey);
    } else {
      window.removeEventListener('keydown', handleEscKey);
    }
  },
  { immediate: true },
);

onUnmounted(() => {
  window.removeEventListener('keydown', handleEscKey);
});

const close = () => {
  emit('close');
};

const branches = computed(() => availability.value?.branches || []);

const allVariants = computed(() => availability.value?.variants || []);

const variants = computed(() => {
  if (!searchFilter.value.trim()) {
    return allVariants.value;
  }

  const filter = searchFilter.value.toLowerCase().trim();

  return allVariants.value.filter((variant) => {
    if (variant.sku?.toLowerCase().includes(filter)) return true;
    if (variant.attributes && typeof variant.attributes === 'object') {
      for (const [key, value] of Object.entries(variant.attributes)) {
        if (
          key.toLowerCase().includes(filter) ||
          String(value).toLowerCase().includes(filter)
        ) {
          return true;
        }
      }
    }
    return false;
  });
});

function getQuantityClass(quantity) {
  if (quantity > 0) return 'text-emerald-600 font-semibold';
  return 'text-slate-400';
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    :title="modalTitle"
    @close="close"
  >
    <template v-if="mode === 'price-check'">
      <div class="space-y-4">
        <input
          ref="priceCheckInput"
          v-model="priceCheckQuery"
          type="text"
          placeholder="Bipar ou digitar código..."
          class="w-full h-11 px-3 py-2 text-base border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          autofocus
          @keydown="handlePriceCheckKeydown"
        >
        <p class="text-xs text-slate-500">
          ESC para fechar
        </p>
        <div v-if="priceCheckLoading" class="py-8 flex flex-col items-center justify-center gap-3">
          <ArrowPathIcon class="h-8 w-8 text-blue-500 animate-spin" />
          <p class="text-sm text-slate-500">
            Buscando...
          </p>
        </div>
        <div v-else-if="priceCheckError" class="py-3 text-sm text-red-600">
          {{ priceCheckError }}
        </div>
        <div
          v-else-if="priceCheckResult"
          class="rounded-lg border-2 border-blue-200 bg-blue-50 p-4 space-y-2"
        >
          <p class="text-sm font-semibold text-slate-800">
            {{ priceCheckResult.name }}
          </p>
          <p v-if="priceCheckResult.sku" class="text-xs text-slate-500">
            {{ priceCheckResult.sku }}
          </p>
          <p class="text-2xl font-bold text-blue-700">
            {{ formatCurrency(priceCheckResult.price) }}
          </p>
          <p class="text-sm">
            <span class="text-slate-600">Disponível:</span>
            <span
              :class="priceCheckResult.quantity > 0 ? 'font-semibold text-emerald-600' : 'text-red-600'"
            >
              {{ priceCheckResult.quantity }} un.
            </span>
          </p>
        </div>
      </div>
    </template>

    <template v-else>
      <div v-if="loading" class="py-12 flex flex-col items-center justify-center gap-3">
        <ArrowPathIcon class="h-8 w-8 text-blue-500 animate-spin" />
        <p class="text-sm text-slate-500">
          Buscando disponibilidade nas filiais...
        </p>
      </div>

      <div v-else-if="error" class="py-4 text-sm text-red-600">
        {{ error }}
      </div>

      <div v-else-if="!availability" class="py-4 text-sm text-slate-500 text-center">
        Nenhuma informação de estoque encontrada para este produto.
      </div>

      <div v-else class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <p class="text-xs text-slate-500 whitespace-nowrap">
          Estoque por variação em todas as filiais
        </p>
        <div class="w-full sm:w-auto sm:min-w-[210px]">
          <input
            v-model="searchFilter"
            type="text"
            placeholder="Filtrar por SKU, cor, tamanho..."
            class="w-full h-8 px-3 py-1 text-xs border border-slate-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <div v-if="variants.length === 0" class="py-4 text-center text-sm text-slate-500">
        Nenhuma variação encontrada com o filtro "{{ searchFilter }}".
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-slate-500 uppercase tracking-wider">
                Variação
              </th>
              <th
                v-for="branch in branches"
                :key="branch.id"
                class="px-3 py-2 text-right font-medium text-slate-500 uppercase tracking-wider whitespace-nowrap"
              >
                {{ branch.name }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-100">
            <tr
              v-for="variant in variants"
              :key="variant.sku"
            >
              <td class="px-3 py-2 align-top">
                <div class="text-[11px] font-semibold text-slate-800 truncate">
                  {{ variant.sku }}
                </div>
                <div
                  v-if="variant.attributes && Object.keys(variant.attributes).length"
                  class="mt-1 flex flex-wrap gap-1"
                >
                  <span
                    v-for="(value, key) in variant.attributes"
                    :key="key"
                    class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700"
                  >
                    <span class="font-semibold capitalize">{{ key }}:</span>
                    <span class="ml-1">{{ value }}</span>
                  </span>
                </div>
              </td>

              <td
                v-for="branch in branches"
                :key="`${variant.sku}-${branch.id}`"
                class="px-3 py-2 text-right align-middle"
              >
                <span
                  :class="getQuantityClass(variant.stock_by_branch?.[branch.id] ?? 0)"
                >
                  {{
                    (variant.stock_by_branch?.[branch.id] ?? 0) > 0
                      ? variant.stock_by_branch?.[branch.id]
                      : '-'
                  }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </template>
  </Modal>
</template>

