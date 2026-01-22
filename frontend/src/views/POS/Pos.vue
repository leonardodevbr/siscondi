<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter, onBeforeRouteLeave } from 'vue-router';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import { useToast } from 'vue-toastification';
import { useAlert } from '@/composables/useAlert';
import api from '@/services/api';
import { formatCurrency } from '@/utils/format';
import Button from '@/components/Common/Button.vue';
import Modal from '@/components/Common/Modal.vue';
import PosClosedState from '@/components/Pos/PosClosedState.vue';
import StockAvailabilityModal from '@/components/Products/StockAvailabilityModal.vue';
import { ArrowsPointingOutIcon, ArrowsPointingInIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const router = useRouter();
const cashRegisterStore = useCashRegisterStore();
const cartStore = useCartStore();
const authStore = useAuthStore();
const appStore = useAppStore();
const toast = useToast();
const { confirm, info } = useAlert();

const searchQuery = ref('');
const lastScannedCode = ref('');
const products = ref([]);
const loadingProducts = ref(false);
const searchTimeout = ref(null);
const showPriceCheckModal = ref(false);
const showHelpModal = ref(false);
const showCheckoutModal = ref(false);
const showCustomerModal = ref(false);
const showCloseRegisterModal = ref(false);
const closeRegisterFinalBalance = ref('');
const closeRegisterLoading = ref(false);
const isFullscreen = ref(false);
const selectedCartIndex = ref(null);
const cartListRef = ref(null);
const customerCpf = ref('');

const cartTotal = computed(() => cartStore.subtotal);

const operatorName = computed(() => authStore.user?.name ?? 'Operador');
const branchName = computed(() => {
  const b = appStore.currentBranch ?? authStore.user?.branch;
  return b?.name ?? 'Filial não definida';
});

const shortcuts = [
  { key: 'F1', label: 'Ajuda' },
  { key: 'F2', label: 'Consultar Preço' },
  { key: 'F3', label: 'Remover Item' },
  { key: 'F4', label: 'Cancelar Venda' },
  { key: 'F7', label: 'Identificar Cliente' },
  { key: 'F10', label: 'Finalizar Venda' },
  { key: 'ESC', label: 'Fechar / Limpar Busca' },
];

async function checkCashRegisterStatus() {
  try {
    await cashRegisterStore.checkStatus();
  } catch {
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
    const { data } = await api.get('/products', { params: { search: query } });
    products.value = data.data ?? [];
  } catch {
    toast.error('Erro ao buscar produtos.');
    products.value = [];
  } finally {
    loadingProducts.value = false;
  }
}

function handleSearchInput(e) {
  const q = (e?.target?.value ?? searchQuery.value).trim();
  searchQuery.value = q;
  if (searchTimeout.value) clearTimeout(searchTimeout.value);
  if (q.length >= 2) {
    searchTimeout.value = setTimeout(() => searchProducts(q), 300);
  } else {
    products.value = [];
  }
}

function clearScanAndFocus() {
  searchQuery.value = '';
  products.value = [];
  nextTick(() => {
    const el = document.querySelector('#product-search');
    if (el) el.focus();
  });
}

async function handleScan(e) {
  if (e?.key !== 'Enter') return;
  const code = searchQuery.value.trim();
  if (!code) return;

  lastScannedCode.value = code;

  try {
    const { data } = await api.get('/inventory/scan', { params: { code } });
    const stock = data.current_stock ?? 0;
    if (stock < 1) {
      toast.error('Sem estoque nesta filial.');
      clearScanAndFocus();
      return;
    }
    const product = {
      id: data.product_id,
      name: data.name,
      price: data.price ?? 0,
      current_stock: stock,
    };
    cartStore.addItem(product, 1, data.variation_id);
    toast.success(`${data.name} adicionado.`);
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Produto não encontrado.';
    toast.error(msg);
  }
  clearScanAndFocus();
}

function handleBarcodeSearch(e) {
  if (e.key !== 'Enter') return;
  const code = searchQuery.value.trim();
  if (!code) return;

  lastScannedCode.value = code;

  const viaScan = /^\d+$/.test(code) || code.length >= 8;
  if (viaScan) {
    handleScan(e);
    return;
  }

  runProductSearchAndAdd(code);
}

async function runProductSearchAndAdd(code) {
  try {
    const { data } = await api.get('/products', { params: { search: code } });
    const list = data.data ?? [];
    let product = list.find((p) => {
      if (p.barcode && String(p.barcode) === code) return true;
      return p.variants?.some((v) => v.barcode && String(v.barcode) === code);
    });
    if (!product && list.length > 0) product = list[0];
    if (!product) {
      toast.error('Produto não encontrado.');
      clearScanAndFocus();
      return;
    }
    const variant = product.variants?.find((v) => v.barcode && String(v.barcode) === code) ?? product.variants?.[0];
    if (!variant) {
      toast.error('Produto sem variação.');
      clearScanAndFocus();
      return;
    }
    const stock = product.current_stock ?? variant.current_stock ?? 0;
    if (!stock || stock < 1) {
      toast.error('Sem estoque nesta filial.');
      clearScanAndFocus();
      return;
    }
    cartStore.addItem(product, 1, variant.id);
    toast.success(`${product.name} adicionado.`);
  } catch {
    toast.error('Erro ao buscar produto.');
  }
  clearScanAndFocus();
}

function handleAddProduct(product) {
  try {
    const v = product.variants?.[0];
    const stock = product.current_stock ?? product.stock_quantity ?? v?.current_stock ?? 0;
    if (!stock || stock < 1) {
      toast.error('Sem estoque nesta filial.');
      return;
    }
    if (!v) {
      toast.error('Produto sem variação.');
      return;
    }
    cartStore.addItem(product, 1, v.id);
    toast.success(`${product.name} adicionado.`);
  } catch (err) {
    toast.error(err.message ?? 'Erro ao adicionar.');
  }
}

function handleRemoveItem(index) {
  cartStore.removeItem(index);
  toast.info('Item removido.');
}

function handleUpdateQuantity(index, newQuantity) {
  try {
    cartStore.updateQuantity(index, parseInt(newQuantity, 10) || 1);
  } catch (err) {
    toast.error(err.message ?? 'Erro ao atualizar.');
  }
}

function handleClearCart() {
  cartStore.clearCart();
  toast.info('Itens da venda limpos.');
}

function branchIdForSale() {
  const id = appStore.currentBranch?.id ?? authStore.user?.branch?.id ?? authStore.user?.branch_id;
  return id ? Number(id) : null;
}

async function handleFinalizeSale() {
  if (cartStore.items.length === 0) {
    toast.error('Adicione pelo menos um item.');
    return;
  }

  const bid = branchIdForSale();
  if (!bid) {
    toast.error('Filial não identificada. Não é possível finalizar.');
    return;
  }

  try {
    await api.post('/sales', {
      branch_id: bid,
      items: cartStore.items.map((i) => ({
        product_variant_id: i.product_variant_id,
        quantity: i.quantity,
      })),
      payments: [{ method: 'money', amount: cartTotal.value }],
    });
    toast.success('Venda finalizada.');
    cartStore.clearCart();
    await cashRegisterStore.checkStatus();
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Erro ao finalizar venda.';
    toast.error(msg);
  }
}

async function handleCancelSale() {
  if (cartStore.items.length === 0) return;
  const ok = await confirm(
    'Cancelar Venda',
    'Deseja cancelar? Todos os itens da venda serão removidos.',
    'Sim, cancelar',
    'blue'
  );
  if (ok) {
    cartStore.clearCart();
    toast.info('Venda cancelada.');
  }
}

function handlePriceCheckClose() {
  showPriceCheckModal.value = false;
  nextTick(focusSearch);
}

function closeHelp() {
  showHelpModal.value = false;
  nextTick(focusSearch);
}

function closeCheckout() {
  showCheckoutModal.value = false;
  nextTick(focusSearch);
}

function closeCustomer() {
  showCustomerModal.value = false;
  nextTick(focusSearch);
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
  selectedCartIndex.value = rest.length === 0 ? null : Math.min(idx, Math.max(0, rest.length - 1));
  nextTick(() => cartListRef.value?.focus());
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen?.();
    isFullscreen.value = true;
  } else {
    document.exitFullscreen?.();
    isFullscreen.value = false;
  }
}

function openCloseRegisterModal() {
  closeRegisterFinalBalance.value = String(cashRegisterStore.balance ?? 0);
  showCloseRegisterModal.value = true;
}

function closeCloseRegisterModal() {
  showCloseRegisterModal.value = false;
  closeRegisterFinalBalance.value = '';
}

async function confirmCloseRegister() {
  const v = parseFloat(closeRegisterFinalBalance.value);
  if (Number.isNaN(v) || v < 0) {
    toast.error('Informe o saldo final válido.');
    return;
  }
  closeRegisterLoading.value = true;
  try {
    await cashRegisterStore.closeRegister(v);
    toast.success('Caixa fechado.');
    closeCloseRegisterModal();
    await router.push({ name: 'dashboard' });
  } catch (err) {
    toast.error(err.message ?? 'Erro ao fechar caixa.');
  } finally {
    closeRegisterLoading.value = false;
  }
}

function handleKeydown(e) {
  if (!cashRegisterStore.isOpen) return;
  const key = e.key;
  const isF = /^F([1-9]|1[0-2])$/.test(key);

  if (key === 'F5') {
    e.preventDefault();
    info('Atualizar página', 'Finalize ou cancele a venda antes.');
    return;
  }

  if (key === 'Escape') {
    e.preventDefault();
    if (showHelpModal.value) {
      showHelpModal.value = false;
      nextTick(focusSearch);
      return;
    }
    if (showPriceCheckModal.value) {
      handlePriceCheckClose();
      return;
    }
    if (showCheckoutModal.value) {
      showCheckoutModal.value = false;
      nextTick(focusSearch);
      return;
    }
    if (showCustomerModal.value) {
      showCustomerModal.value = false;
      nextTick(focusSearch);
      return;
    }
    if (showCloseRegisterModal.value) {
      closeCloseRegisterModal();
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
    if (!showPriceCheckModal.value) {
      document.querySelector('#product-search')?.blur();
      showPriceCheckModal.value = true;
    }
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
  if (key === 'F7') {
    showCustomerModal.value = true;
    customerCpf.value = '';
    return;
  }
  if (key === 'F10') {
    if (cartStore.items.length === 0) {
      toast.error('Adicione pelo menos um item.');
      return;
    }
    showCheckoutModal.value = true;
  }
}

async function confirmCheckout() {
  showCheckoutModal.value = false;
  await handleFinalizeSale();
  nextTick(focusSearch);
}

function handleCustomerSubmit() {
  const cpf = customerCpf.value?.replace(/\D/g, '').trim();
  if (!cpf) {
    toast.error('Informe o CPF.');
    return;
  }
  toast.success('Cliente identificado.');
  showCustomerModal.value = false;
  customerCpf.value = '';
  nextTick(focusSearch);
}

onBeforeRouteLeave((_to, _from, next) => {
  if (!cashRegisterStore.isOpen) {
    next();
    return;
  }
  confirm(
    'Caixa aberto',
    'O caixa está aberto. Deseja realmente sair? Feche o caixa antes se for encerrar o turno.',
    'Sair mesmo assim',
    'red'
  )
    .then((ok) => (ok ? next() : next(false)))
    .catch(() => next(false));
});

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
  <div class="flex h-full min-h-0 flex-col">
    <PosClosedState v-if="!cashRegisterStore.isOpen" />

    <template v-else>
      <header class="flex shrink-0 items-center justify-between gap-4 bg-slate-800 px-4 py-2 text-white">
        <div class="font-semibold">
          PDV
        </div>
        <div class="flex flex-1 items-center justify-center gap-4 text-sm">
          <span>{{ operatorName }}</span>
          <span class="text-slate-400">|</span>
          <span>{{ branchName }}</span>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded p-1.5 text-slate-300 transition hover:bg-slate-700 hover:text-white"
            :title="isFullscreen ? 'Sair da tela cheia' : 'Tela cheia'"
            @click="toggleFullscreen"
          >
            <ArrowsPointingInIcon v-if="isFullscreen" class="h-5 w-5" />
            <ArrowsPointingOutIcon v-else class="h-5 w-5" />
          </button>
          <button
            type="button"
            class="flex items-center gap-2 rounded bg-red-600 px-3 py-1.5 text-sm font-medium hover:bg-red-700"
            @click="openCloseRegisterModal"
          >
            <XCircleIcon class="h-5 w-5" />
            Fechar Caixa
          </button>
        </div>
      </header>

      <div class="flex min-h-0 flex-1 flex-col gap-4 px-4 pt-4 pb-0">
        <div class="flex shrink-0 items-center justify-between rounded-lg border border-slate-200 bg-white p-4">
          <div>
            <p class="text-xs text-slate-500">Saldo do Caixa</p>
            <p class="text-xl font-bold text-slate-900">{{ formatCurrency(cashRegisterStore.balance) }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-slate-500">Itens da Venda</p>
            <p class="text-xl font-bold text-slate-900">{{ cartStore.totalCount }}</p>
          </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-3">
          <div class="flex flex-col space-y-4 lg:col-span-2">
            <div>
              <label v-if="lastScannedCode" class="mb-1 block text-xs text-slate-500">
                Último código: {{ lastScannedCode }}
              </label>
              <input
                id="product-search"
                v-model="searchQuery"
                type="text"
                placeholder="Bipar ou digitar produto..."
                class="input-base w-full text-lg"
                autofocus
                @input="handleSearchInput"
                @keyup.enter="handleBarcodeSearch"
              >
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto rounded-lg border border-slate-200 bg-white">
              <div v-if="loadingProducts" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Buscando...</p>
              </div>
              <div v-else-if="products.length === 0 && searchQuery.length >= 2" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Nenhum produto encontrado.</p>
              </div>
              <div v-else-if="products.length === 0" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Digite para buscar.</p>
              </div>
              <div v-else class="grid grid-cols-2 gap-2 p-2 sm:grid-cols-3 lg:grid-cols-4">
                <button
                  v-for="product in products"
                  :key="product.id"
                  type="button"
                  class="flex flex-col rounded-lg border border-slate-200 bg-white p-3 text-left transition hover:border-blue-300 hover:bg-blue-50"
                  :class="{
                    'cursor-not-allowed opacity-50': !(product.current_stock ?? product.stock_quantity) || (product.current_stock ?? product.stock_quantity) === 0,
                  }"
                  :disabled="!(product.current_stock ?? product.stock_quantity) || (product.current_stock ?? product.stock_quantity) === 0"
                  @click="handleAddProduct(product)"
                >
                  <p class="text-sm font-semibold text-slate-800">{{ product.name }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ product.variants?.[0]?.sku ?? product.sku ?? '-' }}</p>
                  <p class="mt-2 text-sm font-bold text-blue-600">
                    {{ formatCurrency(product.effective_price ?? product.sell_price) }}
                  </p>
                  <p v-if="(product.current_stock ?? product.stock_quantity) > 0" class="mt-1 text-xs text-slate-400">
                    Estoque: {{ product.current_stock ?? product.stock_quantity }}
                  </p>
                  <p v-else class="mt-1 text-xs text-red-500">Sem estoque</p>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-col rounded-lg border border-slate-200 bg-white lg:col-span-1">
            <div class="border-b border-slate-200 p-4">
              <h3 class="text-lg font-semibold text-slate-800">Itens da Venda</h3>
            </div>

            <div
              ref="cartListRef"
              tabindex="-1"
              class="min-h-0 flex-1 overflow-y-auto p-4 outline-none"
            >
              <div v-if="cartStore.items.length === 0" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-400">Nenhum item.</p>
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="(item, index) in cartStore.items"
                  :key="index"
                  class="cursor-pointer rounded-lg border p-3 transition-colors"
                  :class="selectedCartIndex === index ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                  @click="selectedCartIndex = index"
                >
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <p class="text-sm font-semibold text-slate-800">{{ item.product.name }}</p>
                      <p class="mt-1 text-xs text-slate-500">
                        {{ formatCurrency(item.unit_price) }} x {{ item.quantity }}
                      </p>
                    </div>
                    <button
                      type="button"
                      class="ml-2 text-red-500 hover:text-red-700"
                      @click.stop="handleRemoveItem(index)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                      @change="(e) => handleUpdateQuantity(index, parseInt(e.target.value, 10) || 1)"
                    >
                    <button
                      type="button"
                      class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50"
                      @click="handleUpdateQuantity(index, item.quantity + 1)"
                    >
                      +
                    </button>
                    <span class="ml-auto text-sm font-semibold text-slate-800">{{ formatCurrency(item.total) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-200 bg-slate-50 p-4">
              <div class="mb-4 flex items-center justify-between">
                <span class="text-lg font-semibold text-slate-700">TOTAL</span>
                <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(cartTotal) }}</span>
              </div>
              <div class="flex flex-wrap justify-end gap-2">
                <Button variant="outline" class="border-slate-300 text-slate-700 hover:bg-slate-50" @click="showHelpModal = true">
                  F1 - Ajuda
                </Button>
                <Button variant="outline" class="border-slate-300 text-slate-700 hover:bg-slate-50" @click="showPriceCheckModal = true">
                  F2 - Consultar Preço
                </Button>
                <Button variant="outline" class="border-red-300 text-red-600 hover:bg-red-50" @click="handleCancelSale">
                  F4 - Cancelar
                </Button>
                <Button variant="primary" @click="showCheckoutModal = true">
                  F10 - Finalizar
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div class="-mx-4 flex shrink-0 flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-slate-800 px-4 py-2 text-sm text-white">
          <span
            v-for="s in shortcuts"
            :key="s.key"
            class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 font-medium ring-1 ring-slate-600"
          >
            <kbd class="rounded bg-slate-700 px-1.5 py-0.5 font-mono text-xs">{{ s.key }}</kbd>
            <span>{{ s.label }}</span>
          </span>
        </div>
      </div>

      <StockAvailabilityModal mode="price-check" :is-open="showPriceCheckModal" @close="handlePriceCheckClose" />

      <Modal :is-open="showHelpModal" title="Atalhos do PDV" @close="closeHelp">
        <ul class="space-y-2 text-slate-700">
          <li v-for="s in shortcuts" :key="s.key" class="flex items-center gap-2">
            <kbd class="rounded bg-slate-200 px-2 py-0.5 font-mono text-sm">{{ s.key }}</kbd>
            <span>{{ s.label }}</span>
          </li>
        </ul>
      </Modal>

      <Modal :is-open="showCheckoutModal" title="Finalizar Venda" @close="closeCheckout">
        <div class="space-y-4">
          <p class="text-lg font-semibold text-slate-800">Total: {{ formatCurrency(cartTotal) }}</p>
          <p class="text-sm text-slate-500">Pagamento em dinheiro. Confirme para encerrar.</p>
          <div class="flex justify-end gap-2">
            <Button variant="outline" @click="closeCheckout">Voltar</Button>
            <Button variant="primary" @click="confirmCheckout">Confirmar e finalizar</Button>
          </div>
        </div>
      </Modal>

      <Modal :is-open="showCustomerModal" title="Identificar Cliente" @close="closeCustomer">
        <form class="space-y-4" @submit.prevent="handleCustomerSubmit">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">CPF</label>
            <input
              v-model="customerCpf"
              type="text"
              placeholder="000.000.000-00"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="closeCustomer">Fechar</Button>
            <Button type="submit" variant="primary">Identificar</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="showCloseRegisterModal" title="Fechar Caixa" @close="closeCloseRegisterModal">
        <div class="space-y-4">
          <p class="text-sm text-slate-600">
            Saldo atual em caixa: <strong>{{ formatCurrency(cashRegisterStore.balance) }}</strong>
          </p>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Saldo final (contagem)</label>
            <input
              v-model="closeRegisterFinalBalance"
              type="number"
              step="0.01"
              min="0"
              placeholder="0,00"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button variant="outline" @click="closeCloseRegisterModal">Cancelar</Button>
            <Button
              variant="primary"
              class="bg-red-600 hover:bg-red-700"
              :disabled="closeRegisterLoading"
              @click="confirmCloseRegister"
            >
              {{ closeRegisterLoading ? 'Fechando...' : 'Confirmar e fechar caixa' }}
            </Button>
          </div>
        </div>
      </Modal>
    </template>
  </div>
</template>
