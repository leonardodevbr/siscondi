<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
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
import CheckoutModal from '@/components/Pos/CheckoutModal.vue';
import DiscountModalContent from '@/components/Pos/DiscountModalContent.vue';
import { ArrowsPointingOutIcon, ArrowsPointingInIcon, XCircleIcon, EyeIcon, EyeSlashIcon, ShoppingCartIcon, PhotoIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
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
const allowNavigation = ref(false);
const scanBuffer = ref('');
const scanLastKeyTime = ref(0);
const products = ref([]);
const loadingProducts = ref(false);
const searchTimeout = ref(null);
const showPriceCheckModal = ref(false);
const showHelpModal = ref(false);
const showCheckoutModal = ref(false);
const showCustomerModal = ref(false);
const showDiscountModal = ref(false);
const showCloseRegisterModal = ref(false);
const closeRegisterFinalBalance = ref('');
const closeRegisterLoading = ref(false);
const isFullscreen = ref(false);
const selectedCartIndex = ref(null);
const cartListRef = ref(null);
const highlightedItemId = ref(null);
const highlightItemTimeout = ref(null);
const customerCpf = ref('');
const showStartSaleModal = ref(false);
const startSaleCpfInput = ref('');
const quantityMultiplier = ref(1);
const lastScannedProduct = ref(null);
const lastScanError = ref(null);
const isBalanceVisible = ref(false);
const balanceVisibilityTimeout = ref(null);
const isLoading = ref(true);
const loadingProgress = ref(0);
const isCancellationMode = ref(false);
const isSearchMode = ref(false);
const cancelSearchMode = ref(false);
const selectedCancelSearchIndex = ref(-1);
const selectedSearchProductIndex = ref(0);
const feedbackMessage = ref(null);
const feedbackType = ref('info'); // 'info', 'error', 'warning'
const isIdleScanLoading = ref(false);

const cartTotal = computed(() => cartStore.subtotal);

const isIdle = computed(() => !cartStore.saleStarted);
const operatorName = computed(() => authStore.user?.name ?? 'Operador');
const branchName = computed(() => {
  const b = appStore.currentBranch ?? authStore.user?.branch;
  return b?.name ?? 'Filial não definida';
});
const customerLabel = computed(() => {
  const c = cartStore.customer;
  const d = String(c?.cpf_cnpj ?? c?.document ?? '').replace(/\D/g, '');
  if (!d) return 'Consumidor Final';
  if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  return c?.cpf_cnpj ?? c?.document ?? '';
});

const formattedCustomerLabel = computed(() => {
  const c = cartStore.customer;
  const doc = String(c?.cpf_cnpj ?? c?.document ?? '').replace(/\D/g, '');
  if (!c || !doc) return 'Consumidor Final';

  const firstName = c.name ? c.name.split(' ')[0] : 'Cliente';
  if (doc.length >= 11) {
    const first3 = doc.substring(0, 3);
    const last2 = doc.substring(doc.length - 2);
    const maskedDoc = doc.length === 11
      ? `${first3}.***.***-${last2}`
      : `${first3}.***.***/****-${last2}`;
    return `${firstName} - ${maskedDoc}`;
  }
  return firstName;
});

const shortcutsIdle = [{ key: 'F1', label: 'Iniciar Venda' }];
const shortcutsSale = [
  { key: 'F1', label: 'Ajuda' },
  { key: 'F2', label: 'Consultar Preço' },
  { key: 'F3', label: 'Cancelar Item' },
  { key: 'F4', label: 'Cancelar Venda' },
  { key: 'F7', label: 'Identificar Cliente' },
  { key: 'F8', label: 'Desconto/Cupom' },
  { key: 'F10', label: 'Finalizar Venda' },
  { key: 'ESC', label: 'Limpar / Fechar' },
];
const shortcuts = computed(() => (isIdle.value ? shortcutsIdle : shortcutsSale));

const cancelSearchResults = computed(() => {
  if (!cancelSearchMode.value || !isCancellationMode.value) return [];
  const q = searchQuery.value.trim().toLowerCase();
  if (q.length < 1) return [];
  return cartStore.items.filter((i) =>
    formatCartItemName(i).toLowerCase().includes(q)
  );
});

watch(cancelSearchResults, (results) => {
  if (results.length === 0) {
    selectedCancelSearchIndex.value = -1;
    return;
  }
  const i = selectedCancelSearchIndex.value;
  if (i < 0 || i >= results.length) selectedCancelSearchIndex.value = 0;
}, { immediate: true });

const searchGridCols = 3;
watch(products, (list) => {
  selectedSearchProductIndex.value = list.length > 0 ? 0 : -1;
}, { immediate: true });

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
  if (!item) return '';
  const variantLabel = formatVariantLabel(item.variant_attributes ?? {});
  const productName = item.product_name ?? item.product?.name ?? 'Produto';
  if (!variantLabel) return productName;
  return `${productName} - ${variantLabel}`;
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

  try {
    await checkCashRegisterStatus();
  } catch {
    isLoading.value = false;
    return;
  }

  if (!cashRegisterStore.isOpen) {
    isLoading.value = false;
    return;
  }

  // Caixa aberto: barra só aqui, ao identificar possível compra em andamento (cartStore.init)
  const progressInterval = setInterval(() => {
    if (loadingProgress.value < 90) {
      loadingProgress.value += 2;
    }
  }, 30);

  const minTime = new Promise((resolve) => setTimeout(resolve, 1500));
  const initCart = cartStore.init();

  await Promise.all([minTime, initCart]);

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
  if (!isSearchMode.value) {
    if (!isCancellationMode.value) {
      const value = e?.target?.value ?? searchQuery.value;
      if (!/^\d*$/.test(value)) {
        searchQuery.value = value.replace(/\D/g, '');
        return;
      }
    }
    return;
  }
  
  const q = (e?.target?.value ?? searchQuery.value);
  searchQuery.value = q;
  if (searchTimeout.value) clearTimeout(searchTimeout.value);
  const trimmed = q.trim();
  if (trimmed.length >= 2) {
    searchTimeout.value = setTimeout(() => searchProducts(trimmed), 300);
  } else {
    products.value = [];
  }
}

function clearScanAndFocus() {
  searchQuery.value = '';
  products.value = [];
  nextTick(() => {
    const el = document.querySelector('#product-search');
    if (el) {
      el.focus();
      el.select?.();
    }
  });
}

function resetToScannerMode() {
  isSearchMode.value = false;
  searchQuery.value = '';
  products.value = [];
  lastScanError.value = null;
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
  nextTick(() => {
    const el = document.querySelector('#product-search');
    if (el) {
      el.focus();
      el.select();
    }
  });
}

function viaScan(code) {
  return /^\d+$/.test(code) || code.length >= 8;
}

async function handleIdleScan(code) {
  const c = String(code ?? '').trim();
  if (!c) return;

  const parsed = parseQuantityMultiplier(c);
  const branchId = branchIdForSale();
  isIdleScanLoading.value = true;
  try {
    await cartStore.startSale(null, branchId);
  } catch (err) {
    toast.error(err.message ?? 'Erro ao iniciar venda.');
    return;
  } finally {
    isIdleScanLoading.value = false;
  }

  quantityMultiplier.value = parsed.quantity;
  lastScannedCode.value = parsed.code;
  lastScanError.value = null;

  try {
    if (viaScan(parsed.code)) {
      try {
        await processScanApi(parsed.code, parsed.quantity);
      } catch {
        lastScanError.value = 'PRODUTO NÃO CADASTRADO';
        lastScannedProduct.value = null;
      }
    } else {
      await runProductSearchAndAdd(parsed.code, parsed.quantity);
    }
  } finally {
    quantityMultiplier.value = 1;
    nextTick(focusSearch);
  }
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

function snapshotCartForHighlight() {
  return cartStore.items.map((i) => ({ id: i.id, quantity: i.quantity }));
}

function scheduleHighlight(itemId) {
  if (highlightItemTimeout.value) clearTimeout(highlightItemTimeout.value);
  highlightedItemId.value = itemId;
  highlightItemTimeout.value = setTimeout(() => {
    highlightedItemId.value = null;
    highlightItemTimeout.value = null;
  }, 2200);
}

function highlightAddedItem(prev) {
  const next = cartStore.items;
  const byId = Object.fromEntries((prev ?? []).map((p) => [p.id, p]));
  for (const it of next) {
    const p = byId[it.id];
    if (!p) {
      scheduleHighlight(it.id);
      return;
    }
    if (it.quantity > p.quantity) {
      scheduleHighlight(it.id);
      return;
    }
  }
}

async function processScanApi(code, quantity = 1) {
  let data;
  try {
    const res = await api.get('/inventory/scan', { params: { code } });
    data = res.data;
  } catch (err) {
    lastScannedProduct.value = null;
    throw err;
  }
  const stock = data.current_stock ?? 0;
  if (stock < quantity) {
    feedbackMessage.value = `Estoque insuficiente. Disponível: ${stock}`;
    feedbackType.value = 'error';
    lastScannedProduct.value = null;
    return;
  }
  try {
    const prev = snapshotCartForHighlight();
    await cartStore.addItem(code, quantity);
    highlightAddedItem(prev);
    lastScanError.value = null;
    lastScannedProduct.value = {
      id: data.product_id,
      name: data.name,
      variant_id: data.variation_id,
      quantity_added: quantity,
      variant_stock: stock,
      price: data.price ?? 0,
      image: data.image ?? null,
    };
    resetToScannerMode();
  } catch (error) {
    toast.error(error.message || 'Erro ao adicionar item.');
    lastScannedProduct.value = null;
  }
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
      feedbackMessage.value = 'Produto não cadastrado.';
      feedbackType.value = 'error';
      lastScannedProduct.value = null;
      return;
    }
    const variant = product.variants?.find((v) => v.barcode && String(v.barcode) === code) ?? product.variants?.[0];
    if (!variant) {
      feedbackMessage.value = 'Produto sem variação.';
      feedbackType.value = 'error';
      lastScannedProduct.value = null;
      return;
    }
    const stock = variant.current_stock ?? product.current_stock ?? 0;
    if (stock < quantity) {
      feedbackMessage.value = `Estoque insuficiente. Disponível: ${stock}`;
      feedbackType.value = 'error';
      lastScannedProduct.value = null;
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
    const barcodeToUse = variant.barcode || code;
    const prev = snapshotCartForHighlight();
    await cartStore.addItem(barcodeToUse, quantity);
    highlightAddedItem(prev);
    lastScanError.value = null;
    resetToScannerMode();
  } catch (error) {
    toast.error(error.message || 'Erro ao buscar produto.');
    lastScannedProduct.value = null;
  }
}

function clearCancellationAndFocus() {
  searchQuery.value = '';
  products.value = [];
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
  nextTick(focusSearch);
}

async function confirmCancellation(codeOrItem) {
  const byItem = typeof codeOrItem === 'object' && codeOrItem?.id;
  if (byItem) {
    searchQuery.value = '';
    products.value = [];
    if (searchTimeout.value) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }
  }
  const item = byItem
    ? codeOrItem
    : cartStore.items.find((i) => {
        const itemCode = i.barcode || i.sku;
        return itemCode && String(itemCode) === String(codeOrItem);
      });

  if (!item) {
    feedbackMessage.value = 'Item não encontrado na lista.';
    feedbackType.value = 'error';
    clearCancellationAndFocus();
    return;
  }

  const user = authStore.user;
  const isAdmin = user?.roles?.some((r) => {
    if (typeof r === 'string') {
      return r === 'super-admin' || r === 'admin' || r === 'manager';
    }
    return r?.name === 'super-admin' || r?.name === 'admin' || r?.name === 'manager';
  });

  const productName = formatCartItemName(item);
  const qty = item.quantity ?? 1;
  const qtyLabel = qty === 1 ? '1 un' : `${qty} un`;
  let confirmed = false;

  if (isAdmin) {
    const result = await Swal.fire({
      title: 'Confirmar Cancelamento',
      html: `Deseja remover <strong>${qtyLabel}</strong> de <strong>${productName}</strong>?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sim, Remover',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ef4444',
      focusConfirm: false,
      allowOutsideClick: false,
    });
    confirmed = result.isConfirmed;
  } else {
    const result = await Swal.fire({
      title: 'Cancelar Item',
      html: `Insira a senha de gerente para cancelar <strong>${productName}</strong>:`,
      icon: 'warning',
      input: 'text',
      inputPlaceholder: 'Senha de gerente',
      customClass: {
        input: 'swal-manager-auth-input',
      },
      inputAttributes: {
        autocomplete: 'off',
        autocapitalize: 'off',
        autocorrect: 'off',
        spellcheck: 'false',
        name: 'manager-auth-cancel',
        'data-lpignore': 'true',
        'data-1p-ignore': 'true',
        'data-bwignore': 'true',
        'data-form-type': 'other',
      },
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ef4444',
      focusConfirm: false,
      allowOutsideClick: false,
      inputValidator: (value) => {
        if (!value) {
          return 'Por favor, insira a senha.';
        }
      },
    });

    if (result.isConfirmed) {
      const correctPassword = 'admin123';
      if (result.value === correctPassword) {
        confirmed = true;
      } else {
        toast.error('Senha incorreta.');
        clearCancellationAndFocus();
        return;
      }
    }
  }

  if (!confirmed) {
    clearCancellationAndFocus();
    return;
  }

  try {
    if (byItem) {
      await cartStore.removeItemById(item.id);
    } else {
      const barcodeToSend = item.barcode || item.sku || codeOrItem;
      await cartStore.removeItemByCode(barcodeToSend);
    }
    toast.success(qty === 1 ? 'Item removido.' : 'Itens removidos.');
    isCancellationMode.value = false;
    clearCancellationAndFocus();
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Erro ao remover item.';
    feedbackMessage.value = message;
    feedbackType.value = 'error';
    isCancellationMode.value = false;
    clearCancellationAndFocus();
  }
}

async function handleScannedCode(code) {
  const c = String(code).trim();
  if (!c) return;

  if (isCancellationMode.value) {
    searchQuery.value = '';
    products.value = [];
    if (searchTimeout.value) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }
    await confirmCancellation(c);
    return;
  }

  const parsed = parseQuantityMultiplier(c);
  quantityMultiplier.value = parsed.quantity;
  lastScannedCode.value = parsed.code;
  lastScanError.value = null;
  searchQuery.value = '';
  products.value = [];
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }

  try {
    if (viaScan(parsed.code)) {
      try {
        await processScanApi(parsed.code, parsed.quantity);
      } catch (err) {
        lastScanError.value = 'PRODUTO NÃO CADASTRADO';
        lastScannedProduct.value = null;
      }
    } else {
      if (isSearchMode.value) {
        await runProductSearchAndAdd(parsed.code, parsed.quantity);
      } else {
        feedbackMessage.value = 'Digite no modo Pesquisa (F5) para buscar por nome.';
        feedbackType.value = 'info';
        lastScannedProduct.value = null;
      }
    }
  } finally {
    quantityMultiplier.value = 1;
    nextTick(() => {
      const el = document.querySelector('#product-search');
      if (el) {
        el.focus();
        el.select?.();
      }
    });
  }
}

function handleInputKeydown(e) {
  if (isSearchMode.value) {
    return;
  }
  
  if (isCancellationMode.value) {
    return;
  }
  
  const key = e.key;
  
  if (key === 'Enter' || key === 'Backspace' || key === 'Delete' || key === 'ArrowLeft' || key === 'ArrowRight' || key === 'ArrowUp' || key === 'ArrowDown' || key === 'Home' || key === 'End' || key === 'Tab' || key === 'Escape') {
    return;
  }
  
  if (e.ctrlKey || e.metaKey) {
    if (key === 'a' || key === 'A' || key === 'c' || key === 'C' || key === 'v' || key === 'V' || key === 'x' || key === 'X') {
      return;
    }
  }
  
  if (!/^\d$/.test(key)) {
    e.preventDefault();
    e.stopPropagation();
    return;
  }
}

function handleSearchKeydown(e) {
  const key = e.key;
  const inCancelSearch = isCancellationMode.value && cancelSearchMode.value;
  const results = cancelSearchResults.value;
  const hasResults = results.length > 0;
  const sel = selectedCancelSearchIndex.value;

  const inSearchWithProducts = isSearchMode.value && !inCancelSearch && products.value.length > 0;
  if (inSearchWithProducts && (key === 'ArrowUp' || key === 'ArrowDown' || key === 'ArrowLeft' || key === 'ArrowRight')) {
    e.preventDefault();
    const idx = selectedSearchProductIndex.value;
    const total = products.value.length;
    const cols = searchGridCols;
    let next = idx;
    if (key === 'ArrowRight') next = idx + 1;
    else if (key === 'ArrowLeft') next = idx - 1;
    else if (key === 'ArrowDown') next = idx + cols;
    else if (key === 'ArrowUp') next = idx - cols;
    selectedSearchProductIndex.value = Math.max(0, Math.min(next, total - 1));
    return;
  }
  if (inSearchWithProducts && key === 'Enter') {
    const idx = selectedSearchProductIndex.value;
    const prods = products.value;
    if (idx >= 0 && idx < prods.length) {
      const p = prods[idx];
      const stock = p.current_stock ?? p.stock_quantity ?? 0;
      if (stock > 0) {
        e.preventDefault();
        handleAddProduct(p);
      }
    }
    return;
  }

  if (inCancelSearch && hasResults && (key === 'ArrowDown' || key === 'ArrowUp')) {
    e.preventDefault();
    if (key === 'ArrowDown') {
      selectedCancelSearchIndex.value = sel < 0 ? 0 : Math.min(sel + 1, results.length - 1);
    } else {
      selectedCancelSearchIndex.value = sel <= 0 ? -1 : sel - 1;
    }
    return;
  }

  if (key !== 'Enter') return;
  e.preventDefault();
  if (inCancelSearch && hasResults && sel >= 0) {
    confirmCancellation(results[sel]);
    return;
  }
  const code = searchQuery.value.trim();
  if (!code) return;
  handleScannedCode(code);
}

function handleScanBufferKeydown(e) {
  if (!cashRegisterStore.isOpen) return;
  if (showDiscountModal.value) return;

  const key = e.key;
  const now = Date.now();

  if (cartStore.saleStarted) {
    if (isSearchMode.value) return;
    const el = document.activeElement;
    const tag = el?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
  }

  if (key === 'Enter') {
    const buf = scanBuffer.value.trim();
    const rapid = now - scanLastKeyTime.value < 50;
    scanBuffer.value = '';
    if (!buf) return;
    if (rapid) {
      e.preventDefault();
      e.stopPropagation();
      if (cartStore.saleStarted) {
        handleScannedCode(buf);
      } else {
        handleIdleScan(buf);
      }
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

async function handleAddProduct(product) {
  try {
    const v = product.variants?.[0];
    if (!v) {
      toast.error('Produto sem variação.');
      return;
    }
    const barcode = String(v.barcode ?? v.sku ?? v.id);
    const prev = snapshotCartForHighlight();
    await cartStore.addItem(barcode, 1);
    highlightAddedItem(prev);
    const stock = v?.current_stock ?? product.current_stock ?? product.stock_quantity ?? 0;
    const effectivePrice = v.sell_price ?? product.sell_price ?? product.effective_price ?? 0;
    lastScanError.value = null;
    lastScannedProduct.value = {
      ...product,
      variant_id: v.id,
      quantity_added: 1,
      variant_stock: stock,
      image: v.image ?? product.image ?? null,
      price: effectivePrice,
    };
    resetToScannerMode();
  } catch (err) {
    toast.error(err.message ?? 'Erro ao adicionar.');
    lastScannedProduct.value = null;
  }
}


function handleClearCart() {
  cartStore.reset();
}

function branchIdForSale() {
  const id = appStore.currentBranch?.id ?? authStore.user?.branch?.id ?? authStore.user?.branch_id;
  return id ? Number(id) : null;
}

/**
 * Chamado pelo CheckoutModal após cartStore.finish() ter sido executado com sucesso.
 * Fecha o modal e atualiza status do caixa. A limpeza do estado local (clearLocalState)
 * é feita pelo watch em cartStore.saleId quando a store chama resetState().
 *
 * @param {object|null} _completedSale - venda finalizada, para uso futuro (ex.: impressão)
 */
async function onCheckoutFinished(_completedSale) {
  showCheckoutModal.value = false;
  await cashRegisterStore.checkStatus();
}

async function handleCancelSale() {
  if (!cartStore.saleId) {
    cartStore.resetState();
    clearLocalState();
    return;
  }

  const result = await Swal.fire({
    title: 'Cancelar Venda',
    text: 'Tem certeza que deseja cancelar a venda atual? Todos os itens serão removidos e a venda será cancelada no sistema.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, cancelar (ENTER)',
    cancelButtonText: 'Cancelar (ESC)',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    focusConfirm: true,
    allowOutsideClick: false,
    footer: '<p class="swal-cpf-shortcuts">ENTER para confirmar · ESC para cancelar</p>',
  });

  if (result.isConfirmed) {
    try {
      await cartStore.cancel();
      toast.success('Venda cancelada.');
      // resetState() na store dispara o watch(saleId) -> clearLocalState() + focusSearch
    } catch (error) {
      toast.error(error.message || 'Erro ao cancelar venda.');
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

function closeDiscount() {
  showDiscountModal.value = false;
  nextTick(focusSearch);
}

async function handleApplyDiscount(type, value) {
  try {
    await cartStore.applyDiscount(type, value);
    toast.success('Desconto aplicado.');
    closeDiscount();
  } catch (err) {
    toast.error(err?.message ?? 'Erro ao aplicar desconto.');
  }
}

function onCouponApplied() {
  toast.success('Cupom aplicado com sucesso.');
  closeDiscount();
}

async function handleRemoveCoupon() {
  try {
    await cartStore.removeCoupon();
    toast.success('Cupom removido.');
  } catch (err) {
    toast.error(err?.message ?? 'Erro ao remover cupom.');
  }
}

function focusSearch() {
  const el = document.querySelector('#product-search');
  if (el) el.focus();
}

/**
 * Limpa refs locais do PDV para evitar estado zumbi entre vendas.
 * Chamado quando a store reseta (venda finalizada ou cancelada).
 */
function clearLocalState() {
  searchQuery.value = '';
  lastScannedCode.value = '';
  products.value = [];
  customerCpf.value = '';
  startSaleCpfInput.value = '';
  quantityMultiplier.value = 1;
  lastScannedProduct.value = null;
  lastScanError.value = null;
  feedbackMessage.value = null;
  feedbackType.value = 'info';
  isCancellationMode.value = false;
  isSearchMode.value = false;
  cancelSearchMode.value = false;
  selectedCancelSearchIndex.value = -1;
  selectedSearchProductIndex.value = 0;
  highlightedItemId.value = null;
  selectedCartIndex.value = null;
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
  nextTick(focusSearch);
}

function handleF3ToggleCancellationMode() {
  isCancellationMode.value = !isCancellationMode.value;
  isSearchMode.value = false;
  cancelSearchMode.value = false;
  selectedCancelSearchIndex.value = -1;
  searchQuery.value = '';
  products.value = [];
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = null;
  }
  nextTick(focusSearch);
}

function handleF5ToggleSearchMode() {
  if (isCancellationMode.value) {
    cancelSearchMode.value = !cancelSearchMode.value;
    selectedCancelSearchIndex.value = -1;
    searchQuery.value = '';
    products.value = [];
    if (searchTimeout.value) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }
    nextTick(focusSearch);
    return;
  }
  isSearchMode.value = !isSearchMode.value;
  if (isSearchMode.value) {
    isCancellationMode.value = false;
    nextTick(focusSearch);
  } else {
    isSearchMode.value = false;
    searchQuery.value = '';
    products.value = [];
    nextTick(focusSearch);
  }
}

function syncFullscreenState() {
  isFullscreen.value = !!document.fullscreenElement;
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

async function confirmStartSale() {
  try {
    const raw = startSaleCpfInput.value?.replace(/\D/g, '').trim() ?? '';
    let customerId = null;
    
    if (raw) {
      try {
        const { data } = await api.get('/customers', { params: { document: raw } });
        if (data.data && data.data.length > 0) {
          customerId = data.data[0].id;
        }
      } catch {
        // Cliente não encontrado, continuar sem cliente
      }
    }
    
    const branchId = branchIdForSale();
    await cartStore.startSale(customerId, branchId);
    closeStartSaleModal();
    nextTick(focusSearch);
  } catch (error) {
    toast.error(error.message || 'Erro ao iniciar venda.');
  }
}

async function skipStartSale() {
  try {
    const branchId = branchIdForSale();
    await cartStore.startSale(null, branchId);
    closeStartSaleModal();
    nextTick(focusSearch);
  } catch (error) {
    toast.error(error.message || 'Erro ao iniciar venda.');
  }
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
    input: 'text',
    inputPlaceholder: 'Digite a senha',
    customClass: {
      input: 'swal-manager-auth-input',
    },
    inputAttributes: {
      autocomplete: 'off',
      autocapitalize: 'off',
      autocorrect: 'off',
      spellcheck: 'false',
      name: 'manager-auth-balance',
      'data-lpignore': 'true',
      'data-1p-ignore': 'true',
      'data-bwignore': 'true',
      'data-form-type': 'other',
    },
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
      feedbackMessage.value = null;
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
  
  if (k === 'F5') {
    if (cartStore.saleStarted && !isIdle.value) {
      e.preventDefault();
      e.stopPropagation();
      handleF5ToggleSearchMode();
      return;
    }
    if (!cartStore.saleStarted || !cartStore.saleId) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    feedbackMessage.value = 'Para atualizar, encerre a venda.';
    feedbackType.value = 'warning';
    return;
  }
  
  if (!cartStore.saleStarted || !cartStore.saleId) return;
  
  const reload =
    (mod && (k === 'r' || k === 'R')) ||
    (mod && e.shiftKey && (k === 'r' || k === 'R'));
  if (reload) {
    e.preventDefault();
    e.stopPropagation();
    feedbackMessage.value = 'Para atualizar, encerre a venda.';
    feedbackType.value = 'warning';
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
    closeCloseRegisterModal();
    await router.push({ name: 'dashboard' });
  } catch (err) {
    toast.error(err.message ?? 'Erro ao fechar caixa.');
  } finally {
    closeRegisterLoading.value = false;
  }
}

function handleShortcutClick(key) {
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
    if (!showCheckoutModal.value) handleF3ToggleCancellationMode();
    return;
  }
  if (key === 'F4') {
    handleCancelSale();
    return;
  }
  if (key === 'F7') {
    handleIdentifyCustomer();
    return;
  }
  if (key === 'F8') {
    showDiscountModal.value = true;
    return;
  }
  if (key === 'F10') {
    handleF10Finalize();
    return;
  }
}

async function handleF10Finalize() {
  if (cartStore.items.length === 0) {
    toast.error('Adicione pelo menos um item.');
    return;
  }
  if (!cartStore.customer) {
    const swalResult = await Swal.fire({
      title: 'Identificar Cliente?',
      text: 'Deseja informar o CPF/CNPJ na nota?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sim (ENTER)',
      cancelButtonText: 'Não (ESC)',
      focusConfirm: true,
      allowOutsideClick: false,
      footer: '<p class="swal-cpf-shortcuts">ENTER para Sim · ESC para Não</p>',
    });
    if (swalResult.isConfirmed) {
      await handleIdentifyCustomer({ openCheckoutOnSuccess: true });
      return;
    }
  }
  showCheckoutModal.value = true;
}

function handleKeydown(e) {
  if (!cashRegisterStore.isOpen) return;
  const key = e.key;
  const isF = /^F([1-9]|1[0-2])$/.test(key);

  if (key === 'Escape') {
    e.preventDefault();
    if (feedbackMessage.value) {
      feedbackMessage.value = null;
      feedbackType.value = 'info';
      return;
    }
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
    if (showDiscountModal.value) {
      showDiscountModal.value = false;
      nextTick(focusSearch);
      return;
    }
    if (showCloseRegisterModal.value) {
      closeCloseRegisterModal();
      return;
    }
    if (cartStore.saleStarted) {
      if (isCancellationMode.value) {
        isCancellationMode.value = false;
        nextTick(focusSearch);
        return;
      }
      if (isSearchMode.value) {
        isSearchMode.value = false;
        searchQuery.value = '';
        products.value = [];
        nextTick(focusSearch);
        return;
      }
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

  if (showDiscountModal.value) return;

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
    e.preventDefault();
    handleF3ToggleCancellationMode();
    return;
  }
  if (key === 'F4') {
    e.preventDefault();
    handleCancelSale();
    return;
  }
  if (key === 'F5') {
    e.preventDefault();
    e.stopPropagation();
    handleF5ToggleSearchMode();
    return;
  }
  if (key === 'F7') {
    handleIdentifyCustomer();
    return;
  }
  if (key === 'F8') {
    showDiscountModal.value = true;
    return;
  }
  if (key === 'F10') {
    if (cartStore.items.length === 0) {
      toast.error('Adicione pelo menos um item.');
      return;
    }
    handleF10Finalize();
    return;
  }
}

function applyCpfCnpjMask(val) {
  const d = String(val ?? '').replace(/\D/g, '').slice(0, 14);
  if (d.length <= 11) {
    return d.slice(0, 3) + (d.length > 3 ? '.' + d.slice(3, 6) : '') + (d.length > 6 ? '.' + d.slice(6, 9) : '') + (d.length > 9 ? '-' + d.slice(9, 11) : '');
  }
  return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + (d.length > 12 ? '-' + d.slice(12, 14) : '');
}

function getCustomerMaskedDoc(c) {
  const doc = String(c?.cpf_cnpj ?? c?.document ?? '').replace(/\D/g, '');
  if (doc.length === 11) return `${doc.slice(0, 3)}.***.***-${doc.slice(-2)}`;
  if (doc.length >= 14) return `${doc.slice(0, 2)}.***.***/****-${doc.slice(-2)}`;
  return doc || '—';
}

async function handleIdentifyCustomer(options = {}) {
  if (!cartStore.saleStarted || !cartStore.saleId) {
    feedbackMessage.value = 'Inicie uma venda primeiro.';
    feedbackType.value = 'error';
    return;
  }

  const current = cartStore.customer;
  const hasDoc = current && String(current?.cpf_cnpj ?? current?.document ?? '').replace(/\D/g, '').length >= 11;

  if (hasDoc) {
    const name = (current?.name || '—').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const maskedDoc = getCustomerMaskedDoc(current);
    const replaceResult = await Swal.fire({
      title: 'Cliente já identificado',
      html: `
        <div class="swal-customer-preview">
          <p class="mb-1"><strong>Nome:</strong> ${name}</p>
          <p><strong>CPF/CNPJ:</strong> ${maskedDoc}</p>
        </div>
        <p class="swal-customer-preview-question">Deseja substituir ou corrigir o cliente vinculado?</p>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sim, alterar (ENTER)',
      cancelButtonText: 'Não, manter (ESC)',
      allowOutsideClick: false,
      footer: '<p class="swal-cpf-shortcuts">ENTER para alterar · ESC para manter</p>',
    });
    if (!replaceResult.isConfirmed) {
      nextTick(focusSearch);
      return;
    }
  }

  const result = await Swal.fire({
    title: 'Identificar Cliente',
    html: `
      <label for="swal-cpf-input" class="swal-cpf-label">CPF ou CNPJ</label>
      <input id="swal-cpf-input" class="swal2-input -ml-0.5 mt-0.5 swal2-input w-full" placeholder="Digite o documento" autocomplete="one-time-code" data-lpignore="true" data-form-type="other" maxlength="18">
    `,
    showCancelButton: true,
    confirmButtonText: 'Buscar (ENTER)',
    cancelButtonText: 'Cancelar (ESC)',
    allowOutsideClick: false,
    customClass: {
      input: 'swal-manager-auth-input',
    },
    preConfirm: () => {
      const input = window.document.getElementById('swal-cpf-input');
      const value = input?.value?.trim().replace(/\D/g, '') ?? '';
      if (!value || value.length < 11) {
        Swal.showValidationMessage('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos).');
        return false;
      }
      return value;
    },
    didOpen: () => {
      const input = window.document.getElementById('swal-cpf-input');
      if (!input) return;
      input.setAttribute('inputmode', 'numeric');
      input.focus();
      input.addEventListener('input', () => {
        const masked = applyCpfCnpjMask(input.value);
        input.value = masked;
        input.setSelectionRange(masked.length, masked.length);
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          Swal.clickConfirm();
        }
      });
    },
  });

  if (!result.isConfirmed || !result.value) {
    nextTick(focusSearch);
    return;
  }

  const docValue = result.value.replace(/\D/g, '');

  try {
    await cartStore.identifyCustomer(docValue);
    feedbackMessage.value = `Cliente identificado: ${formattedCustomerLabel.value}`;
    feedbackType.value = 'info';
    if (options.openCheckoutOnSuccess) showCheckoutModal.value = true;
    nextTick(focusSearch);
  } catch (error) {
    if (error.status === 404) {
      await handleQuickRegister(error.document || docValue, undefined, {
        onSuccess: () => { if (options.openCheckoutOnSuccess) showCheckoutModal.value = true; },
      });
    } else {
      feedbackMessage.value = error.message || 'Erro ao identificar cliente.';
      feedbackType.value = 'error';
      nextTick(focusSearch);
    }
  }
}

async function handleQuickRegister(doc, name, registerOptions = {}) {
  const result = await Swal.fire({
    title: 'Cliente não encontrado',
    text: 'Cadastrar rápido?',
    icon: 'question',
    input: 'text',
    inputPlaceholder: 'Nome do Cliente (Opcional)',
    showCancelButton: true,
    confirmButtonText: 'Sim, cadastrar (ENTER)',
    cancelButtonText: 'Cancelar (ESC)',
    allowOutsideClick: false,
    footer: '<p class="swal-cpf-shortcuts">ENTER para cadastrar · ESC para cancelar</p>',
    inputAttributes: {
      autocomplete: 'off',
      'data-lpignore': 'true',
      'data-form-type': 'other',
    },
    didOpen: () => {
      const input = Swal.getInput();
      if (input) {
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            Swal.clickConfirm();
          }
        });
      }
    },
  });

  if (!result.isConfirmed) {
    nextTick(focusSearch);
    return;
  }

  const nameValue = result.value?.trim() || null;

  try {
    await cartStore.quickRegisterCustomer(doc, nameValue);
    feedbackMessage.value = `Cliente cadastrado: ${formattedCustomerLabel.value}`;
    feedbackType.value = 'info';
    registerOptions.onSuccess?.();
    nextTick(focusSearch);
  } catch (error) {
    feedbackMessage.value = error.message || 'Erro ao cadastrar cliente.';
    feedbackType.value = 'error';
    nextTick(focusSearch);
  }
}

onBeforeRouteLeave((_to, _from, next) => {
  if (allowNavigation.value) {
    allowNavigation.value = false;
    next();
    return;
  }

  if (!cashRegisterStore.isOpen) {
    next();
    return;
  }

  if (!cartStore.saleStarted || !cartStore.saleId) {
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

function scrollToBottom() {
  nextTick(() => {
    if (cartListRef.value) {
      cartListRef.value.scrollTop = cartListRef.value.scrollHeight;
    }
  });
}

watch(
  () => cartStore.items.map((i) => `${i.id}:${i.quantity}`).join('|'),
  async () => {
    if (cartStore.items.length > 0) {
      await nextTick();
      if (cartListRef.value) {
        cartListRef.value.scrollTop = cartListRef.value.scrollHeight;
      }
    }
  }
);

watch(isIdle, (idle) => {
  if (!idle) nextTick(focusSearch);
});

watch(
  () => cartStore.saleId,
  (newVal, oldVal) => {
    if (oldVal != null && newVal == null) {
      clearLocalState();
    }
  }
);

onMounted(async () => {
  syncFullscreenState();
  document.addEventListener('fullscreenchange', syncFullscreenState);
  await initializePDV();
  if (cashRegisterStore.isOpen) {
    await nextTick();
    focusSearch();
  }
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('keydown', handleScanBufferKeydown, true);
  window.addEventListener('keydown', handleReloadBlock, true);
});

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', syncFullscreenState);
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('keydown', handleScanBufferKeydown, true);
  window.removeEventListener('keydown', handleReloadBlock, true);
  if (balanceVisibilityTimeout.value) clearTimeout(balanceVisibilityTimeout.value);
  if (highlightItemTimeout.value) clearTimeout(highlightItemTimeout.value);
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
        <p class="mb-6 text-lg text-slate-600">
          {{ cashRegisterStore.isOpen ? 'Verificando Status do Caixa...' : 'Carregando...' }}
        </p>
        <div
          v-if="cashRegisterStore.isOpen"
          class="mx-auto w-64 rounded-full bg-slate-200"
        >
          <div
            class="h-2 rounded-full bg-blue-600 transition-all duration-300"
            :style="{ width: `${loadingProgress}%` }"
          />
        </div>
      </div>
    </div>

    <PosClosedState v-if="!isLoading && !cashRegisterStore.isOpen" />

    <template v-else-if="!isLoading">
      <div
        v-if="feedbackMessage"
        :class="[
          'fixed top-0 left-0 right-0 z-[200] flex items-center justify-between px-4 py-3 text-sm font-medium shadow-lg',
          feedbackType === 'error' ? 'bg-red-600 text-white' : feedbackType === 'warning' ? 'bg-orange-500 text-white' : 'bg-blue-600 text-white'
        ]"
      >
        <span>{{ feedbackMessage }}</span>
        <button
          type="button"
          class="ml-4 rounded p-1 hover:bg-black/20"
          @click="feedbackMessage = null"
        >
          <XCircleIcon class="h-5 w-5" />
        </button>
      </div>
      <header :class="['flex shrink-0 items-center justify-between gap-4 bg-slate-800 px-4 py-2 text-white', feedbackMessage ? 'mt-12' : '']">
        <div class="font-semibold">
          PDV
        </div>
        <div class="flex flex-1 items-center justify-center gap-4 text-sm">
          <span>{{ operatorName }}</span>
          <span class="text-slate-400">|</span>
          <span>{{ branchName }}</span>
          <template v-if="cartStore.saleStarted">
            <span class="text-slate-400">|</span>
            <span class="font-bold text-blue-600">Cliente: {{ formattedCustomerLabel }}</span>
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

      <div v-if="isIdle" class="flex min-h-0 flex-1 flex-col pb-16 relative">
        <div
          v-if="isIdleScanLoading"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-slate-100/90"
        >
          <p class="text-lg font-semibold text-slate-700">Iniciando Venda...</p>
        </div>
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
            Pressione F1 para iniciar venda ou BIPE um produto
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
          <div class="flex min-h-0 flex-col space-y-4 lg:col-span-2">
            <div>
              <div v-if="lastScannedCode && !lastScannedProduct && lastScanError" class="mb-2 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-3">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded bg-red-100">
                  <XCircleIcon class="h-8 w-8 text-red-500" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-slate-600">Último código bipado:</p>
                  <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ lastScannedCode }}</p>
                  <p class="mt-2 text-base font-bold text-red-600">PRODUTO NÃO CADASTRADO</p>
                </div>
              </div>
              <div v-else-if="lastScannedCode && !lastScannedProduct" class="mb-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <label class="block text-xs font-medium text-slate-600">Último código bipado:</label>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ lastScannedCode }}</p>
              </div>
              <div v-if="lastScannedProduct" class="mb-2 flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3">
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
                    <p v-if="lastScannedCode" class="text-xs text-slate-400 mt-0.5">Código: {{ lastScannedCode }}</p>
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
              <div v-if="isCancellationMode" class="mb-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded bg-orange-100 px-2 py-1 text-xs font-bold text-orange-600">
                  MODO CANCELAMENTO (ESC para sair)
                </span>
                <span v-if="!cancelSearchMode" class="text-xs text-slate-500">F5 para pesquisar por nome</span>
              </div>
              <div class="relative">
                <div v-if="isCancellationMode" class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                  <TrashIcon class="h-5 w-5 text-orange-400" />
                </div>
                <div v-else-if="isSearchMode" class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                  <MagnifyingGlassIcon class="h-5 w-5 text-blue-400" />
                </div>
                <div v-else class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                  <MagnifyingGlassIcon class="h-5 w-5 text-slate-400" />
                </div>
                <input
                  id="product-search"
                  v-model="searchQuery"
                  name="pos-product-barcode-scanner"
                  type="text"
                  :placeholder="isCancellationMode
                    ? (cancelSearchMode ? 'Digite o nome do item para cancelar' : 'Bipe o código ou F5 para pesquisar por nome')
                    : (isSearchMode ? 'Digite para buscar ou bipar com multiplicador (ex: 2x 7891234567890)' : 'Bipar código de barras (F5 para modo Pesquisa)')"
                  :class="[
                    'input-base w-full text-lg pr-10',
                    isCancellationMode ? 'ring-2 ring-orange-400 focus:ring-orange-500' : ''
                  ]"
                  :maxlength="(isSearchMode || cancelSearchMode) ? undefined : 13"
                  autocomplete="one-time-code"
                  autocapitalize="off"
                  autocorrect="off"
                  spellcheck="false"
                  data-form-type="other"
                  data-lpignore="true"
                  data-1p-ignore="true"
                  data-bwignore="true"
                  data-autocomplete="off"
                  data-ignore="true"
                  role="textbox"
                  aria-label="Scanner de código de barras"
                  @input="handleSearchInput"
                  @keydown="handleSearchKeydown"
                >
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto border border-slate-200 bg-white">
              <template v-if="isCancellationMode && cancelSearchMode">
                <div v-if="cancelSearchResults.length === 0" class="flex h-32 flex-col items-center justify-center gap-1">
                  <p class="text-sm text-slate-500">
                    {{ searchQuery.trim().length >= 1 ? 'Nenhum item encontrado na venda.' : 'Digite o nome do item para buscar.' }}
                  </p>
                </div>
                <div v-else class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 lg:grid-cols-3">
                  <button
                    v-for="(cartItem, idx) in cancelSearchResults"
                    :key="cartItem.id"
                    type="button"
                    :class="[
                      'flex flex-col rounded-lg border p-3 text-left transition',
                      selectedCancelSearchIndex === idx
                        ? 'border-blue-500 ring-2 ring-blue-400 bg-blue-50'
                        : 'border-orange-200 bg-white hover:border-orange-400 hover:bg-orange-50'
                    ]"
                    @click="confirmCancellation(cartItem)"
                  >
                    <p class="text-sm font-semibold leading-tight text-slate-800" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ formatCartItemName(cartItem) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ cartItem.quantity }} un · {{ formatCurrency(cartItem.unit_price ?? 0) }}</p>
                  </button>
                </div>
              </template>
              <div v-else-if="!isSearchMode" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Pressione F5 para ativar o modo Pesquisa</p>
              </div>
              <div v-else-if="loadingProducts" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Buscando...</p>
              </div>
              <div v-else-if="products.length === 0 && searchQuery.length >= 2" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Nenhum produto encontrado.</p>
              </div>
              <div v-else-if="products.length === 0 && isSearchMode" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-500">Digite para buscar.</p>
              </div>
              <div v-else-if="isSearchMode && products.length > 0" class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                  v-for="(product, idx) in products"
                  :key="product.id"
                  type="button"
                  class="flex flex-col rounded-lg border p-4 text-left transition hover:border-blue-300 hover:bg-blue-50"
                  :class="[
                    (product.current_stock ?? product.stock_quantity) === 0 ? 'cursor-not-allowed opacity-50 border-slate-200 bg-white' : 'border-slate-200 bg-white',
                    selectedSearchProductIndex === idx ? 'border-blue-500 ring-2 ring-blue-400 bg-blue-50' : ''
                  ]"
                  :disabled="(product.current_stock ?? product.stock_quantity) === 0"
                  @click="handleAddProduct(product)"
                >
                  <p class="text-sm font-semibold leading-tight text-slate-800" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ formatProductNameWithVariant(product) }}</p>
                  <p class="mt-1.5 text-xs text-slate-500">Código: {{ product.variants?.[0]?.barcode ?? product.variants?.[0]?.sku ?? product.sku ?? '-' }}</p>
                  <p class="mt-2 text-sm font-bold text-blue-600">
                    {{ formatCurrency(product.effective_price ?? product.sell_price) }}
                  </p>
                </button>
              </div>
            </div>
          </div>

          <div class="flex h-full max-h-full flex-col rounded-lg border border-slate-200 bg-white lg:col-span-1">
            <div class="shrink-0 border-b border-slate-200 p-4">
              <h3 class="text-lg font-semibold text-slate-800">Itens da Venda</h3>
            </div>

            <div
              ref="cartListRef"
              tabindex="-1"
              class="flex-1 overflow-y-auto scroll-smooth p-4 outline-none"
              style="max-height: calc(100vh - 388px);"
            >
              <div v-if="cartStore.items.length === 0" class="flex h-32 items-center justify-center">
                <p class="text-sm text-slate-400">Nenhum item.</p>
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="(item, index) in cartStore.items"
                  :key="item.id || index"
                  :class="[
                    'rounded-lg border bg-white p-4 shadow-sm transition-all duration-200',
                    highlightedItemId === item.id
                      ? 'border-emerald-500 ring-2 ring-emerald-400 ring-offset-1'
                      : 'border-slate-200'
                  ]"
                >
                  <div class="mb-3">
                    <p class="truncate font-semibold text-slate-900">{{ formatCartItemName(item) }}</p>
                  </div>
                  <div class="mb-3 grid grid-cols-3 gap-4">
                    <div>
                      <p class="mb-1 text-xs uppercase text-gray-400">QTD</p>
                      <p class="text-base font-semibold text-slate-800">{{ item.quantity }} un</p>
                    </div>
                    <div>
                      <p class="mb-1 text-xs uppercase text-gray-400">VL. UNIT</p>
                      <p class="text-base text-gray-600">{{ formatCurrency(item.unit_price) }}</p>
                    </div>
                    <div class="text-right">
                      <p class="mb-1 text-xs uppercase text-gray-400">TOTAL</p>
                      <p class="text-lg font-bold text-blue-600">{{ formatCurrency(item.total_price) }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 border-t border-slate-100 pt-2 text-xs text-gray-300">
                    <span v-if="item.sku">SKU: {{ item.sku }}</span>
                    <span v-if="item.sku && item.barcode">|</span>
                    <span v-if="item.barcode">Código: {{ item.barcode }}</span>
                    <span v-if="!item.sku && !item.barcode" class="text-gray-300">-</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-slate-50 p-4 rounded-b-md">
              <div
                v-if="cartStore.coupon"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-green-800">
                      Cupom aplicado: {{ cartStore.coupon.code }}
                    </p>
                    <p class="mt-1 text-xs text-green-700">
                      Desconto: {{ formatCurrency(cartStore.discountAmount) }}
                    </p>
                    <ul v-if="cartStore.coupon.rules_summary && cartStore.coupon.rules_summary.length" class="mt-2 space-y-0.5 text-xs text-green-700">
                      <li v-for="(rule, i) in cartStore.coupon.rules_summary" :key="i">
                        {{ rule }}
                      </li>
                    </ul>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-red-600 hover:text-red-800 underline"
                    @click="handleRemoveCoupon"
                  >
                    Remover cupom
                  </button>
                </div>
              </div>
              <div class="mb-4 flex items-center justify-between">
                <span class="text-lg font-semibold text-slate-700">TOTAL</span>
                <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(cartStore.finalAmount || cartTotal) }}</span>
              </div>
              <div class="flex justify-end">
                <Button variant="primary" class="px-4 py-2 text-base font-semibold" @click="handleF10Finalize">
                  F10 - Finalizar Venda
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

      <div v-else class="fixed bottom-0 left-0 right-0 z-[100] flex shrink-0 flex-nowrap items-center justify-center gap-x-1.5 bg-slate-800 px-2 py-1.5 text-xs text-white shadow-lg overflow-x-auto">
        <button
          v-for="s in shortcuts"
          :key="s.key"
          type="button"
          class="inline-flex shrink-0 items-center gap-1 rounded-md bg-slate-700 px-2 py-1 font-medium text-white transition-colors hover:bg-slate-600 active:bg-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-400 focus:ring-offset-1 focus:ring-offset-slate-800"
          @click="handleShortcutClick(s.key)"
        >
          <kbd class="rounded bg-slate-600 px-1 py-0.5 font-mono text-[10px] font-bold leading-none">{{ s.key }}</kbd>
          <span class="whitespace-nowrap text-[11px]">{{ s.label }}</span>
        </button>
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

      <CheckoutModal :is-open="showCheckoutModal" @close="closeCheckout" @finish="onCheckoutFinished" />

      <Modal :is-open="showCustomerModal" title="Identificar Cliente" @close="closeCustomer">
        <form class="space-y-4" @submit.prevent="handleCustomerSubmit">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">CPF / CNPJ</label>
            <input
              v-model="customerCpf"
              type="text"
              placeholder="000.000.000-00 ou 00.000.000/0001-00"
              class="h-10 w-full rounded border border-slate-300 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              autofocus
            >
          </div>
          <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" @click="closeCustomer">Fechar</Button>
            <Button type="submit" variant="primary">Identificar</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="showDiscountModal" title="Aplicar Desconto / Cupom" @close="closeDiscount">
        <DiscountModalContent
          @apply="handleApplyDiscount"
          @close="closeDiscount"
          @coupon-applied="onCouponApplied"
        />
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
