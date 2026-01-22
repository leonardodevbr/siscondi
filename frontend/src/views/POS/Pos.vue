<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useCartStore } from '@/stores/cart';
import { useToast } from 'vue-toastification';
import { useAlert } from '@/composables/useAlert';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import PosClosedState from '@/components/Pos/PosClosedState.vue';
import StockAvailabilityModal from '@/components/Products/StockAvailabilityModal.vue';

const cashRegisterStore = useCashRegisterStore();
const cartStore = useCartStore();
const toast = useToast();
const { confirm, info } = useAlert();

const searchQuery = ref('');
const products = ref([]);
const loadingProducts = ref(false);
const searchTimeout = ref(null);
const showPriceCheckModal = ref(false);
const showHelpModal = ref(false);
const showCheckoutModal = ref(false);
const showCustomerModal = ref(false);
const selectedCartIndex = ref(null);
const cartListRef = ref(null);
const customerCpf = ref('');

const cartTotal = computed(() => cartStore.subtotal);

const shortcuts = [
  { key: 'F1', label: 'Ajuda' },
  { key: 'F2', label: 'Consultar Preço' },
  { key: 'F3', label: 'Remover Item' },
  { key: 'F4', label: 'Cancelar Venda' },
  { key: 'F6', label: 'Abrir Gaveta' },
  { key: 'F7', label: 'Identificar Cliente' },
  { key: 'F10', label: 'Finalizar Venda' },
  { key: 'ESC', label: 'Fechar / Limpar Busca' },
];

async function checkCashRegisterStatus() {
  try {
    await cashRegisterStore.checkStatus();
  } catch (error) {
    toast.error('Erro ao verificar status do caixa.');
  }
}

async function searchProducts(query) {
  if (!query || query.length < 2) {
    products.value = [];
    return;
  }

  loadingProducts.value = true;

  try {
    const response = await api.get('/products', {
      params: {
        search: query,
      },
    });

    products.value = response.data.data || [];
  } catch (error) {
    toast.error('Erro ao buscar produtos.');
    products.value = [];
  } finally {
    loadingProducts.value = false;
  }
}

function handleSearchInput(event) {
  const query = event.target.value.trim();
  searchQuery.value = query;

  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }

  if (query.length >= 2) {
    searchTimeout.value = setTimeout(() => {
      searchProducts(query);
    }, 300);
  } else {
    products.value = [];
  }
}

async function handleBarcodeSearch(event) {
  if (event.key !== 'Enter') {
    return;
  }

  const barcode = searchQuery.value.trim();

  if (!barcode) {
    return;
  }

  try {
    const response = await api.get('/products', {
      params: {
        search: barcode,
      },
    });

    const foundProducts = response.data.data || [];
    const product = foundProducts.find((p) => p.barcode && p.barcode.toString() === barcode);

    if (product) {
      try {
        cartStore.addItem(product, 1);
        toast.success(`${product.name} adicionado ao carrinho.`);
        searchQuery.value = '';
        products.value = [];

        await nextTick();
        const searchInput = document.querySelector('#product-search');
        if (searchInput) {
          searchInput.focus();
        }
      } catch (cartError) {
        toast.error(cartError.message || 'Erro ao adicionar produto ao carrinho.');
      }
    } else {
      searchProducts(barcode);
    }
  } catch (error) {
    if (error.message && error.message.includes('Estoque insuficiente')) {
      toast.error(error.message);
    } else {
      searchProducts(barcode);
    }
  }
}

function handleAddProduct(product) {
  try {
    cartStore.addItem(product, 1);
    toast.success(`${product.name} adicionado ao carrinho.`);
  } catch (error) {
    toast.error(error.message || 'Erro ao adicionar produto.');
  }
}

function handleRemoveItem(index) {
  cartStore.removeItem(index);
  toast.info('Item removido do carrinho.');
}

function handleUpdateQuantity(index, newQuantity) {
  try {
    cartStore.updateQuantity(index, parseInt(newQuantity) || 1);
  } catch (error) {
    toast.error(error.message || 'Erro ao atualizar quantidade.');
  }
}

function handleClearCart() {
  cartStore.clearCart();
  toast.info('Carrinho limpo.');
}

async function handleFinalizeSale() {
  if (cartStore.items.length === 0) {
    toast.error('Adicione pelo menos um item ao carrinho.');
    return;
  }

  try {
    const saleData = {
      items: cartStore.items.map((item) => ({
        product_id: item.product.id,
        quantity: item.quantity,
      })),
      payments: [
        {
          method: 'CASH',
          amount: cartTotal.value,
        },
      ],
    };

    await api.post('/sales', saleData);
    toast.success('Venda finalizada com sucesso!');
    cartStore.clearCart();
    await cashRegisterStore.checkStatus();
  } catch (error) {
    const message = error.response?.data?.message || 'Erro ao finalizar venda.';
    toast.error(message);
  }
}

async function handleCancelSale() {
  if (cartStore.items.length === 0) {
    return;
  }

  const confirmed = await confirm(
    'Cancelar Venda',
    'Deseja realmente cancelar esta venda? Todos os itens serão removidos do carrinho.',
    'Sim, cancelar',
    'blue'
  );

  if (confirmed) {
    cartStore.clearCart();
    toast.info('Venda cancelada.');
  }
}

function handlePriceCheckClose() {
  showPriceCheckModal.value = false;
  nextTick(() => focusSearch());
}

function closeHelp() {
  showHelpModal.value = false;
  nextTick(() => focusSearch());
}
function closeCheckout() {
  showCheckoutModal.value = false;
  nextTick(() => focusSearch());
}
function closeCustomer() {
  showCustomerModal.value = false;
  nextTick(() => focusSearch());
}

function focusSearch() {
  const el = document.querySelector('#product-search');
  if (el) el.focus();
}

function handleF3RemoveItem() {
  const items = cartStore.items;
  if (items.length === 0) return;
  const idx = selectedCartIndex.value != null && selectedCartIndex.value >= 0 && selectedCartIndex.value < items.length
    ? selectedCartIndex.value
    : items.length - 1;
  handleRemoveItem(idx);
  const rest = cartStore.items;
  selectedCartIndex.value = rest.length === 0 ? null : Math.min(idx, rest.length - 1);
  nextTick(() => cartListRef.value?.focus());
}

async function handleF6OpenDrawer() {
  try {
    await api.post('/cash-register/open-drawer');
    toast.success('Gaveta acionada.');
  } catch (err) {
    toast.info('Abertura de gaveta não disponível.');
  }
}

function handleKeydown(e) {
  if (!cashRegisterStore.isOpen) return;
  const key = e.key;
  const isF = /^F([1-9]|1[0-2])$/.test(key);

  if (key === 'F5') {
    e.preventDefault();
    info('Atualizar página', 'Para atualizar, finalize ou cancele a venda atual.');
    return;
  }

  if (key === 'Escape') {
    e.preventDefault();
    if (showHelpModal.value) {
      showHelpModal.value = false;
      nextTick(() => focusSearch());
      return;
    }
    if (showPriceCheckModal.value) {
      handlePriceCheckClose();
      return;
    }
    if (showCheckoutModal.value) {
      showCheckoutModal.value = false;
      nextTick(() => focusSearch());
      return;
    }
    if (showCustomerModal.value) {
      showCustomerModal.value = false;
      nextTick(() => focusSearch());
      return;
    }
    searchQuery.value = '';
    products.value = [];
    focusSearch();
    return;
  }

  if (!isF) return;

  e.preventDefault();

  if (key === 'F1') {
    showHelpModal.value = true;
    return;
  }
  if (key === 'F2') {
    if (!showPriceCheckModal.value) showPriceCheckModal.value = true;
    return;
  }
  if (key === 'F3') {
    handleF3RemoveItem();
    return;
  }
  if (key === 'F4') {
    handleCancelSale();
    return;
  }
  if (key === 'F6') {
    handleF6OpenDrawer();
    return;
  }
  if (key === 'F7') {
    showCustomerModal.value = true;
    customerCpf.value = '';
    return;
  }
  if (key === 'F10') {
    if (cartStore.items.length === 0) {
      toast.error('Adicione pelo menos um item ao carrinho.');
      return;
    }
    showCheckoutModal.value = true;
  }
}

async function confirmCheckout() {
  showCheckoutModal.value = false;
  await handleFinalizeSale();
  nextTick(() => focusSearch());
}

function handleCustomerSubmit() {
  const cpf = customerCpf.value?.replace(/\D/g, '').trim();
  if (!cpf) {
    toast.error('Informe o CPF ou identifique o cliente.');
    return;
  }
  toast.success('Cliente identificado.');
  showCustomerModal.value = false;
  customerCpf.value = '';
  nextTick(() => focusSearch());
}

onMounted(async () => {
  await checkCashRegisterStatus();

  if (cashRegisterStore.isOpen) {
    await nextTick();
    focusSearch();
  }

  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- Tela de Empty State quando caixa está fechado -->
    <PosClosedState v-if="!cashRegisterStore.isOpen" />

    <!-- Interface de Vendas (só aparece se caixa estiver aberto) -->
    <div
      v-else
      class="flex h-full flex-col gap-4"
    >
      <!-- Header com saldo do caixa -->
      <div class="flex items-center justify-between rounded-lg bg-blue-50 p-4 border border-blue-200">
        <div>
          <p class="text-xs text-blue-600">
            Saldo do Caixa
          </p>
          <p class="text-xl font-bold text-blue-900">
            {{ formatCurrency(cashRegisterStore.balance) }}
          </p>
        </div>
        <div class="text-right">
          <p class="text-xs text-blue-600">
            Itens no Carrinho
          </p>
          <p class="text-xl font-bold text-blue-900">
            {{ cartStore.totalCount }}
          </p>
        </div>
      </div>

      <!-- Grid 2 Colunas -->
      <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Coluna Esquerda (2/3) - Busca e Produtos -->
        <div class="flex flex-col space-y-4 lg:col-span-2">
          <!-- Input de Busca -->
          <div>
            <input
              id="product-search"
              v-model="searchQuery"
              type="text"
              placeholder="Bipar ou Digitar Produto..."
              class="input-base text-lg"
              autofocus
              @input="handleSearchInput"
              @keyup.enter="handleBarcodeSearch"
            >
          </div>

          <!-- Lista de Produtos -->
          <div class="flex-1 overflow-y-auto rounded-lg border border-slate-200 bg-white">
            <div
              v-if="loadingProducts"
              class="flex h-32 items-center justify-center"
            >
              <p class="text-sm text-slate-500">
                Buscando produtos...
              </p>
            </div>

            <div
              v-else-if="products.length === 0 && searchQuery.length >= 2"
              class="flex h-32 items-center justify-center"
            >
              <p class="text-sm text-slate-500">
                Nenhum produto encontrado.
              </p>
            </div>

            <div
              v-else-if="products.length === 0"
              class="flex h-32 items-center justify-center"
            >
              <p class="text-sm text-slate-500">
                Digite para buscar produtos...
              </p>
            </div>

            <div
              v-else
              class="grid grid-cols-2 gap-2 p-2 sm:grid-cols-3 lg:grid-cols-4"
            >
              <button
                v-for="product in products"
                :key="product.id"
                type="button"
                class="flex flex-col rounded-lg border border-slate-200 bg-white p-3 text-left transition hover:border-blue-300 hover:bg-blue-50"
                :class="{
                  'opacity-50 cursor-not-allowed': !product.stock_quantity || product.stock_quantity === 0,
                }"
                :disabled="!product.stock_quantity || product.stock_quantity === 0"
                @click="handleAddProduct(product)"
              >
                <p class="text-sm font-semibold text-slate-800">
                  {{ product.name }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ product.sku }}
                </p>
                <p class="mt-2 text-sm font-bold text-blue-600">
                  {{ formatCurrency(product.effective_price ?? product.sell_price) }}
                </p>
                <p
                  v-if="product.stock_quantity"
                  class="mt-1 text-xs text-slate-400"
                >
                  Estoque: {{ product.stock_quantity }}
                </p>
                <p
                  v-else
                  class="mt-1 text-xs text-red-500"
                >
                  Sem estoque
                </p>
              </button>
            </div>
          </div>
        </div>

        <!-- Coluna Direita (1/3) - Carrinho -->
        <div class="flex flex-col rounded-lg border border-slate-200 bg-white lg:col-span-1">
          <div class="border-b border-slate-200 p-4">
            <h3 class="text-lg font-semibold text-slate-800">
              Carrinho
            </h3>
          </div>

          <!-- Lista de Itens -->
          <div
            ref="cartListRef"
            tabindex="-1"
            class="flex-1 overflow-y-auto p-4 outline-none"
          >
            <div
              v-if="cartStore.items.length === 0"
              class="flex h-32 items-center justify-center"
            >
              <p class="text-sm text-slate-400">
                Carrinho vazio
              </p>
            </div>

            <div
              v-else
              class="space-y-3"
            >
              <div
                v-for="(item, index) in cartStore.items"
                :key="index"
                class="rounded-lg border p-3 transition-colors cursor-pointer"
                :class="selectedCartIndex === index ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                @click="selectedCartIndex = index"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-800">
                      {{ item.product.name }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                      {{ formatCurrency(item.unit_price) }} x {{ item.quantity }}
                    </p>
                  </div>
                  <button
                    type="button"
                    class="ml-2 text-red-500 hover:text-red-700"
                    @click.stop="handleRemoveItem(index)"
                  >
                    <svg
                      class="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                      />
                    </svg>
                  </button>
                </div>
                <div class="mt-2 flex items-center gap-2" @click.stop>
                  <button
                    type="button"
                    class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50"
                    @click="handleUpdateQuantity(index, item.quantity - 1)"
                  >
                    -
                  </button>
                  <input
                    :value="item.quantity"
                    type="number"
                    min="1"
                    class="w-16 rounded border border-slate-300 px-2 py-1 text-center text-sm"
                    @change="(e) => handleUpdateQuantity(index, parseInt(e.target.value) || 1)"
                  >
                  <button
                    type="button"
                    class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50"
                    @click="handleUpdateQuantity(index, item.quantity + 1)"
                  >
                    +
                  </button>
                  <span class="ml-auto text-sm font-semibold text-slate-800">
                    {{ formatCurrency(item.total) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Rodapé Fixo -->
          <div class="border-t border-slate-200 bg-slate-50 p-4">
            <div class="mb-4 flex items-center justify-between">
              <span class="text-lg font-semibold text-slate-700">
                TOTAL
              </span>
              <span class="text-2xl font-bold text-blue-600">
                {{ formatCurrency(cartTotal) }}
              </span>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                class="border-slate-300 text-slate-700 hover:bg-slate-50"
                @click="showHelpModal = true"
              >
                F1 - Ajuda
              </Button>
              <Button
                type="button"
                variant="outline"
                class="border-slate-300 text-slate-700 hover:bg-slate-50"
                @click="showPriceCheckModal = true"
              >
                F2 - Consultar
              </Button>
              <Button
                type="button"
                variant="outline"
                class="border-red-300 text-red-600 hover:bg-red-50"
                @click="handleCancelSale"
              >
                F4 - Cancelar
              </Button>
              <Button
                type="button"
                variant="primary"
                @click="showCheckoutModal = true"
              >
                F10 - Finalizar
              </Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Barra de legenda (atalhos) -->
      <div class="flex shrink-0 flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-slate-800 px-4 py-2 text-sm text-white">
        <span
          v-for="s in shortcuts"
          :key="s.key"
          class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 font-medium ring-1 ring-slate-600"
        >
          <kbd class="rounded bg-slate-700 px-1.5 py-0.5 font-mono text-xs">{{ s.key }}</kbd>
          <span>{{ s.label }}</span>
        </span>
      </div>

      <StockAvailabilityModal
        mode="price-check"
        :is-open="showPriceCheckModal"
        @close="handlePriceCheckClose"
      />

      <Modal
        :is-open="showHelpModal"
        title="Atalhos do PDV"
        @close="closeHelp"
      >
        <ul class="space-y-2 text-slate-700">
          <li v-for="s in shortcuts" :key="s.key" class="flex items-center gap-2">
            <kbd class="rounded bg-slate-200 px-2 py-0.5 font-mono text-sm">{{ s.key }}</kbd>
            <span>{{ s.label }}</span>
          </li>
        </ul>
      </Modal>

      <Modal
        :is-open="showCheckoutModal"
        title="Finalizar Venda"
        @close="closeCheckout"
      >
        <div class="space-y-4">
          <p class="text-lg font-semibold text-slate-800">
            Total: {{ formatCurrency(cartTotal) }}
          </p>
          <p class="text-sm text-slate-500">
            Pagamento em dinheiro. Confirme para encerrar a venda.
          </p>
          <div class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="closeCheckout"
            >
              Voltar
            </Button>
            <Button
              type="button"
              variant="primary"
              @click="confirmCheckout"
            >
              Confirmar e finalizar
            </Button>
          </div>
        </div>
      </Modal>

      <Modal
        :is-open="showCustomerModal"
        title="Identificar Cliente"
        @close="closeCustomer"
      >
        <form class="space-y-4" @submit.prevent="handleCustomerSubmit">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">CPF</label>
            <input
              v-model="customerCpf"
              type="text"
              placeholder="000.000.000-00"
              class="w-full h-10 px-3 rounded border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="closeCustomer">
              Fechar
            </Button>
            <Button type="submit" variant="primary">
              Identificar
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  </div>
</template>
