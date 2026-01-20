<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useCartStore } from '@/stores/cart';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';
import Input from '@/components/Common/Input.vue';
import Modal from '@/components/Common/Modal.vue';

const router = useRouter();
const cashRegisterStore = useCashRegisterStore();
const cartStore = useCartStore();
const toast = useToast();

const showOpenModal = ref(false);
const initialBalance = ref('');
const searchQuery = ref('');
const products = ref([]);
const loadingProducts = ref(false);
const searchTimeout = ref(null);

const cartTotal = computed(() => cartStore.subtotal);

async function checkCashRegisterStatus() {
  try {
    await cashRegisterStore.checkStatus();
    if (!cashRegisterStore.isOpen) {
      showOpenModal.value = true;
    }
  } catch (error) {
    toast.error('Erro ao verificar status do caixa.');
    showOpenModal.value = true;
  }
}

async function handleOpenRegister() {
  const balance = parseFloat(initialBalance.value);

  if (!initialBalance.value || isNaN(balance) || balance < 0) {
    toast.error('Informe um saldo inicial válido (maior ou igual a zero).');
    return;
  }

  try {
    await cashRegisterStore.openRegister(balance);
    toast.success('Caixa aberto com sucesso!');
    showOpenModal.value = false;
    initialBalance.value = '';
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Erro ao abrir o caixa.';
    toast.error(message);
  }
}

function handleCancelAndGoBack() {
  router.push({ name: 'dashboard' });
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

function handleCancelSale() {
  if (cartStore.items.length === 0) {
    return;
  }

  if (confirm('Deseja realmente cancelar esta venda?')) {
    cartStore.clearCart();
    toast.info('Venda cancelada.');
  }
}

watch(showOpenModal, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    const balanceInput = document.querySelector('#initial-balance');
    if (balanceInput) {
      balanceInput.focus();
    }
  }
});

onMounted(async () => {
  await checkCashRegisterStatus();

  if (cashRegisterStore.isOpen) {
    await nextTick();
    const searchInput = document.querySelector('#product-search');
    if (searchInput) {
      searchInput.focus();
    }
  } else if (showOpenModal.value) {
    await nextTick();
    const balanceInput = document.querySelector('#initial-balance');
    if (balanceInput) {
      balanceInput.focus();
    }
  }
});
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- Modal para abrir caixa -->
    <Modal
      :is-open="showOpenModal"
      :closable="false"
      title="Abrir Caixa"
      @close="() => {}"
    >
      <div class="space-y-4">
        <p class="text-sm text-slate-600">
          Para iniciar as vendas, é necessário abrir o caixa com um saldo inicial.
        </p>

        <Input
          id="initial-balance"
          v-model="initialBalance"
          label="Saldo Inicial (R$)"
          type="number"
          step="0.01"
          min="0"
          autocomplete="off"
          @keyup.enter="handleOpenRegister"
        />

        <div class="flex justify-between w-full pt-2">
          <button
            type="button"
            :disabled="cashRegisterStore.loading"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
            @click="handleCancelAndGoBack"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="cashRegisterStore.loading"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
            @click="handleOpenRegister"
          >
            <span v-if="cashRegisterStore.loading">Abrindo...</span>
            <span v-else>Abrir Caixa</span>
          </button>
        </div>
      </div>
    </Modal>

    <!-- Interface de Vendas (só aparece se caixa estiver aberto) -->
    <div
      v-if="cashRegisterStore.isOpen"
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
          <div class="flex-1 overflow-y-auto p-4">
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
                class="rounded-lg border border-slate-200 p-3"
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
                    @click="handleRemoveItem(index)"
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
                <div class="mt-2 flex items-center gap-2">
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

            <div class="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                class="border-red-300 text-red-600 hover:bg-red-50"
                @click="handleCancelSale"
              >
                Cancelar (Esc)
              </Button>
              <Button
                type="button"
                variant="primary"
                @click="handleFinalizeSale"
              >
                Finalizar Venda (F2)
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
