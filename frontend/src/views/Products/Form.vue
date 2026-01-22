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

    <form @submit.prevent="handleSubmit" novalidate class="space-y-6">
      <div class="card">
        <div class="border-b border-slate-200 px-6 pt-6 pb-4">
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
              v-if="form.has_variants"
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
              v-if="!isEdit && form.has_variants"
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
        <div v-show="activeTab === 'general'" class="space-y-4 px-6 py-6">
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
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ex: Camiseta Básica"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Categoria *
                </label>
                <SearchableSelect
                  v-model="form.category_id"
                  :options="categoryOptions"
                  placeholder="Selecione uma categoria"
                />
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
              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Descrição do produto"
            />
          </div>

          <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Este produto possui variações (cores, tamanhos)?
                </label>
                <p class="text-xs text-slate-500">
                  Se desativado, o produto terá apenas um SKU e estoque único
                </p>
              </div>
              <Toggle
                v-model="form.has_variants"
                label=""
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Composição / Tecido
            </label>
            <input
              v-model="form.composition"
              type="text"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ex: 100% Algodão, Seda Pura, Poliéster com Elastano"
            />
          </div>

          <!-- Produto Simples (sem variações) -->
          <div v-if="!form.has_variants" class="border border-slate-200 rounded-lg p-4 space-y-4 bg-slate-50">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Informações do Produto Simples</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="block text-sm font-medium text-slate-700">
                    SKU
                    <span v-if="!settingsStore.skuAutoGeneration" class="text-red-500">*</span>
                  </label>
                  <div
                    v-if="settingsStore.skuAutoGeneration"
                    class="flex items-center gap-1 text-xs text-slate-500"
                    title="SKU gerado automaticamente"
                  >
                    <SparklesIcon class="h-4 w-4" />
                    <span>Automático</span>
                  </div>
                </div>
                <div class="relative">
                  <input
                    v-model="form.sku"
                    type="text"
                    :required="!settingsStore.skuAutoGeneration"
                    :disabled="settingsStore.skuAutoGeneration"
                    :class="[
                      'w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500',
                      settingsStore.skuAutoGeneration
                        ? 'border-slate-200 bg-white text-slate-500 cursor-not-allowed'
                        : 'border-slate-300',
                    ]"
                    :placeholder="settingsStore.skuAutoGeneration ? 'Gerado automaticamente ao salvar' : 'Ex: CAM-001'"
                    @keydown.enter.prevent
                  />
                  <LockClosedIcon
                    v-if="settingsStore.skuAutoGeneration"
                    class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Código de Barras (EAN)
                </label>
                <input
                  v-model="form.barcode"
                  type="text"
                  maxlength="13"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="7891234567890"
                  @keydown.enter.prevent
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Cor
                </label>
                <input
                  v-model="form.simple_attributes.cor"
                  type="text"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ex: Preto, Azul, Vermelho"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Tamanho
                </label>
                <input
                  v-model="form.simple_attributes.tamanho"
                  type="text"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Ex: Único, P, M, G"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Estoque em
                  <span v-if="currentBranchName" class="font-normal">{{ currentBranchName }}</span>
                  <span v-else class="font-normal">filial atual</span>
                </label>
                <input
                  v-model.number="form.stock"
                  type="number"
                  min="0"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :placeholder="`Quantidade em estoque${currentBranchName ? ' - ' + currentBranchName : ''}`"
                  @keydown.enter.prevent
                />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Fornecedor
              </label>
              <SearchableSelect
                v-model="form.supplier_id"
                :options="supplierOptions"
                placeholder="Selecione um fornecedor"
              />
            </div>

            <div v-if="form.has_variants && form.variants.length > 0">
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Total em Estoque
              </label>
              <input
                :value="totalStock"
                type="number"
                disabled
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
              />
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
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>
          </div>
        </div>

        <!-- Tab 2: Variações -->
        <div v-show="activeTab === 'variants' && form.has_variants" class="space-y-4 px-6 py-6">
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
                  <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-slate-700">
                      SKU
                      <span v-if="!settingsStore.skuAutoGeneration" class="text-red-500">*</span>
                    </label>
                    <div
                      v-if="settingsStore.skuAutoGeneration"
                      class="flex items-center gap-1 text-xs text-slate-500"
                      title="SKU gerado automaticamente com base nas configurações do sistema"
                    >
                      <SparklesIcon class="h-4 w-4" />
                      <span>Automático</span>
                    </div>
                  </div>
                  <div class="relative">
                    <input
                      v-model="variant.sku"
                      type="text"
                      :required="!settingsStore.skuAutoGeneration"
                      :disabled="settingsStore.skuAutoGeneration"
                      :class="[
                        'w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-9',
                        settingsStore.skuAutoGeneration
                          ? 'border-slate-200 bg-slate-50 text-slate-500 cursor-not-allowed'
                          : 'border-slate-300',
                      ]"
                      :placeholder="settingsStore.skuAutoGeneration ? 'Gerado automaticamente ao salvar' : 'Ex: CAM-AZUL-P'"
                      @blur="generateSkuIfEmpty(variant, index)"
                      @keydown.enter.prevent
                    />
                    <LockClosedIcon
                      v-if="settingsStore.skuAutoGeneration"
                      class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Código de Barras (EAN)
                  </label>
                  <input
                    v-model="variant.barcode"
                    type="text"
                    maxlength="13"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="7891234567890"
                    @keydown.enter.prevent
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
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: P, M, G"
                    @input="handleAttributeChange(variant, index)"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Preço (Opcional - se diferente do base)
                  </label>
                  <input
                    v-model.number="variant.price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Deixe vazio para usar preço base"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">
                    Estoque Atual
                    <span v-if="currentBranchName" class="text-xs font-normal text-slate-500">
                      ({{ currentBranchName }})
                    </span>
                  </label>
                  <input
                    v-model.number="variant.stock"
                    type="number"
                    min="0"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :placeholder="`Quantidade em estoque${currentBranchName ? ' - ' + currentBranchName : ''}`"
                    :title="currentBranchName ? `Estoque na filial ${currentBranchName}` : 'Estoque atual'"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 3: Estoque Inicial (apenas criação) -->
        <div v-if="!isEdit && activeTab === 'stock' && form.has_variants" class="space-y-4 px-6 py-6">
          <p class="text-sm text-slate-600 mb-4">
            Defina a quantidade inicial de cada variação para
            <span v-if="currentBranchName" class="font-semibold">{{ currentBranchName }}</span>
            <span v-else class="font-semibold">a filial atual</span>
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
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
import { useSupplierStore } from '@/stores/supplier';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import ImageUpload from '@/components/Common/ImageUpload.vue';
import Toggle from '@/components/Common/Toggle.vue';
import { SparklesIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import api from '@/services/api';

export default {
  name: 'ProductForm',
  components: {
    SearchableSelect,
    ImageUpload,
    Toggle,
    SparklesIcon,
    LockClosedIcon,
  },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const toast = useToast();
    const productStore = useProductStore();
    const settingsStore = useSettingsStore();
    const supplierStore = useSupplierStore();
    const authStore = useAuthStore();
    const appStore = useAppStore();

    const isEdit = computed(() => !!route.params.id);
    const activeTab = ref('general');
    const saving = ref(false);
    const categories = ref([]);
    const suppliers = computed(() => supplierStore.suppliers || []);
    const initialStock = ref([]);

    const form = ref({
      name: '',
      description: '',
      has_variants: false,
      composition: '',
      category_id: null,
      supplier_id: null,
      cost_price: null,
      sell_price: '',
      promotional_price: null,
      promotional_expires_at: null,
      cover_image: null,
      stock: 0,
      sku: '',
      barcode: '',
      simple_attributes: {
        cor: '',
        tamanho: '',
      },
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

    const supplierOptions = computed(() =>
      (suppliers.value || []).map((supplier) => ({
        id: supplier.id,
        name: supplier.trade_name || supplier.name || `Fornecedor #${supplier.id}`,
      })),
    );

    const categoryOptions = computed(() =>
      categories.value.map((category) => ({
        id: category.id,
        name: category.name,
      })),
    );

    const totalStock = computed(() => {
      return form.value.variants.reduce((sum, variant) => {
        return sum + (Number(variant.stock) || 0);
      }, 0);
    });

    const currentBranchName = computed(() => {
      if (appStore.currentBranch?.name) {
        return appStore.currentBranch.name;
      }
      if (authStore.user?.branch?.name) {
        return authStore.user.branch.name;
      }
      return null;
    });

    const loadProduct = async () => {
      if (!isEdit.value) return;

      try {
        const product = await productStore.fetchOne(route.params.id);

        const categoryId =
          (product.category && product.category.id) ??
          product.category_id ??
          null;

        const supplierId =
          (product.supplier && product.supplier.id) ??
          product.supplier_id ??
          null;

        const loadedVariants = (product.variants || []).map((v) => {
          const attrs = v.attributes || {};

          const stock = v.current_stock !== undefined ? v.current_stock : (
            Array.isArray(v.inventories) && v.inventories.length > 0
              ? v.inventories[0].quantity ?? 0
              : 0
          );

          return {
            id: v.id,
            sku: v.sku || '',
            barcode: v.barcode || '',
            price: v.price || null,
            image: v.image || null,
            attributes: {
              cor: attrs.cor ?? '',
              tamanho: attrs.tamanho ?? '',
              ...attrs,
            },
            stock,
          };
        });

        const hasVariants = product.has_variants ?? (loadedVariants.length > 1);
        
        let simpleSku = '';
        let simpleBarcode = '';
        let simpleStock = 0;
        let simpleAttributes = {
          cor: '',
          tamanho: '',
        };

        if (!hasVariants && loadedVariants.length === 1) {
          const defaultVariant = loadedVariants[0];
          simpleSku = defaultVariant.sku || '';
          simpleBarcode = defaultVariant.barcode || '';
          simpleStock = defaultVariant.stock ?? 0;
          
          // Preenche atributos da variação padrão
          if (defaultVariant.attributes) {
            simpleAttributes = {
              cor: defaultVariant.attributes.cor || '',
              tamanho: defaultVariant.attributes.tamanho || '',
            };
          }
        } else if (!hasVariants) {
          simpleStock = product.current_stock ?? 0;
        } else {
          simpleStock = product.current_stock ?? 0;
        }

        form.value = {
          name: product.name || '',
          description: product.description || '',
          has_variants: hasVariants,
          composition: product.composition || '',
          category_id: categoryId,
          supplier_id: supplierId,
          cost_price: product.cost_price || null,
          sell_price: product.sell_price || '',
          promotional_price: product.promotional_price || null,
          promotional_expires_at: product.promotional_expires_at || null,
          cover_image: product.image || null,
          stock: simpleStock,
          sku: simpleSku,
          barcode: simpleBarcode,
          simple_attributes: simpleAttributes,
          variants: hasVariants ? loadedVariants : [],
        };
      } catch (error) {
        toast.error('Erro ao carregar produto');
        router.push({ name: 'products.index' });
      }
    };

    const addVariant = async () => {
      if (!form.value.has_variants) {
        form.value.has_variants = true;
      }
      
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
          formData.append('has_variants', form.value.has_variants ? '1' : '0');
          formData.append('category_id', form.value.category_id);
          if (form.value.supplier_id) formData.append('supplier_id', form.value.supplier_id);
          if (form.value.cost_price) formData.append('cost_price', form.value.cost_price);
          formData.append('sell_price', form.value.sell_price);
          if (form.value.promotional_price) formData.append('promotional_price', form.value.promotional_price);
          if (form.value.promotional_expires_at) formData.append('promotional_expires_at', form.value.promotional_expires_at);
          
          if (form.value.cover_image instanceof File) {
            formData.append('cover_image', form.value.cover_image);
          }

          if (form.value.composition) {
            formData.append('composition', form.value.composition);
          }

          // Produto simples: envia SKU, barcode, stock e atributos
          if (!form.value.has_variants) {
            if (form.value.sku) formData.append('sku', form.value.sku);
            if (form.value.barcode) formData.append('barcode', form.value.barcode);
            if (form.value.stock !== undefined && form.value.stock !== null) {
              formData.append('stock', form.value.stock);
            }
            // Envia atributos simples para criar a variação padrão
            if (form.value.simple_attributes) {
              formData.append('simple_attributes', JSON.stringify(form.value.simple_attributes));
            }
          } else if (form.value.variants.length === 0 && form.value.stock !== undefined && form.value.stock !== null) {
            formData.append('stock', form.value.stock);
          }

          if (form.value.has_variants && form.value.variants.length > 0) {
            form.value.variants.forEach((variant, index) => {
              formData.append(`variants[${index}][sku]`, variant.sku);
              if (variant.barcode) formData.append(`variants[${index}][barcode]`, variant.barcode);
              if (variant.price) formData.append(`variants[${index}][price]`, variant.price);
              // Só envia a imagem se for um arquivo novo (File), não envia URLs existentes
              if (variant.image instanceof File) {
                formData.append(`variants[${index}][image]`, variant.image);
              }
              formData.append(`variants[${index}][attributes]`, JSON.stringify(variant.attributes));
              if (variant.id) formData.append(`variants[${index}][id]`, variant.id);
              if (variant.stock !== undefined && variant.stock !== null) {
                formData.append(`variants[${index}][stock]`, variant.stock);
              }
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
          
          if (form.value.has_variants && form.value.variants.length > 0) {
            payload.variants = form.value.variants.map((v) => ({
              ...v,
              image: v.image instanceof File ? null : v.image,
              stock: v.stock !== undefined && v.stock !== null ? v.stock : undefined,
            }));

            if (!isEdit.value && initialStock.value.length > 0) {
              payload.initial_stock = form.value.variants.map((variant, index) => ({
                quantity: initialStock.value[index] || 0,
              }));
            }
            delete payload.stock;
            delete payload.sku;
            delete payload.barcode;
          } else {
            payload.variants = [];
            if (payload.stock === undefined || payload.stock === null) {
              payload.stock = 0;
            }
            // Inclui atributos simples para produto sem variações
            if (form.value.simple_attributes) {
              payload.simple_attributes = form.value.simple_attributes;
            }
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

    watch(
      () => form.value.has_variants,
      (newValue) => {
        if (!newValue) {
          // Se desligou variações, limpa a lista e volta para aba geral
          form.value.variants = [];
          initialStock.value = [];
          if (activeTab.value === 'variants' || activeTab.value === 'stock') {
            activeTab.value = 'general';
          }
        } else {
          // Se ligou variações, vai para aba de variações
          if (form.value.variants.length === 0) {
            activeTab.value = 'variants';
          }
        }
      }
    );

    onMounted(async () => {
      // Carrega configurações públicas (SKU generation, etc)
      await settingsStore.fetchPublicConfig();

      await loadCategories();
      await supplierStore.fetchAll();
      await loadProduct();
    });

    return {
      isEdit,
      activeTab,
      saving,
      form,
      categories,
      suppliers,
      categoryOptions,
      supplierOptions,
      initialStock,
      settingsStore,
      currentBranchName,
      totalStock,
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
