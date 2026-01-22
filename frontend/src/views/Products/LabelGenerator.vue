<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Gerador de Etiquetas</h2>
        <p class="text-xs text-slate-500">
          Selecione as variantes de produtos e gere etiquetas de código de barras
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <div class="card p-6">
          <h3 class="text-md font-semibold text-slate-800 mb-4">Seleção de Produtos</h3>

          <!-- Barra de Filtros -->
          <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
              <input
                v-model="filters.search"
                type="text"
                placeholder="Buscar por nome, SKU ou código de barras"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="handleSearchInput"
              />
            </div>
            <div class="sm:w-64">
              <SearchableSelect
                v-model="filters.category_id"
                :options="categoryOptions"
                placeholder="Todas as categorias"
                @update:modelValue="handleCategoryChange"
              />
            </div>
          </div>

          <div v-if="loading" class="text-center py-8">
            <p class="text-slate-500">Carregando produtos...</p>
          </div>

          <div v-else-if="filteredProducts.length === 0" class="text-center py-8">
            <p class="text-slate-500">Nenhum produto encontrado</p>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="border border-slate-200 rounded-lg p-4"
            >
              <div class="font-semibold text-slate-800 mb-3">{{ product.name }}</div>
              
              <div v-if="product.variants && product.variants.length > 0" class="space-y-2">
                <div
                  v-for="variant in product.variants"
                  :key="variant.id"
                  class="flex items-center justify-between p-2 bg-slate-50 rounded"
                >
                  <div class="flex-1">
                    <div class="text-sm font-medium text-slate-700">
                      {{ formatVariantDescription(variant) }}
                    </div>
                    <div class="text-xs text-slate-500">
                      SKU: {{ variant.sku }} | 
                      {{ variant.barcode ? `Código: ${variant.barcode}` : 'Sem código de barras' }}
                    </div>
                    <div class="text-xs font-semibold text-green-600 mt-1">
                      R$ {{ formatPrice(variant.effective_price || variant.price || product.sell_price) }}
                    </div>
                  </div>
                  
                  <div class="flex items-center gap-2 ml-4">
                    <label class="text-sm text-slate-600">Qtd:</label>
                    <input
                      :value="quantities[variant.id] || 0"
                      @input="updateQuantity(variant.id, $event.target.value)"
                      type="number"
                      min="0"
                      max="1000"
                      class="w-20 px-2 py-1 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>
              
              <div v-else class="text-sm text-slate-500 italic">
                Nenhuma variante disponível
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-1">
        <div class="card sticky top-4 p-6">
          <h3 class="text-md font-semibold text-slate-800 mb-4">Carrinho de Impressão</h3>

          <div v-if="cartItems.length === 0" class="text-sm text-slate-500 py-4">
            Nenhuma etiqueta selecionada
          </div>

          <div v-else class="space-y-3 mb-4">
            <div
              v-for="item in cartItems"
              :key="item.variant_id"
              class="text-sm border-b border-slate-200 pb-2"
            >
              <div class="font-medium text-slate-700">{{ item.product_name }}</div>
              <div class="text-xs text-slate-500">{{ item.variant_description }}</div>
              <div class="text-xs text-slate-600 mt-1">Quantidade: {{ item.quantity }}</div>
            </div>
          </div>

          <div class="border-t border-slate-200 pt-4">
            <div class="flex justify-between items-center mb-4">
              <span class="text-sm font-semibold text-slate-700">Total de Etiquetas:</span>
              <span class="text-lg font-bold text-blue-600">{{ totalLabels }}</span>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-slate-700 mb-2">
                Layout de Impressão
              </label>
              <select
                v-model="selectedLayout"
                class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="thermal">Térmica (40mm x 40mm)</option>
                <option value="a4">Folha (Pimaco 6180 / Carta)</option>
              </select>
            </div>

            <button
              @click="generateLabels"
              :disabled="cartItems.length === 0 || generating"
              class="w-full bg-blue-600 text-white py-2 px-4 rounded font-medium hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="generating">Gerando...</span>
              <span v-else>Gerar Etiquetas</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useCategoryStore } from '@/stores/category';
import SearchableSelect from '@/components/Common/SearchableSelect.vue';
import api from '@/services/api';

export default {
  name: 'LabelGenerator',
  components: {
    SearchableSelect,
  },
  setup() {
    const route = useRoute();
    const toast = useToast();
    const categoryStore = useCategoryStore();

    const products = ref([]);
    const loading = ref(true);
    const quantities = ref({});
    const selectedLayout = ref('thermal');
    const generating = ref(false);
    const filters = ref({
      search: '',
      category_id: null,
    });

    let searchTimeout = null;

    const categoryOptions = computed(() => {
      const categories = categoryStore.items || [];
      return [
        { id: null, name: 'Todas as categorias' },
        ...categories.map((cat) => ({
          id: cat.id,
          name: cat.name,
        })),
      ];
    });

    const filteredProducts = computed(() => {
      let result = products.value;

      // Filtro por busca (nome, SKU, código de barras)
      if (filters.value.search) {
        const searchLower = filters.value.search.toLowerCase();
        result = result.filter((product) => {
          // Busca no nome do produto
          if (product.name?.toLowerCase().includes(searchLower)) {
            return true;
          }

          // Busca no SKU ou código de barras das variações
          if (product.variants) {
            return product.variants.some(
              (variant) =>
                variant.sku?.toLowerCase().includes(searchLower) ||
                variant.barcode?.toLowerCase().includes(searchLower)
            );
          }

          return false;
        });
      }

      // Filtro por categoria
      if (filters.value.category_id) {
        result = result.filter(
          (product) => product.category_id === filters.value.category_id
        );
      }

      return result;
    });

    const cartItems = computed(() => {
      const items = [];

      for (const product of products.value) {
        if (!product.variants) continue;

        for (const variant of product.variants) {
          const quantity = quantities.value[variant.id] || 0;

          if (quantity > 0) {
            items.push({
              variant_id: variant.id,
              quantity: quantity,
              product_name: product.name,
              variant_description: formatVariantDescription(variant),
            });
          }
        }
      }

      return items;
    });

    const totalLabels = computed(() => {
      return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
    });

    const handleSearchInput = () => {
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }
      // Debounce de 300ms para não fazer muitas requisições
      searchTimeout = setTimeout(() => {
        // A filtragem é feita via computed, não precisa fazer nada aqui
      }, 300);
    };

    const handleCategoryChange = () => {
      // A filtragem é feita via computed
    };

    const updateQuantity = (variantId, value) => {
      quantities.value[variantId] = parseInt(value) || 0;
    };

    const loadProducts = async () => {
      try {
        loading.value = true;
        const response = await api.get('/products', {
          params: {
            per_page: 100,
          },
        });

        products.value = response.data.data || response.data || [];
        initializeQuantities();
      } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        toast.error('Erro ao carregar produtos');
      } finally {
        loading.value = false;
      }
    };

    const initializeQuantities = () => {
      quantities.value = {};
      for (const product of products.value) {
        if (product.variants) {
          for (const variant of product.variants) {
            quantities.value[variant.id] = 0;
          }
        }
      }
    };

    const formatVariantDescription = (variant) => {
      if (!variant.attributes || Object.keys(variant.attributes).length === 0) {
        return 'Padrão';
      }

      const parts = [];
      for (const [key, value] of Object.entries(variant.attributes)) {
        if (key === 'tipo' && value === 'único') {
          continue;
        }
        parts.push(`${capitalize(key)}: ${value}`);
      }

      return parts.length > 0 ? parts.join(' / ') : 'Padrão';
    };

    const capitalize = (str) => {
      return str.charAt(0).toUpperCase() + str.slice(1);
    };

    const formatPrice = (price) => {
      if (!price) return '0,00';
      return parseFloat(price).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    };

    const generateLabels = async () => {
      if (cartItems.value.length === 0) {
        return;
      }

      try {
        generating.value = true;

        const payload = {
          items: cartItems.value.map((item) => ({
            variant_id: item.variant_id,
            quantity: item.quantity,
          })),
          layout: selectedLayout.value,
        };

        const response = await api.post('/labels/generate', payload, {
          responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'etiquetas.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

        toast.success('Etiquetas geradas com sucesso!');
      } catch (error) {
        console.error('Erro ao gerar etiquetas:', error);
        toast.error('Erro ao gerar etiquetas');
      } finally {
        generating.value = false;
      }
    };

    onMounted(async () => {
      // Carrega categorias
      await categoryStore.fetchAll();

      // Verifica se há parâmetros na URL
      if (route.query.search) {
        filters.value.search = route.query.search;
      }
      if (route.query.category_id) {
        filters.value.category_id = parseInt(route.query.category_id);
      }

      // Carrega produtos
      await loadProducts();
    });

    return {
      products,
      loading,
      quantities,
      selectedLayout,
      generating,
      filters,
      categoryOptions,
      filteredProducts,
      cartItems,
      totalLabels,
      handleSearchInput,
      handleCategoryChange,
      updateQuantity,
      formatVariantDescription,
      formatPrice,
      generateLabels,
    };
  },
  methods: {
    async loadProducts() {
      try {
        this.loading = true;
        const response = await api.get('/products', {
          params: {
            per_page: 100,
          },
        });
        
        this.products = response.data.data || response.data || [];
        this.initializeQuantities();
      } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        this.$toast?.error('Erro ao carregar produtos');
      } finally {
        this.loading = false;
      }
    },
    initializeQuantities() {
      this.quantities = {};
      for (const product of this.products) {
        if (product.variants) {
          for (const variant of product.variants) {
            this.quantities[variant.id] = 0;
          }
        }
      }
    },
    updateCart() {
      // Força reatividade
      this.$forceUpdate();
    },
    formatVariantDescription(variant) {
      if (!variant.attributes || Object.keys(variant.attributes).length === 0) {
        return 'Padrão';
      }
      
      const parts = [];
      for (const [key, value] of Object.entries(variant.attributes)) {
        parts.push(`${this.capitalize(key)}: ${value}`);
      }
      
      return parts.join(' / ');
    },
    capitalize(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },
    formatPrice(price) {
      if (!price) return '0,00';
      return parseFloat(price).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    async generateLabels() {
      if (this.cartItems.length === 0) {
        return;
      }

      try {
        this.generating = true;
        
        const payload = {
          items: this.cartItems.map(item => ({
            variant_id: item.variant_id,
            quantity: item.quantity,
          })),
          layout: this.selectedLayout,
        };

        const response = await api.post('/labels/generate', payload, {
          responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'etiquetas.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

        this.$toast?.success('Etiquetas geradas com sucesso!');
      } catch (error) {
        console.error('Erro ao gerar etiquetas:', error);
        this.$toast?.error('Erro ao gerar etiquetas');
      } finally {
        this.generating = false;
      }
    },
  },
};
</script>
