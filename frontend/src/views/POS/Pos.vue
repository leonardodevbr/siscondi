<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter, onBeforeRouteLeave } from 'vue-router';
import { useCashRegisterStore } from '@/stores/cashRegister';
import { useCartStore } from '@/stores/cart';
import { setupCartPersist } from '@/stores/cart';
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
import { ArrowsPointingOutIcon, ArrowsPointingInIcon, XCircleIcon, EyeIcon, EyeSlashIcon, ShoppingCartIcon, PhotoIcon } from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const router = useRouter();
const cashRegisterStore = useCashRegisterStore();
const cartStore = useCartStore();
const authStore = useAuthStore();
const appStore = useAppStore();
const toast = useToast();
const { confirm, info } = useAlert();

const searchQuery = ref('');
const lastScannedCode = ref('');
const scanBuffer = ref('');
const scanLastKeyTime = ref(0);
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
const showStartSaleModal = ref(false);
const startSaleCpfInput = ref('');
const quantityMultiplier = ref(1);
const lastScannedProduct = ref(null);
const isBalanceVisible = ref(false);
const balanceVisibilityTimeout = ref(null);
const isLoading = ref(true);
const loadingProgress = ref(0);

const cartTotal = computed(() => cartStore.subtotal);

const isIdle = computed(() => !cartStore.saleStarted);
const operatorName = computed(() => authStore.user?.name ?? 'Operador');
const branchName = computed(() => {
  const b = appStore.currentBranch ?? authStore.user?.branch;
  return b?.name ?? 'Filial não definida';
});
const customerLabel = computed(() => {
  const c = cartStore.customer;
  if (!c?.document) return 'Consumidor Final';
  const d = String(c.document).replace(/\D/g, '');
  if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  return c.document;
});

const shortcutsIdle = [{ key: 'F1', label: 'Iniciar Venda' }];
const shortcutsSale = [
  { key: 'F1', label: 'Ajuda' },
  { key: 'F2', label: 'Consultar Preço' },
  { key: 'F3', label: 'Remover Item' },
  { key: 'F4', label: 'Cancelar Venda' },
  { key: 'F7', label: 'Identificar Cliente' },
  { key: 'F10', label: 'Finalizar Venda' },
  { key: 'ESC', label: 'Limpar / Fechar' },
];
const shortcuts = computed(() => (isIdle.value ? shortcutsIdle : shortcutsSale));

function formatVariantLabel(attributes) {
  if (!attributes || Object.keys(attributes).length === 0) {
    return '';
  }
  const values = [];
  const sizeKeys = ['tamanho', 'size', 'tam'];
  const colorKeys = ['cor', 'color', 'colour'];
  for (const sizeKey of sizeKeys) {
    for (const [key, value] of Object.entries(attributes)) {
      if (key.toLowerCase().trim() === sizeKey) {
        values.push(String(value).trim());
        break;
      }
    }
  }
  for (const colorKey of colorKeys) {
    for (const [key, value] of Object.entries(attributes)) {
      if (key.toLowerCase().trim() === colorKey) {
        values.push(String(value).trim());
        break;
      }
    }
  }
  return values.length > 0 ? values.join(' / ') : '';
}

function formatProductNameWithVariant(product) {
  if (!product.variants || product.variants.length === 0) {
    return product.name;
  }
  const variant = product.variants[0];
  const attrs = variant.attributes ?? {};
  const variantLabel = formatVariantLabel(attrs);
  return variantLabel ? `${product.name} - ${variantLabel}` : product.name;
}

function formatCartItemName(item) {
  const variantLabel = formatVariantLabel(item.variant_attributes);
  return variantLabel ? `${item.product.name} - ${variantLabel}` : item.product.name;
}

async function checkCashRegisterStatus() {
  try {
    await cashRegisterStore.checkStatus();
  } catch {
    toast.error('Erro ao verificar status do caixa.');
  }
}

async function initializePDV() {
  isLoading.value = true;
  loadingProgress.value = 0;
  
  const progressInterval = setInterval(() => {
    if (loadingProgress.value < 90) {
      loadingProgress.value += 2;
    }
  }, 30);
  
  const minTime = new Promise((resolve) => setTimeout(resolve, 1500));
  const statusCheck = checkCashRegisterStatus();
  
  await Promise.all([minTime, statusCheck]);
  
  clearInterval(progressInterval);
  loadingProgress.value = 100;
  
  await new Promise((resolve) => setTimeout(resolve, 200));
  
  isLoading.value = false;
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

function viaScan(code) {
  return /^\d+$/.test(code) || code.length >= 8;
}

function parseQuantityMultiplier(input) {
  const trimmed = String(input).trim();
  if (!trimmed) return { quantity: 1, code: '' };
  
  const patterns = [
    /^(\d+)[x*]\s*(.+)$/i,
    /^[x*](\d+)\s*(.+)$/i,
    /^(\d+)[x*](.+)$/i,
    /^[x*](\d+)(.+)$/i,
  ];
  
  for (const pattern of patterns) {
    const match = trimmed.match(pattern);
    if (match) {
      const qty = parseInt(match[1] || match[2], 10);
      const code = (match[2] || match[3] || '').trim();
      if (qty > 0 && qty <= 999 && code) {
        return { quantity: qty, code };
      }
    }
  }
  return { quantity: 1, code: trimmed };
}

async function processScanApi(code, quantity = 1) {
  const { data } = await api.get('/inventory/scan', { params: { code } });
  const stock = data.current_stock ?? 0;
  if (stock < quantity) {
    toast.error(`Estoque insuficiente. Disponível: ${stock}`);
    return;
  }
  const product = {
    id: data.product_id,
    name: data.name,
    price: data.price ?? 0,
    current_stock: stock,
    image: data.image ?? null,
    variants: data.variation_id ? [
      {
        id: data.variation_id,
        attributes: data.variation_attributes ?? {},
        current_stock: stock,
      },
    ] : [],
  };
  lastScannedProduct.value = {
    ...product,
    variant_id: data.variation_id,
    quantity_added: quantity,
    variant_stock: stock,
    price: data.price ?? 0,
  };
  cartStore.addItem(product, quantity, data.variation_id);
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
  searchQuery.value = '';
  products.value = [];
}

async function runProductSearchAndAdd(code, quantity = 1) {
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
      return;
    }
    const variant = product.variants?.find((v) => v.barcode && String(v.barcode) === code) ?? product.variants?.[0];
    if (!variant) {
      toast.error('Produto sem variação.');
      return;
    }
    const stock = variant.current_stock ?? product.current_stock ?? 0;
    if (stock < quantity) {
      toast.error(`Estoque insuficiente. Disponível: ${stock}`);
      return;
    }
    const effectivePrice = variant.sell_price ?? product.sell_price ?? product.effective_price ?? 0;
    lastScannedProduct.value = {
      ...product,
      variant_id: variant.id,
      quantity_added: quantity,
      variant_stock: stock,
      image: variant.image ?? product.image ?? null,
      price: effectivePrice,
    };
    cartStore.addItem(product, quantity, variant.id);
    if (searchTimeout.value) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }
    searchQuery.value = '';
    products.value = [];
  } catch {
    toast.error('Erro ao buscar produto.');
  }
}

async function handleScannedCode(code) {
  const c = String(code).trim();
  if (!c) return;
  
  const parsed = parseQuantityMultiplier(c);
  quantityMultiplier.value = parsed.quantity;
  lastScannedCode.value = parsed.code;

  try {
    if (viaScan(parsed.code)) {
      try {
        await processScanApi(parsed.code, parsed.quantity);
      } catch (err) {
        const msg = err.response?.data?.message ?? 'Produto não encontrado.';
        toast.error(msg);
        lastScannedProduct.value = null;
      }
    } else {
      await runProductSearchAndAdd(parsed.code, parsed.quantity);
    }
  } finally {
    quantityMultiplier.value = 1;
    clearScanAndFocus();
  }
}

function handleBarcodeSearch(e) {
  if (e.key !== 'Enter') return;
  const code = searchQuery.value.trim();
  if (!code) return;
  handleScannedCode(code);
}

function handleScanBufferKeydown(e) {
  if (!cashRegisterStore.isOpen || !cartStore.saleStarted) return;
  const el = document.activeElement;
  const tag = el?.tagName?.toLowerCase();
  if (tag === 'input' || tag === 'textarea') return;

  const key = e.key;
  const now = Date.now();

  if (key === 'Enter') {
    const buf = scanBuffer.value.trim();
    const rapid = now - scanLastKeyTime.value < 50;
    scanBuffer.value = '';
    if (buf && rapid) {
      e.preventDefault();
      e.stopPropagation();
      handleScannedCode(buf);
    }
    return;
  }

  if (key.length === 1 && /[\dA-Za-z\-.]/.test(key)) {
    if (now - scanLastKeyTime.value > 50) scanBuffer.value = '';
    scanBuffer.value += key;
    scanLastKeyTime.value = now;
  } else {
    scanBuffer.value = '';
  }
}

function handleAddProduct(product) {
  try {
    const v = product.variants?.[0];
    const stock = v?.current_stock ?? product.current_stock ?? product.stock_quantity ?? 0;
    if (stock < 1) {
      toast.error('Sem estoque nesta filial.');
      return;
    }
    if (!v) {
      toast.error('Produto sem variação.');
      return;
    }
    const effectivePrice = v.sell_price ?? product.sell_price ?? product.effective_price ?? 0;
    lastScannedProduct.value = {
      ...product,
      variant_id: v.id,
      quantity_added: 1,
      variant_stock: stock,
      image: v.image ?? product.image ?? null,
      price: effectivePrice,
    };
    cartStore.addItem(product, 1, v.id);
    if (searchTimeout.value) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }
    searchQuery.value = '';
    products.value = [];
  } catch (err) {
    toast.error(err.message ?? 'Erro ao adicionar.');
  }
}

async function handleRemoveItem(index) {
  const item = cartStore.items[index];
  if (!item) return;
  
  const confirmed = await confirm(
    'Remover Item?',
    'Tem certeza que deseja remover este item da venda?',
    'Sim, Remover',
    'red'
  );
  
  if (confirmed) {
    cartStore.removeItem(index);
    // TODO: Solicitar senha de gerente se user.role === 'seller'
  }
}

function handleUpdateQuantity(index, newQuantity) {
  try {
    cartStore.updateQuantity(index, parseInt(newQuantity, 10) || 1);
  } catch (err) {
    toast.error(err.message ?? 'Erro ao atualizar.');
  }
}

function handleClearCart() {
  cartStore.clearForCancel();
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
    cartStore.clearForFinalize();
    await cashRegisterStore.checkStatus();
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Erro ao finalizar venda.';
    toast.error(msg);
  }
}

async function handleCancelSale() {
  if (cartStore.items.length > 0) {
    const ok = await confirm(
      'Cancelar Venda',
      'Tem certeza que deseja cancelar a venda atual? Todos os itens serão removidos.',
      'Sim, cancelar',
      'red'
    );
    if (ok) {
      cartStore.clearForCancel();
      lastScannedProduct.value = null;
      lastScannedCode.value = '';
      searchQuery.value = '';
      products.value = [];
      quantityMultiplier.value = 1;
      nextTick(focusSearch);
    }
  } else {
    const ok = await confirm(
      'Reiniciar Venda',
      'Deseja reiniciar a venda atual? O cliente será removido e a tela voltará ao estado inicial.',
      'Sim, reiniciar',
      'blue'
    );
    if (ok) {
      cartStore.setCustomer(null);
      cartStore.setSaleStarted(false);
      lastScannedProduct.value = null;
      lastScannedCode.value = '';
      searchQuery.value = '';
      products.value = [];
      quantityMultiplier.value = 1;
    }
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
  if (cartStore.items.length > 0) {
    toast.error('Não é possível fechar o caixa com uma venda em andamento. Finalize ou cancele a venda atual.');
    return;
  }
  closeRegisterFinalBalance.value = String(cashRegisterStore.balance ?? 0);
  showCloseRegisterModal.value = true;
}

function closeCloseRegisterModal() {
  showCloseRegisterModal.value = false;
  closeRegisterFinalBalance.value = '';
}

function openStartSaleModal() {
  startSaleCpfInput.value = '';
  showStartSaleModal.value = true;
}

function closeStartSaleModal() {
  showStartSaleModal.value = false;
  startSaleCpfInput.value = '';
}

function confirmStartSale() {
  const raw = startSaleCpfInput.value?.replace(/\D/g, '').trim() ?? '';
  if (raw) {
    cartStore.setCustomer({ document: raw });
  } else {
    cartStore.setCustomer(null);
  }
  cartStore.setSaleStarted(true);
  closeStartSaleModal();
  nextTick(focusSearch);
}

function skipStartSale() {
  cartStore.setCustomer(null);
  cartStore.setSaleStarted(true);
  closeStartSaleModal();
  nextTick(focusSearch);
}

function hideBalanceAfterTimeout() {
  if (balanceVisibilityTimeout.value) {
    clearTimeout(balanceVisibilityTimeout.value);
  }
  balanceVisibilityTimeout.value = setTimeout(() => {
    isBalanceVisible.value = false;
    balanceVisibilityTimeout.value = null;
  }, 15000);
}

async function requestBalanceAccess() {
  const user = authStore.user;
  const isAdmin = user?.roles?.some((r) => {
    if (typeof r === 'string') {
      return r === 'super-admin' || r === 'admin' || r === 'manager';
    }
    return r?.name === 'super-admin' || r?.name === 'admin' || r?.name === 'manager';
  });
  
  if (isAdmin) {
    const confirmed = await confirm(
      'Visualizar Saldo',
      'Deseja visualizar o saldo do caixa?',
      'Sim, visualizar',
      'blue'
    );
    if (confirmed) {
      isBalanceVisible.value = true;
      hideBalanceAfterTimeout();
    }
    return;
  }
  
  const { value: password } = await Swal.fire({
    title: 'Senha de Gerente',
    text: 'Informe a Senha de Gerente para visualizar o saldo',
    input: 'password',
    inputPlaceholder: 'Digite a senha',
    showCancelButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#64748b',
    inputValidator: (value) => {
      if (!value) {
        return 'Por favor, informe a senha';
      }
    },
    allowOutsideClick: false,
    allowEscapeKey: true,
  });
  
  if (password) {
    const correctPassword = 'admin123';
    if (password === correctPassword) {
      isBalanceVisible.value = true;
      hideBalanceAfterTimeout();
      toast.success('Acesso liberado. O saldo será ocultado em 15 segundos.');
    } else {
      toast.error('Senha incorreta.');
    }
  }
}

function toggleBalanceVisibility() {
  if (isBalanceVisible.value) {
    isBalanceVisible.value = false;
    if (balanceVisibilityTimeout.value) {
      clearTimeout(balanceVisibilityTimeout.value);
      balanceVisibilityTimeout.value = null;
    }
  } else {
    requestBalanceAccess();
  }
}

function handleReloadBlock(e) {
  if (!cashRegisterStore.isOpen) return;
  const k = e.key;
  const mod = e.ctrlKey || e.metaKey;
  const reload =
    k === 'F5' ||
    (mod && (k === 'r' || k === 'R')) ||
    (mod && e.shiftKey && (k === 'r' || k === 'R'));
  if (reload) {
    e.preventDefault();
    e.stopPropagation();
    toast.warning('Para atualizar, encerre a venda.');
  }
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

  if (key === 'Escape') {
    e.preventDefault();
    if (showStartSaleModal.value) {
      skipStartSale();
      return;
    }
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
    if (cartStore.saleStarted) {
      if (searchQuery.value.trim()) {
        searchQuery.value = '';
        products.value = [];
        focusSearch();
      } else {
        focusSearch();
      }
    }
    return;
  }

  if (!isF) return;
  e.preventDefault();

  if (key === 'F1') {
    if (isIdle.value && !showStartSaleModal.value) {
      openStartSaleModal();
      return;
    }
    if (isIdle.value && showStartSaleModal.value) {
      skipStartSale();
      return;
    }
    if (cartStore.saleStarted) {
      showHelpModal.value = true;
    }
    return;
  }

  if (isIdle.value) return;

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
    e.preventDefault();
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
  await initializePDV();
  cartStore.hydrate();
  setupCartPersist(cartStore);
  if (cashRegisterStore.isOpen && cartStore.saleStarted) {
    await nextTick();
    focusSearch();
  }
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('keydown', handleScanBufferKeydown, true);
  window.addEventListener('keydown', handleReloadBlock, true);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('keydown', handleScanBufferKeydown, true);
  window.removeEventListener('keydown', handleReloadBlock, true);
  if (balanceVisibilityTimeout.value) {
    clearTimeout(balanceVisibilityTimeout.value);
  }
});
</script>

<template>
  <div class="flex h-full min-h-0 flex-col overflow-x-hidden">
    <div
      v-if="isLoading"
      class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-white"
    >
      <div class="text-center">
        <h1 class="mb-4 text-3xl font-bold text-slate-800">Adonai System</h1>
        <p class="mb-6 text-lg text-slate-600">Verificando Status do Caixa...</p>
        <div class="mx-auto w-64 rounded-full bg-slate-200">
          <div
            class="h-2 rounded-full bg-blue-600 transition-all duration-300"
            :style="{ width: `${loadingProgress}%` }"
          />
        </div>
      </div>
    </div>

    <PosClosedState v-if="!isLoading && !cashRegisterStore.isOpen" />

    <template v-else-if="!isLoading">
      <header class="flex shrink-0 items-center justify-between gap-4 bg-slate-800 px-4 py-2 text-white">
        <div class="font-semibold">
          PDV
        </div>
        <div class="flex flex-1 items-center justify-center gap-4 text-sm">
          <span>{{ operatorName }}</span>
          <span class="text-slate-400">|</span>
          <span>{{ branchName }}</span>
          <template v-if="cartStore.saleStarted">
            <span class="text-slate-400">|</span>
            <span>Cliente: {{ customerLabel }}</span>
          </template>
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

      <div v-if="isIdle" class="flex min-h-0 flex-1 flex-col pb-16">
        <div class="flex flex-1 flex-col items-center justify-center gap-6 px-4">
          <div class="text-center">
            <h2 class="text-4xl font-bold text-slate-800">
              CAIXA LIVRE
            </h2>
            <p class="mt-2 text-xl text-slate-600">
              PRÓXIMO CLIENTE
            </p>
          </div>
          <p class="mb-4 text-slate-500">
            Pressione F1 para iniciar venda
          </p>
          <button
            type="button"
            class="flex items-center gap-3 rounded-lg bg-blue-600 px-6 py-3 text-lg font-semibold text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            @click="openStartSaleModal"
          >
            <ShoppingCartIcon class="h-6 w-6" />
            Iniciar Nova Venda (F1)
          </button>
        </div>
      </div>

      <div v-else class="flex min-h-0 flex-1 flex-col gap-4 px-4 pt-4 pb-16">
        <div class="flex shrink-0 items-center justify-between rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex items-center gap-2">
            <div>
              <p class="text-xs text-slate-500">Saldo do Caixa</p>
              <p class="text-xl font-bold text-slate-900">
                {{ isBalanceVisible ? formatCurrency(cashRegisterStore.balance) : 'R$ ••••••' }}
              </p>
            </div>
            <button
              type="button"
              class="cursor-pointer rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
              @click="toggleBalanceVisibility"
            >
              <EyeIcon v-if="!isBalanceVisible" class="h-5 w-5" />
              <EyeSlashIcon v-else class="h-5 w-5" />
            </button>
          </div>
          <div class="text-right">
            <p class="text-xs text-slate-500">Itens da Venda</p>
            <p class="text-xl font-bold text-slate-900">{{ cartStore.totalCount }}</p>
          </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 xl:grid-cols-3">
          <div class="flex flex-col space-y-4 lg:col-span-2">
            <div>
              <div v-if="lastScannedCode && !lastScannedProduct" class="mb-1">
                <label class="block text-xs text-slate-500">Último código: {{ lastScannedCode }}</label>
              </div>
              <div v-if="quantityMultiplier > 1 || lastScannedProduct" class="mb-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3">
                <div v-if="quantityMultiplier > 1" class="flex items-center gap-2 rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">
                  <span>Multiplicador: {{ quantityMultiplier }}x</span>
                </div>
                <div v-if="lastScannedProduct" class="flex flex-1 items-center gap-3">
                  <div v-if="lastScannedProduct.image" class="h-16 w-16 shrink-0 overflow-hidden rounded">
                    <img
                      :src="lastScannedProduct.image"
                      :alt="lastScannedProduct.name"
                      class="h-full w-full object-cover"
                    >
                  </div>
                  <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded bg-gray-200">
                    <PhotoIcon class="h-8 w-8 text-gray-400" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 leading-tight">{{ lastScannedProduct.name }}</p>
                    <div class="mt-1">
                      <p class="text-xl font-bold text-blue-600">
                        {{ formatCurrency(lastScannedProduct.price ?? 0) }}
                      </p>
                      <p v-if="lastScannedProduct.quantity_added > 1" class="text-sm font-semibold text-slate-600">
                        Total: {{ formatCurrency((lastScannedProduct.price ?? 0) * lastScannedProduct.quantity_added) }}
                      </p>
                      <p v-if="lastScannedProduct.quantity_added > 1" class="mt-1 text-xs text-blue-600">
                        +{{ lastScannedProduct.quantity_added }} itens
                      </p>
                    </div>
                  </div>
                </div>
              </div>
              <input
                id="product-search"
                v-model="searchQuery"
                name="product-search-input"
                type="text"
                placeholder="Bipar ou digitar produto... (ex: x3 7891234567890)"
                class="input-base w-full text-lg"
                autocomplete="off"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                data-form-type="other"
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
              <div v-else class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                  v-for="product in products"
                  :key="product.id"
                  type="button"
                  class="flex flex-col rounded-lg border border-slate-200 bg-white p-4 text-left transition hover:border-blue-300 hover:bg-blue-50"
                  :class="{
                    'cursor-not-allowed opacity-50': (product.current_stock ?? product.stock_quantity) === 0,
                  }"
                  :disabled="(product.current_stock ?? product.stock_quantity) === 0"
                  @click="handleAddProduct(product)"
                >
                  <p class="text-sm font-semibold leading-tight text-slate-800" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ formatProductNameWithVariant(product) }}</p>
                  <p class="mt-1.5 text-xs text-slate-500">{{ product.variants?.[0]?.sku ?? product.sku ?? '-' }}</p>
                  <p class="mt-2 text-sm font-bold text-blue-600">
                    {{ formatCurrency(product.effective_price ?? product.sell_price) }}
                  </p>
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
                      <p class="text-sm font-semibold text-slate-800">{{ formatCartItemName(item) }}</p>
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
              <div class="flex flex-nowrap justify-end gap-1.5">
                <Button variant="outline" class="border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="showHelpModal = true">
                  F1 - Ajuda
                </Button>
                <Button variant="outline" class="border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="showPriceCheckModal = true">
                  F2 - Consultar
                </Button>
                <Button variant="outline" class="border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="handleCancelSale">
                  F4 - Cancelar
                </Button>
                <Button variant="primary" class="px-3 py-1.5 text-sm" @click="showCheckoutModal = true">
                  F10 - Finalizar
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isIdle" class="fixed bottom-0 left-0 right-0 z-50 flex shrink-0 justify-center bg-slate-800 px-4 py-2 text-sm text-white">
        <span class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 font-medium ring-1 ring-slate-600">
          <kbd class="rounded bg-slate-700 px-1.5 py-0.5 font-mono text-xs">F1</kbd>
          <span>Iniciar Venda</span>
        </span>
      </div>

      <div v-else class="fixed bottom-0 left-0 right-0 z-50 flex shrink-0 flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-slate-800 px-4 py-2 text-sm text-white">
        <span
          v-for="s in shortcuts"
          :key="s.key"
          class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 font-medium ring-1 ring-slate-600"
        >
          <kbd class="rounded bg-slate-700 px-1.5 py-0.5 font-mono text-xs">{{ s.key }}</kbd>
          <span>{{ s.label }}</span>
        </span>
      </div>

      <StockAvailabilityModal mode="price-check" :is-open="showPriceCheckModal" @close="handlePriceCheckClose" />

      <Modal :is-open="showStartSaleModal" title="Identificar Cliente na Nota?" @close="skipStartSale">
        <div class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">CPF / CNPJ (opcional)</label>
            <input
              v-model="startSaleCpfInput"
              type="text"
              placeholder="000.000.000-00 ou 00.000.000/0001-00"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              @keydown.enter.prevent="confirmStartSale"
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="skipStartSale">
              Sem CPF
            </Button>
            <Button type="button" variant="primary" @click="confirmStartSale">
              Confirmar
            </Button>
          </div>
        </div>
      </Modal>

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
