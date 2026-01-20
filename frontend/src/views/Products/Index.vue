<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Produtos</h2>
        <p class="text-xs text-slate-500">
          Gerencie produtos, variações e estoque
        </p>
      </div>
      <div class="flex gap-2">
        <button
          @click="handleImport"
          class="bg-slate-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-slate-700 transition-colors"
        >
          Importar Excel
        </button>
        <router-link
          :to="{ name: 'products.labels' }"
          class="bg-green-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-green-700 transition-colors"
        >
          Gerar Etiquetas
        </router-link>
        <button
          @click="$router.push({ name: 'products.form' })"
          class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors"
        >
          Novo Produto
        </button>
      </div>
    </div>

    <div class="card">
      <div class="mb-4 flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por nome..."
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @input="debouncedSearch"
          />
        </div>
        <div class="w-full sm:w-48">
          <select
            v-model="filters.category_id"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @change="loadProducts"
          >
            <option value="">Todas as categorias</option>
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

      <div v-if="productStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando produtos...</p>
      </div>

      <div v-else-if="productStore.products.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhum produto encontrado</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Nome
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Categoria
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Preço Base
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Total em Estoque
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Variações
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="product in productStore.products" :key="product.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">{{ product.name }}</div>
                <div v-if="product.description" class="text-xs text-slate-500 truncate max-w-xs">
                  {{ product.description }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ product.category?.name || '-' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">
                  R$ {{ formatPrice(product.sell_price) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ getTotalStock(product) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ product.variants?.length || 0 }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="editProduct(product.id)"
                  class="text-blue-600 hover:text-blue-900 mr-4"
                >
                  Editar
                </button>
                <button
                  @click="deleteProduct(product)"
                  class="text-red-600 hover:text-red-900"
                >
                  Excluir
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="productStore.pagination" class="mt-4 flex items-center justify-between">
        <div class="text-sm text-slate-500">
          Mostrando {{ productStore.pagination.from }} a {{ productStore.pagination.to }} de
          {{ productStore.pagination.total }} resultados
        </div>
        <div class="flex gap-2">
          <button
            v-if="productStore.pagination.current_page > 1"
            @click="changePage(productStore.pagination.current_page - 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Anterior
          </button>
          <button
            v-if="productStore.pagination.current_page < productStore.pagination.last_page"
            @click="changePage(productStore.pagination.current_page + 1)"
            class="px-3 py-1 border border-slate-300 rounded text-sm hover:bg-slate-50"
          >
            Próxima
          </button>
        </div>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      accept=".xlsx,.xls,.csv"
      class="hidden"
      @change="handleFileSelect"
    />
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useProductStore } from '@/stores/product';
import api from '@/services/api';

let searchTimeout = null;

export default {
  name: 'ProductsIndex',
  setup() {
    const router = useRouter();
    const toast = useToast();
    const productStore = useProductStore();
    const fileInput = ref(null);
    const categories = ref([]);
    const filters = ref({
      search: '',
      category_id: '',
    });

    const loadProducts = async () => {
      try {
        const params = {};
        if (filters.value.search) {
          params.search = filters.value.search;
        }
        if (filters.value.category_id) {
          params.category_id = filters.value.category_id;
        }
        await productStore.fetchAll(params);
      } catch (error) {
        toast.error('Erro ao carregar produtos');
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

    const debouncedSearch = () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        loadProducts();
      }, 500);
    };

    const changePage = (page) => {
      loadProducts({ page });
    };

    const editProduct = (id) => {
      router.push({ name: 'products.form', params: { id } });
    };

    const deleteProduct = async (product) => {
      if (!confirm(`Tem certeza que deseja excluir o produto "${product.name}"?`)) {
        return;
      }

      try {
        await productStore.delete(product.id);
        toast.success('Produto excluído com sucesso!');
        loadProducts();
      } catch (error) {
        toast.error('Erro ao excluir produto');
      }
    };

    const handleImport = () => {
      fileInput.value?.click();
    };

    const handleFileSelect = async (event) => {
      const file = event.target.files[0];
      if (!file) return;

      try {
        await productStore.importExcel(file);
        toast.success('Produtos importados com sucesso!');
        loadProducts();
      } catch (error) {
        toast.error('Erro ao importar produtos');
      } finally {
        event.target.value = '';
      }
    };

    const getTotalStock = (product) => {
      if (!product.variants) return 0;
      return product.variants.reduce((total, variant) => {
        if (variant.inventories) {
          return total + variant.inventories.reduce((sum, inv) => sum + (inv.quantity || 0), 0);
        }
        return total;
      }, 0);
    };

    const formatPrice = (price) => {
      if (!price) return '0,00';
      return parseFloat(price).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    };

    onMounted(() => {
      loadProducts();
      loadCategories();
    });

    return {
      productStore,
      categories,
      filters,
      fileInput,
      loadProducts,
      debouncedSearch,
      changePage,
      editProduct,
      deleteProduct,
      handleImport,
      handleFileSelect,
      getTotalStock,
      formatPrice,
    };
  },
};
</script>