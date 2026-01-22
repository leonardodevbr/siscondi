<template>
  <Teleport to="body">
    <Transition name="drawer" :duration="{ enter: 280, leave: 280 }">
      <div v-if="show" class="fixed inset-0 z-50 flex">
        <div
          class="drawer-overlay fixed inset-0 bg-black/50 z-40"
          aria-hidden="true"
        />
        <div
          class="drawer-panel fixed inset-y-0 right-0 w-full max-w-md bg-white shadow-2xl z-50 flex flex-col"
          role="dialog"
          aria-label="Conferência de Estoque"
        >
          <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold text-slate-800">
              Conferência de Estoque
            </h2>
            <button
              type="button"
              class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors"
              aria-label="Fechar"
              @click="handleClose"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div
            class="flex-1 overflow-y-auto p-4 flex flex-col"
            @click="focusInput"
          >
            <div class="mb-4">
              <p class="text-sm font-medium text-gray-600 mb-2 text-center">
                Tipo de Movimentação
              </p>
              <div class="flex rounded-lg overflow-hidden border border-gray-300 shadow-sm">
                <button
                  v-for="option in operationTypes"
                  :key="option.value"
                  type="button"
                  :class="[
                    'flex-1 py-3 px-2 text-sm font-semibold transition-all duration-200 focus:outline-none',
                    defaultOperationType === option.value ? option.activeClass : 'bg-white text-gray-600 hover:bg-gray-50',
                  ]"
                  @click="defaultOperationType = option.value"
                >
                  {{ option.label }}
                </button>
              </div>
            </div>

            <input
              ref="barcodeInput"
              v-model="scanCode"
              type="text"
              placeholder="Escaneie ou digite o código de barras"
              class="w-full p-4 text-center text-xl border-2 border-blue-300 rounded-lg shadow-lg focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
              @keyup.enter="handleScan"
            >

            <div v-if="productNotFound" class="mt-4 text-red-600 text-center font-semibold">
              Produto não encontrado. Tente novamente.
            </div>

            <div class="mt-6 flex-1">
              <h3 class="text-lg font-semibold mb-3 text-gray-700">
                Últimas Movimentações Realizadas
              </h3>
              <ul class="bg-white rounded-lg border border-slate-200 p-4 max-h-60 overflow-y-auto">
                <li v-if="lastMovements.length === 0" class="text-gray-500 italic">
                  Nenhuma movimentação recente.
                </li>
                <li
                  v-for="(movement, idx) in lastMovements"
                  :key="idx"
                  class="border-b border-slate-100 last:border-b-0 py-2 text-gray-700"
                >
                  <div class="flex justify-between items-center">
                    <span class="text-sm truncate">{{ movement.name }}</span>
                    <span
                      :class="{
                        'text-green-600': movement.operation === 'add',
                        'text-red-600': movement.operation === 'sub',
                      }"
                    >
                      {{ movement.operation === 'add' ? '+' : '-' }}{{ movement.quantity }}
                    </span>
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ movement.type_label }} - {{ movement.created_at }}
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <StockAdjustmentModal
    v-if="showAdjustmentModal"
    :show="showAdjustmentModal"
    :product-id="selectedProduct.product_id"
    :variation-id="selectedProduct.variation_id"
    :product-name="selectedProduct.name"
    :current-stock="selectedProduct.current_stock"
    :initial-type="defaultOperationType"
    :auto-focus-quantity="true"
    @close="handleModalClose"
    @adjustment-made="handleAdjustmentMade"
  />
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import StockAdjustmentModal from '@/components/Products/StockAdjustmentModal.vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const scanCode = ref('');
const barcodeInput = ref(null);
const showAdjustmentModal = ref(false);
const selectedProduct = ref({
  product_id: null,
  variation_id: null,
  name: '',
  current_stock: 0,
});
const productNotFound = ref(false);
const lastMovements = ref([]);
const toast = useToast();

const defaultOperationType = ref('entry');
const operationTypes = [
  { value: 'entry', label: 'Entrada', activeClass: 'bg-green-600 text-white' },
  { value: 'exit', label: 'Saída', activeClass: 'bg-red-600 text-white' },
  { value: 'adjustment', label: 'Ajuste', activeClass: 'bg-blue-600 text-white' },
  { value: 'return', label: 'Devolução', activeClass: 'bg-amber-500 text-white' },
];

function focusInput() {
  if (!showAdjustmentModal.value && barcodeInput.value) {
    barcodeInput.value.focus();
  }
}

function handleClose() {
  emit('close');
}

async function handleScan() {
  if (!scanCode.value) return;

  try {
    const { data } = await api.get(`/inventory/scan?code=${encodeURIComponent(scanCode.value)}`);
    selectedProduct.value = {
      product_id: data.product_id,
      variation_id: data.variation_id,
      name: data.name,
      current_stock: data.current_stock,
    };
    scanCode.value = '';
    productNotFound.value = false;
    showAdjustmentModal.value = true;
  } catch (err) {
    productNotFound.value = true;
    toast.error('Produto não encontrado!');
    scanCode.value = '';
    nextTick(() => barcodeInput.value?.focus());
  }
}

function handleModalClose() {
  showAdjustmentModal.value = false;
  nextTick(() => focusInput());
}

function handleAdjustmentMade(newMovement) {
  lastMovements.value.unshift({
    name: selectedProduct.value.name,
    quantity: newMovement.quantity,
    operation: newMovement.operation,
    type_label: newMovement.type_label,
    created_at: newMovement.created_at,
  });
  if (lastMovements.value.length > 5) {
    lastMovements.value.pop();
  }
  handleModalClose();
}

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      scanCode.value = '';
      productNotFound.value = false;
      showAdjustmentModal.value = false;
      nextTick(() => focusInput());
    } else {
      showAdjustmentModal.value = false;
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.drawer-enter-active .drawer-overlay,
.drawer-leave-active .drawer-overlay {
  transition: opacity 0.28s ease-out;
}
.drawer-enter-from .drawer-overlay,
.drawer-leave-to .drawer-overlay {
  opacity: 0;
}
.drawer-enter-active .drawer-panel,
.drawer-leave-active .drawer-panel {
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}
.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
  transform: translateX(100%);
}
</style>
