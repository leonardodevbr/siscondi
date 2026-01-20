<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">
          {{ isEdit ? 'Editar Produto' : 'Novo Produto' }}
        </h2>
        <p class="text-xs text-slate-500">
          {{ isEdit ? 'Atualize as informações do produto' : 'Cadastre um novo produto com suas variações' }}
        </p>
      </div>
      <button
        @click="$router.push({ name: 'products.index' })"
        class="text-slate-600 hover:text-slate-800 text-sm"
      >
        ← Voltar
      </button>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="card">
        <div class="border-b border-slate-200 mb-4">
          <nav class="flex space-x-4">
            <button
              type="button"
              @click="activeTab = 'general'"
              :class="[
                'py-2 px-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'general'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700',
              ]"
            >
              Dados Gerais
            </button>
            <button
              type="button"
              @click="activeTab = 'variants'"
              :class="[
                'py-2 px-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'variants'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700',
              ]"
            >
              Variações ({{ form.variants.length }})
            </button>
            <button
              v-if="!isEdit"
              type="button"
              @click="activeTab = 'stock'"
              :class="[
                'py-2 px-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'stock'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-slate-500 hover:text-slate-700',
              ]"
            >
              Estoque Inicial
            </button>
          </nav>
        </div>

        <!-- Tab 1: Dados Gerais -->
        <div v-show="activeTab === 'general'" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Nome do Produto *
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Ex: Camiseta Básica"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Categoria *
              </label>
              <select
                v-model="form.category_id"
                required
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Selecione uma categoria</option>
                <option
                  v-for="category in categories"
                  :key="category.id"
                  :value="category.id"
                >
                  {{ category.name }}
                </option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Descrição
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Descrição do produto"
            />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Fornecedor
              </label>
              <select
                v-model="form.supplier_id"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Selecione um fornecedor</option>
                <option
                  v-for="supplier in suppliers"
                  :key="supplier.id"
                  :value="supplier.id"
                >
                  {{ supplier.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Preço de Custo Base
              </label>
              <input
                v-model.number="form.cost_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Preço de Venda Base *
              </label>
              <input
                v-model.number="form.sell_price"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Preço Promocional
              </label>
              <input
                v-model.number="form.promotional_price"
                type="number"
                step="0.01"
                min="0"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>
          </div>
        </div>

        <!-- Tab 2: Variações -->
        <div v-show="activeTab === 'variants'" class="space-y-4">
          <div class="flex justify-between items-center">
            <p class="text-sm text-slate-600">
              Adicione as variações do produto (cores, tamanhos, etc.)
            </p>
            <button
              type="button"
              @click="addVariant"
              class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors"
            >
              + Adicionar Variação
            </button>
          </div>

          <div v-if="form.variants.length === 0" class="text-center py-8 text-slate-500">
            Nenhuma variação adicionada. Clique em "Adicionar Variação" para começar.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="(variant, index) in form.variants"
              :key="index"
              class="border border-slate-200 rounded-lg p-4 space-y-4"
            >
              <div class="flex justify-between items-center mb-2">
                <h4 class="text-sm font-semibold text-slate-700">
                  Variação {{ index + 1 }}
                </h4>
                <button
                  type="button"
                  @click="removeVariant(index)"
                  class="text-red-600 hover:text-red-800 text-sm"
                >
                  Remover
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    SKU *
                  </label>
                  <input
                    v-model="variant.sku"
                    type="text"
                    required
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: CAM-AZUL-P"
                    @blur="generateSkuIfEmpty(variant, index)"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Código de Barras (EAN)
                  </label>
                  <input
                    v-model="variant.barcode"
                    type="text"
                    maxlength="13"
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="7891234567890"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Cor
                  </label>
                  <input
                    v-model="variant.attributes.cor"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: Azul"
                    @input="updateVariantAttributes(variant)"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Tamanho
                  </label>
                  <input
                    v-model="variant.attributes.tamanho"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: P, M, G"
                    @input="updateVariantAttributes(variant)"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Preço (Opcional - se diferente do base)
                  </label>
                  <input
                    v-model.number="variant.price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Deixe vazio para usar preço base"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Imagem (URL)
                  </label>
                  <input
                    v-model="variant.image"
                    type="text"
                    class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="https://exemplo.com/imagem.jpg"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 3: Estoque Inicial (apenas criação) -->
        <div v-if="!isEdit && activeTab === 'stock'" class="space-y-4">
          <p class="text-sm text-slate-600 mb-4">
            Defina a quantidade inicial de cada variação para a filial Matriz
          </p>

          <div v-if="form.variants.length === 0" class="text-center py-8 text-slate-500">
            Adicione variações primeiro na aba "Variações"
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="(variant, index) in form.variants"
              :key="index"
              class="flex items-center justify-between p-3 bg-slate-50 rounded"
            >
              <div class="flex-1">
                <div class="text-sm font-medium text-slate-700">
                  {{ getVariantDescription(variant) }}
                </div>
                <div class="text-xs text-slate-500">SKU: {{ variant.sku }}</div>
              </div>
              <div class="w-32">
                <input
                  v-model.number="initialStock[index]"
                  type="number"
                  min="0"
                  class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Quantidade"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          @click="$router.push({ name: 'products.index' })"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 transition-colors"
        >
          Cancelar
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:bg-slate-400 transition-colors"
        >
          <span v-if="saving">Salvando...</span>
          <span v-else>{{ isEdit ? 'Atualizar' : 'Criar' }} Produto</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useProductStore } from '@/stores/product';
import api from '@/services/api';

export default {
  name: 'ProductForm',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const toast = useToast();
    const productStore = useProductStore();

    const isEdit = computed(() => !!route.params.id);
    const activeTab = ref('general');
    const saving = ref(false);
    const categories = ref([]);
    const suppliers = ref([]);
    const initialStock = ref([]);

    const form = ref({
      name: '',
      description: '',
      category_id: '',
      supplier_id: null,
      cost_price: null,
      sell_price: '',
      promotional_price: null,
      promotional_expires_at: null,
      variants: [],
    });

    const loadCategories = async () => {
      try {
        const response = await api.get('/categories');
        categories.value = response.data.data || response.data || [];
      } catch (error) {
        console.error('Erro ao carregar categorias:', error);
      }
    };

    const loadSuppliers = async () => {
      try {
        const response = await api.get('/suppliers');
        suppliers.value = response.data.data || response.data || [];
      } catch (error) {
        console.error('Erro ao carregar fornecedores:', error);
      }
    };

    const loadProduct = async () => {
      if (!isEdit.value) return;

      try {
        const product = await productStore.fetchOne(route.params.id);
        form.value = {
          name: product.name || '',
          description: product.description || '',
          category_id: product.category_id || '',
          supplier_id: product.supplier_id || null,
          cost_price: product.cost_price || null,
          sell_price: product.sell_price || '',
          promotional_price: product.promotional_price || null,
          promotional_expires_at: product.promotional_expires_at || null,
          variants: (product.variants || []).map((v) => ({
            id: v.id,
            sku: v.sku || '',
            barcode: v.barcode || '',
            price: v.price || null,
            image: v.image || '',
            attributes: v.attributes || {},
          })),
        };
      } catch (error) {
        toast.error('Erro ao carregar produto');
        router.push({ name: 'products.index' });
      }
    };

    const addVariant = () => {
      form.value.variants.push({
        sku: '',
        barcode: '',
        price: null,
        image: '',
        attributes: {
          cor: '',
          tamanho: '',
        },
      });
      initialStock.value.push(0);
    };

    const removeVariant = (index) => {
      form.value.variants.splice(index, 1);
      initialStock.value.splice(index, 1);
    };

    const generateSkuIfEmpty = (variant, index) => {
      if (variant.sku) return;

      const baseName = form.value.name
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .substring(0, 6);
      const cor = variant.attributes.cor
        ? variant.attributes.cor.toUpperCase().substring(0, 3)
        : 'DEF';
      const tamanho = variant.attributes.tamanho
        ? variant.attributes.tamanho.toUpperCase()
        : 'UN';
      variant.sku = `${baseName}-${cor}-${tamanho}`;
    };

    const updateVariantAttributes = (variant) => {
      const attrs = {};
      if (variant.attributes.cor) attrs.cor = variant.attributes.cor;
      if (variant.attributes.tamanho) attrs.tamanho = variant.attributes.tamanho;
      variant.attributes = attrs;
    };

    const getVariantDescription = (variant) => {
      const parts = [];
      if (variant.attributes.cor) parts.push(`Cor: ${variant.attributes.cor}`);
      if (variant.attributes.tamanho) parts.push(`Tamanho: ${variant.attributes.tamanho}`);
      return parts.length > 0 ? parts.join(' / ') : 'Variação sem atributos';
    };

    const handleSubmit = async () => {
      if (form.value.variants.length === 0) {
        toast.error('Adicione pelo menos uma variação ao produto');
        activeTab.value = 'variants';
        return;
      }

      saving.value = true;

      try {
        const payload = { ...form.value };

        if (!isEdit.value && initialStock.value.length > 0) {
          payload.initial_stock = form.value.variants.map((variant, index) => ({
            quantity: initialStock.value[index] || 0,
          }));
        }

        if (isEdit.value) {
          await productStore.update(route.params.id, payload);
          toast.success('Produto atualizado com sucesso!');
        } else {
          await productStore.create(payload);
          toast.success('Produto criado com sucesso!');
        }

        router.push({ name: 'products.index' });
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar produto';
        toast.error(message);
      } finally {
        saving.value = false;
      }
    };

    onMounted(async () => {
      await loadCategories();
      await loadSuppliers();
      await loadProduct();
    });

    return {
      isEdit,
      activeTab,
      saving,
      form,
      categories,
      suppliers,
      initialStock,
      addVariant,
      removeVariant,
      generateSkuIfEmpty,
      updateVariantAttributes,
      getVariantDescription,
      handleSubmit,
    };
  },
};
</script>
