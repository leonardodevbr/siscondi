<script setup>
import { ref, watch, computed } from 'vue';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import Modal from '@/components/Common/Modal.vue';
import api from '@/services/api';

const props = defineProps({
  productId: {
    type: [Number, String],
    required: true,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const availability = ref(null);
const searchFilter = ref('');

const title = computed(() => {
  if (!availability.value) return 'Estoque por filial';
  return `Estoque por filial - ${availability.value.product_name}`;
});

async function fetchAvailability() {
  if (!props.productId) return;

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

watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      loading.value = true;
      error.value = '';
      availability.value = null;
      searchFilter.value = '';
      fetchAvailability();
    }
  },
  { immediate: true },
);

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
    // Busca no SKU
    if (variant.sku?.toLowerCase().includes(filter)) {
      return true;
    }

    // Busca nos atributos (chave ou valor)
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
  if (quantity > 0) {
    return 'text-emerald-600 font-semibold';
  }
  return 'text-slate-400';
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    :title="title"
    @close="close"
  >
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
  </Modal>
</template>

