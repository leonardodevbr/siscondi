<template>
  <div class="flex flex-col items-center justify-center h-[calc(100vh-170px)] p-4 bg-gray-100">
    <h1 class="text-3xl font-bold mb-8 text-gray-800">Ajuste Rápido de Estoque</h1>

    <div class="w-full max-w-md">
      <input
        ref="barcodeInput"
        v-model="scanCode"
        @keyup.enter="handleScan"
        type="text"
        placeholder="Escaneie ou digite o código de barras"
        class="w-full p-4 text-center text-xl border-2 border-blue-300 rounded-lg shadow-lg focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
        autofocus
      />

      <div v-if="productNotFound" class="mt-4 text-red-600 text-center font-semibold">
        Produto não encontrado. Tente novamente.
      </div>

      <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">Últimas Movimentações Realizadas</h2>
        <ul class="bg-white rounded-lg shadow-md p-4 max-h-60 overflow-y-auto">
          <li v-if="lastMovements.length === 0" class="text-gray-500 italic">
            Nenhuma movimentação recente.
          </li>
          <li
            v-for="(movement, index) in lastMovements"
            :key="index"
            class="border-b last:border-b-0 py-2 text-gray-700"
          >
            <div class="flex justify-between items-center">
              <span>{{ movement.name }}</span>
              <span :class="{'text-green-600': movement.operation === 'add', 'text-red-600': movement.operation === 'sub'}">
                {{ movement.operation === 'add' ? '+' : '-' }}{{ movement.quantity }}
              </span>
            </div>
            <div class="text-sm text-gray-500">{{ movement.type_label }} - {{ movement.created_at }}</div>
          </li>
        </ul>
      </div>
    </div>

    <StockAdjustmentModal
      v-if="showAdjustmentModal"
      :show="showAdjustmentModal"
      :product-id="selectedProduct.product_id"
      :variation-id="selectedProduct.variation_id"
      :product-name="selectedProduct.name"
      :current-stock="selectedProduct.current_stock"
      :auto-focus-quantity="true"
      @close="handleModalClose"
      @adjustment-made="handleAdjustmentMade"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import StockAdjustmentModal from '@/components/Products/StockAdjustmentModal.vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

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

const handleScan = async () => {
  if (!scanCode.value) return;

  try {
    const response = await axios.get(`/api/inventory/scan?code=${scanCode.value}`);
    const data = response.data;

    selectedProduct.value = {
      product_id: data.product_id,
      variation_id: data.variation_id,
      name: data.name,
      current_stock: data.current_stock,
    };

    scanCode.value = '';
    productNotFound.value = false;
    showAdjustmentModal.value = true;
  } catch (error) {
    console.error('Erro ao buscar:', error);
    productNotFound.value = true;
    toast.error('Produto não encontrado!');

    scanCode.value = '';
    nextTick(() => {
      barcodeInput.value?.focus();
    });
  }
};

const handleModalClose = () => {
  showAdjustmentModal.value = false;
  nextTick(() => {
    barcodeInput.value.focus();
  });
};

const handleAdjustmentMade = (newMovement) => {
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
};

onMounted(() => {
  nextTick(() => {
    barcodeInput.value.focus();
  });
});
</script>

<style scoped>
/* Adicione estilos personalizados aqui se necessário */
</style>
