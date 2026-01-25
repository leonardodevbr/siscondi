<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar Cupom' : 'Novo Cupom' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize as regras do cupom' : 'Cadastre um cupom de desconto ou valor fixo' }}
        </p>
      </div>
      <button
        type="button"
        class="text-slate-600 hover:text-slate-800 text-sm"
        @click="$router.push({ name: 'coupons' })"
      >
        ← Voltar
      </button>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="card p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Dados principais</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Código *</label>
            <input
              v-model="form.code"
              type="text"
              required
              maxlength="50"
              class="input-base w-full"
              placeholder="Ex: VERÃO10"
              @input="normalizeCode"
            />
            <p class="mt-1 text-xs text-slate-500">Maiúsculas, números, _ e -</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tipo *</label>
            <select
              v-model="form.type"
              required
              class="input-base w-full"
            >
              <option value="percentage">Porcentagem (%)</option>
              <option value="fixed">Valor fixo (R$)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Valor *</label>
            <input
              v-model.number="form.value"
              type="number"
              required
              step="0.01"
              min="0.01"
              class="input-base w-full"
              :placeholder="form.type === 'percentage' ? 'Ex: 10' : 'Ex: 25.00'"
            />
            <p class="mt-1 text-xs text-slate-500">
              {{ form.type === 'percentage' ? 'Percentual de desconto (0–100)' : 'Desconto em reais' }}
            </p>
          </div>
        </div>
      </div>

      <div class="card p-6">
        <button
          type="button"
          class="flex w-full items-center justify-between text-left"
          @click="showOptional = !showOptional"
        >
          <h3 class="text-sm font-semibold text-slate-800">Restrições e validade (opcional)</h3>
          <span class="text-slate-400">{{ showOptional ? '−' : '+' }}</span>
        </button>
        <div v-show="showOptional" class="mt-4 space-y-4 pt-4 border-t border-slate-200">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Valor mínimo de compra (R$)</label>
              <input
                v-model.number="form.min_purchase_amount"
                type="number"
                step="0.01"
                min="0"
                class="input-base w-full"
                placeholder="Ex: 100.00"
              />
            </div>
            <div v-if="form.type === 'percentage'">
              <label class="block text-sm font-medium text-slate-700 mb-1">Teto de desconto (R$)</label>
              <input
                v-model.number="form.max_discount_amount"
                type="number"
                step="0.01"
                min="0"
                class="input-base w-full"
                placeholder="Ex: 50.00"
              />
              <p class="mt-1 text-xs text-slate-500">Máximo em reais quando tipo é %</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Limite de usos</label>
              <input
                v-model.number="form.usage_limit"
                type="number"
                min="1"
                class="input-base w-full"
                placeholder="Ex: 100"
              />
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Válido de</label>
              <input
                v-model="form.starts_at"
                type="datetime-local"
                class="input-base w-full"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Válido até</label>
              <input
                v-model="form.expires_at"
                type="datetime-local"
                class="input-base w-full"
              />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Produtos elegíveis</label>
            <p class="text-xs text-slate-500 mb-2">Deixe vazio para todos. Se escolher algum, o cupom só valerá para itens desses produtos.</p>
            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <input
                v-model="productSearchQuery"
                type="text"
                class="input-base w-full mb-3"
                placeholder="Pesquisar produtos..."
              >
              <div v-if="selectedProducts.length" class="mb-3 flex flex-wrap gap-2">
                <span
                  v-for="p in selectedProducts"
                  :key="p.id"
                  class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-sm text-blue-800"
                >
                  {{ p.name }}
                  <button
                    type="button"
                    class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    aria-label="Remover"
                    @click="removeProductId(p.id)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </span>
              </div>
              <div class="max-h-48 overflow-y-auto rounded border border-slate-100">
                <label
                  v-for="p in filteredProductOptions"
                  :key="p.id"
                  :class="[
                    'flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50',
                    form.product_ids.includes(p.id) ? 'bg-blue-50' : ''
                  ]"
                >
                  <input
                    type="checkbox"
                    :checked="form.product_ids.includes(p.id)"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleProductId(p.id)"
                  >
                  <span class="text-sm text-slate-800">{{ p.name }}</span>
                </label>
                <p v-if="filteredProductOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                  Nenhum produto encontrado.
                </p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Categorias elegíveis</label>
            <p class="text-xs text-slate-500 mb-2">Deixe vazio para todas. Se escolher alguma, o cupom só valerá para itens dessas categorias.</p>
            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <input
                v-model="categorySearchQuery"
                type="text"
                class="input-base w-full mb-3"
                placeholder="Pesquisar categorias..."
              >
              <div v-if="selectedCategories.length" class="mb-3 flex flex-wrap gap-2">
                <span
                  v-for="c in selectedCategories"
                  :key="c.id"
                  class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-sm text-blue-800"
                >
                  {{ c.name }}
                  <button
                    type="button"
                    class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    aria-label="Remover"
                    @click="removeCategoryId(c.id)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </span>
              </div>
              <div class="max-h-48 overflow-y-auto rounded border border-slate-100">
                <label
                  v-for="c in filteredCategoryOptions"
                  :key="c.id"
                  :class="[
                    'flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50',
                    form.category_ids.includes(c.id) ? 'bg-blue-50' : ''
                  ]"
                >
                  <input
                    type="checkbox"
                    :checked="form.category_ids.includes(c.id)"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    @change="toggleCategoryId(c.id)"
                  >
                  <span class="text-sm text-slate-800">{{ c.name }}</span>
                </label>
                <p v-if="filteredCategoryOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                  Nenhuma categoria encontrada.
                </p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Tipos de pagamento aceitos</label>
            <p class="text-xs text-slate-500 mb-2">Deixe vazio para aceitar todos. Se marcar algum, o cupom só valerá para os selecionados.</p>
            <div class="flex flex-wrap gap-4">
              <label class="flex items-center gap-2">
                <input
                  v-model="form.allowed_payment_methods"
                  type="checkbox"
                  value="money"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">Dinheiro</span>
              </label>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.allowed_payment_methods"
                  type="checkbox"
                  value="pix"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">PIX</span>
              </label>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.allowed_payment_methods"
                  type="checkbox"
                  value="credit_card"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">Cartão de Crédito</span>
              </label>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.allowed_payment_methods"
                  type="checkbox"
                  value="debit_card"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">Cartão de Débito</span>
              </label>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.allowed_payment_methods"
                  type="checkbox"
                  value="store_credit"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-sm text-slate-700">Crédito Loja</span>
              </label>
            </div>
          </div>
          <div>
            <Toggle v-model="form.active" label="Cupom ativo" />
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200"
          @click="$router.push({ name: 'coupons' })"
        >
          Cancelar
        </button>
        <button
          type="submit"
          :disabled="saving || !form.code?.trim() || !form.type || (form.value == null || form.value < 0.01)"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="saving">Salvando...</span>
          <span v-else>{{ isEdit ? 'Atualizar' : 'Criar' }} cupom</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';
import Toggle from '@/components/Common/Toggle.vue';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const saving = ref(false);
const showOptional = ref(false);

const isEdit = computed(() => Boolean(route.params.id));

const form = ref({
  code: '',
  type: 'percentage',
  value: null,
  min_purchase_amount: null,
  max_discount_amount: null,
  usage_limit: null,
  starts_at: '',
  expires_at: '',
  allowed_payment_methods: [],
  product_ids: [],
  category_ids: [],
  active: true,
});

const productOptions = ref([]);
const categoryOptions = ref([]);
const productSearchQuery = ref('');
const categorySearchQuery = ref('');

const selectedProducts = computed(() =>
  productOptions.value.filter((p) => form.value.product_ids.includes(p.id))
);
const selectedCategories = computed(() =>
  categoryOptions.value.filter((c) => form.value.category_ids.includes(c.id))
);

const filteredProductOptions = computed(() => {
  const q = (productSearchQuery.value || '').trim().toLowerCase();
  const list = productOptions.value;
  if (!q) return list;
  return list.filter((p) => (p.name || '').toLowerCase().includes(q));
});

const filteredCategoryOptions = computed(() => {
  const q = (categorySearchQuery.value || '').trim().toLowerCase();
  const list = categoryOptions.value;
  if (!q) return list;
  return list.filter((c) => (c.name || '').toLowerCase().includes(q));
});

function toggleProductId(id) {
  const arr = [...(form.value.product_ids || [])];
  const i = arr.indexOf(id);
  if (i >= 0) {
    arr.splice(i, 1);
  } else {
    arr.push(id);
  }
  form.value.product_ids = arr;
}

function toggleCategoryId(id) {
  const arr = [...(form.value.category_ids || [])];
  const i = arr.indexOf(id);
  if (i >= 0) {
    arr.splice(i, 1);
  } else {
    arr.push(id);
  }
  form.value.category_ids = arr;
}

function removeProductId(id) {
  form.value.product_ids = (form.value.product_ids || []).filter((x) => x !== id);
}

function removeCategoryId(id) {
  form.value.category_ids = (form.value.category_ids || []).filter((x) => x !== id);
}

function normalizeCode() {
  const v = form.value.code || '';
  form.value.code = v
    .toUpperCase()
    .replace(/\s+/g, '-')
    .replace(/[^A-Z0-9_-]/g, '');
}

function toIsoDateTime(str) {
  if (!str || typeof str !== 'string') return null;
  const s = str.trim();
  return s ? s.slice(0, 19).replace('T', ' ') : null;
}

async function loadCoupon() {
  const id = route.params.id;
  if (!id) return;
  try {
    const { data } = await api.get(`/coupons/${id}`);
    const c = data.data ?? data;
    form.value = {
      code: c.code ?? '',
      type: c.type ?? 'percentage',
      value: c.value != null ? Number(c.value) : null,
      min_purchase_amount: c.min_purchase_amount != null ? Number(c.min_purchase_amount) : null,
      max_discount_amount: c.max_discount_amount != null ? Number(c.max_discount_amount) : null,
      usage_limit: c.usage_limit != null ? Number(c.usage_limit) : null,
      starts_at: c.starts_at ? c.starts_at.slice(0, 16) : '',
      expires_at: c.expires_at ? c.expires_at.slice(0, 16) : '',
      allowed_payment_methods: Array.isArray(c.allowed_payment_methods) ? [...c.allowed_payment_methods] : [],
      product_ids: Array.isArray(c.product_ids) ? [...c.product_ids] : [],
      category_ids: Array.isArray(c.category_ids) ? [...c.category_ids] : [],
      active: c.active !== false,
    };
    if (form.value.min_purchase_amount != null || form.value.usage_limit != null || form.value.starts_at || form.value.expires_at) {
      showOptional.value = true;
    }
  } catch (e) {
    toast.error(e.response?.data?.message || 'Erro ao carregar cupom.');
    router.push({ name: 'coupons' });
  }
}

async function handleSubmit() {
  const payload = {
    code: form.value.code.trim(),
    type: form.value.type,
    value: Number(form.value.value),
    min_purchase_amount: form.value.min_purchase_amount != null && form.value.min_purchase_amount !== '' ? Number(form.value.min_purchase_amount) : null,
    max_discount_amount: form.value.max_discount_amount != null && form.value.max_discount_amount !== '' ? Number(form.value.max_discount_amount) : null,
    usage_limit: form.value.usage_limit != null && form.value.usage_limit !== '' ? Number(form.value.usage_limit) : null,
    starts_at: toIsoDateTime(form.value.starts_at),
    expires_at: toIsoDateTime(form.value.expires_at),
    allowed_payment_methods: Array.isArray(form.value.allowed_payment_methods) && form.value.allowed_payment_methods.length > 0
      ? form.value.allowed_payment_methods
      : null,
    product_ids: Array.isArray(form.value.product_ids) && form.value.product_ids.length > 0
      ? form.value.product_ids
      : [],
    category_ids: Array.isArray(form.value.category_ids) && form.value.category_ids.length > 0
      ? form.value.category_ids
      : [],
    active: form.value.active,
  };

  saving.value = true;
  try {
    if (isEdit.value) {
      await api.put(`/coupons/${route.params.id}`, payload);
      toast.success('Cupom atualizado.');
    } else {
      await api.post('/coupons', payload);
      toast.success('Cupom criado.');
    }
    router.push({ name: 'coupons' });
  } catch (e) {
    const msg = e.response?.data?.message || 'Erro ao salvar cupom.';
    const errs = e.response?.data?.errors;
    if (errs && typeof errs === 'object') {
      const first = Object.values(errs).flat()[0];
      toast.error(first || msg);
    } else {
      toast.error(msg);
    }
  } finally {
    saving.value = false;
  }
}

async function loadOptions() {
  try {
    const [prodRes, catRes] = await Promise.all([
      api.get('/products', { params: { per_page: 500 } }),
      api.get('/categories', { params: { per_page: 500 } }),
    ]);
    productOptions.value = prodRes.data?.data ?? prodRes.data ?? [];
    categoryOptions.value = catRes.data?.data ?? catRes.data ?? [];
  } catch {
    productOptions.value = [];
    categoryOptions.value = [];
  }
}

onMounted(() => {
  loadOptions();
  loadCoupon();
});
</script>
