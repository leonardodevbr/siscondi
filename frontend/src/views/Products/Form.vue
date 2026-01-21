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
      <div class="card p-6">
        <div class="border-b border-slate-200 mb-6 pb-4">
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
          <div class="flex items-start gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Foto de Capa
              </label>
              <ImageUpload v-model="form.cover_image" size="md" />
            </div>
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
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
              :ref="(el) => setVariantRef(el, index)"
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

              <div class="flex gap-4">
                <div class="flex-shrink-0">
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Imagem
                  </label>
                  <ImageUpload v-model="variant.image" size="sm" />
                </div>
                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    SKU *
                  </label>
                  <input
                    v-model="variant.sku"
                    type="text"
                    required
                    :disabled="settingsStore.skuAutoGeneration"
                    :class="[
                      'w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                      settingsStore.skuAutoGeneration
                        ? 'border-slate-200 bg-slate-50 text-slate-500 cursor-not-allowed'
                        : 'border-slate-300',
                    ]"
                    :placeholder="settingsStore.skuAutoGeneration ? 'Gerado automaticamente' : 'Ex: CAM-AZUL-P'"
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
                    @input="handleAttributeChange(variant, index)"
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
                    @input="handleAttributeChange(variant, index)"
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
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useProductStore } from '@/stores/product';
import { useSettingsStore } from '@/stores/settings';
import ImageUpload from '@/components/Common/ImageUpload.vue';
import api from '@/services/api';

export default {
  name: 'ProductForm',
  components: {
    ImageUpload,
  },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const toast = useToast();
    const productStore = useProductStore();
    const settingsStore = useSettingsStore();

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
      cover_image: null,
      variants: [],
    });

    const variantRefs = ref([]);

    const setVariantRef = (el, index) => {
      if (el) {
        variantRefs.value[index] = el;
      }
    };

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
          cover_image: product.cover_image || null,
          variants: (product.variants || []).map((v) => ({
            id: v.id,
            sku: v.sku || '',
            barcode: v.barcode || '',
            price: v.price || null,
            image: v.image || null,
            attributes: v.attributes || {},
          })),
        };
      } catch (error) {
        toast.error('Erro ao carregar produto');
        router.push({ name: 'products.index' });
      }
    };

    const addVariant = async () => {
      const newVariant = {
        sku: '',
        barcode: '',
        price: null,
        image: null,
        attributes: {
          cor: '',
          tamanho: '',
        },
      };
      form.value.variants.unshift(newVariant);
      initialStock.value.unshift(0);

      await nextTick();
      if (settingsStore.skuAutoGeneration) {
        generateSku(newVariant, 0);
      }
      if (variantRefs.value[0]) {
        variantRefs.value[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    };

    const removeVariant = (index) => {
      form.value.variants.splice(index, 1);
      initialStock.value.splice(index, 1);
    };

    const generateSku = (variant, index) => {
      if (!settingsStore.skuAutoGeneration) {
        return;
      }

      const pattern = settingsStore.skuPattern || '{CATEGORY}-{NAME}-{SEQ}';
      let sku = pattern;

      const category = categories.value.find((c) => c.id === form.value.category_id);
      const categoryCode = category
        ? category.name
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .substring(0, 3)
        : 'PRO';

      const nameCode = form.value.name
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .substring(0, 3);

      const variantAttrs = [];
      if (variant.attributes.cor) {
        variantAttrs.push(variant.attributes.cor.toUpperCase().substring(0, 1));
      }
      if (variant.attributes.tamanho) {
        variantAttrs.push(variant.attributes.tamanho.toUpperCase().substring(0, 1));
      }
      const variantsCode = variantAttrs.length > 0 ? variantAttrs.join('-') : 'UN';

      const seq = String(index + 1).padStart(3, '0');

      sku = sku.replace(/{CATEGORY}/g, categoryCode);
      sku = sku.replace(/{NAME}/g, nameCode);
      sku = sku.replace(/{VARIANTS}/g, variantsCode);
      sku = sku.replace(/{SEQ}/g, seq);

      variant.sku = sku;
    };

    const generateSkuIfEmpty = (variant, index) => {
      if (variant.sku && !settingsStore.skuAutoGeneration) return;
      
      if (settingsStore.skuAutoGeneration) {
        generateSku(variant, index);
      } else {
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
      }
    };

    const handleAttributeChange = (variant, index) => {
      updateVariantAttributes(variant);
      if (settingsStore.skuAutoGeneration) {
        generateSku(variant, index);
      }
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

    const hasFiles = () => {
      if (form.value.cover_image instanceof File) return true;
      return form.value.variants.some((v) => v.image instanceof File);
    };

    const handleSubmit = async () => {
      saving.value = true;

      try {
        const hasImages = hasFiles();
        
        if (hasImages) {
          const formData = new FormData();
          
          formData.append('name', form.value.name);
          formData.append('description', form.value.description || '');
          formData.append('category_id', form.value.category_id);
          if (form.value.supplier_id) formData.append('supplier_id', form.value.supplier_id);
          if (form.value.cost_price) formData.append('cost_price', form.value.cost_price);
          formData.append('sell_price', form.value.sell_price);
          if (form.value.promotional_price) formData.append('promotional_price', form.value.promotional_price);
          if (form.value.promotional_expires_at) formData.append('promotional_expires_at', form.value.promotional_expires_at);
          
          if (form.value.cover_image instanceof File) {
            formData.append('cover_image', form.value.cover_image);
          }

          if (form.value.variants.length > 0) {
            form.value.variants.forEach((variant, index) => {
              formData.append(`variants[${index}][sku]`, variant.sku);
              if (variant.barcode) formData.append(`variants[${index}][barcode]`, variant.barcode);
              if (variant.price) formData.append(`variants[${index}][price]`, variant.price);
              if (variant.image instanceof File) {
                formData.append(`variants[${index}][image]`, variant.image);
              } else if (variant.image) {
                formData.append(`variants[${index}][image]`, variant.image);
              }
              formData.append(`variants[${index}][attributes]`, JSON.stringify(variant.attributes));
              if (variant.id) formData.append(`variants[${index}][id]`, variant.id);
            });
          }

          if (!isEdit.value && initialStock.value.length > 0 && form.value.variants.length > 0) {
            form.value.variants.forEach((variant, index) => {
              formData.append(`initial_stock[${index}][quantity]`, initialStock.value[index] || 0);
            });
          }

          if (isEdit.value) {
            await productStore.update(route.params.id, formData);
            toast.success('Produto atualizado com sucesso!');
          } else {
            await productStore.create(formData);
            toast.success('Produto criado com sucesso!');
          }
        } else {
          const payload = { ...form.value };
          delete payload.cover_image;
          
          if (form.value.variants.length > 0) {
            payload.variants = form.value.variants.map((v) => ({
              ...v,
              image: v.image instanceof File ? null : v.image,
            }));

            if (!isEdit.value && initialStock.value.length > 0) {
              payload.initial_stock = form.value.variants.map((variant, index) => ({
                quantity: initialStock.value[index] || 0,
              }));
            }
          } else {
            payload.variants = [];
          }

          if (isEdit.value) {
            await productStore.update(route.params.id, payload);
            toast.success('Produto atualizado com sucesso!');
          } else {
            await productStore.create(payload);
            toast.success('Produto criado com sucesso!');
          }
        }

        router.push({ name: 'products.index' });
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar produto';
        toast.error(message);
      } finally {
        saving.value = false;
      }
    };

    watch(
      [() => form.value.category_id, () => form.value.name],
      () => {
        if (settingsStore.skuAutoGeneration) {
          form.value.variants.forEach((variant, index) => {
            generateSku(variant, index);
          });
        }
      }
    );

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
      settingsStore,
      addVariant,
      removeVariant,
      generateSkuIfEmpty,
      generateSku,
      handleAttributeChange,
      updateVariantAttributes,
      getVariantDescription,
      handleSubmit,
      setVariantRef,
    };
  },
};
</script>
