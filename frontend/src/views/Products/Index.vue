<template>
  <div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Produtos</h2>
        <p class="text-xs text-slate-500">
          Gerencie produtos, variações e estoque
        </p>
      </div>
      
      <!-- Desktop: Botões visíveis -->
      <div class="hidden md:flex gap-2">
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

      <!-- Mobile: Botão Novo + Menu Dropdown -->
      <div class="flex md:hidden items-center justify-between gap-2">
        <button
          @click="$router.push({ name: 'products.form' })"
          class="flex-1 bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
        >
          <PlusIcon class="h-5 w-5" />
          <span class="hidden xs:inline">Novo</span>
        </button>
        
        <Menu as="div" class="relative">
          <MenuButton class="p-2 rounded border border-slate-300 hover:bg-slate-50 transition-colors">
            <EllipsisVerticalIcon class="h-5 w-5 text-slate-600" />
          </MenuButton>
          <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-lg border border-slate-200 shadow-sm z-50">
            <div class="py-1">
              <MenuItem v-slot="{ active }">
                <router-link
                  :to="{ name: 'products.labels' }"
                  :class="[
                    active ? 'bg-slate-100' : '',
                    'flex items-center gap-2 px-4 py-2 text-sm text-slate-700',
                  ]"
                >
                  <QrCodeIcon class="h-5 w-5" />
                  Gerar Etiquetas
                </router-link>
              </MenuItem>
              <MenuItem v-slot="{ active }">
                <button
                  @click="handleImport"
                  :class="[
                    active ? 'bg-slate-100' : '',
                    'w-full flex items-center gap-2 px-4 py-2 text-sm text-slate-700 text-left',
                  ]"
                >
                  <ArrowUpTrayIcon class="h-5 w-5" />
                  Importar Excel
                </button>
              </MenuItem>
            </div>
          </MenuItems>
        </Menu>
      </div>
    </div>

    <div class="card p-4 sm:p-6">
      <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por nome..."
            class="w-full h-10 px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @input="debouncedSearch"
          />
        </div>
        <div>
          <SelectInput
            v-model="filters.category_id"
            :options="categoryOptions"
            mode="single"
            :searchable="true"
            placeholder="Todas as categorias"
            @update:model-value="loadProducts"
          />
        </div>
      </div>

      <div v-if="productStore.loading" class="text-center py-8">
        <p class="text-slate-500">Carregando produtos...</p>
      </div>

      <div v-else-if="productStore.products.length === 0" class="text-center py-8">
        <p class="text-slate-500">Nenhum produto encontrado</p>
      </div>

      <div v-else class="overflow-x-auto -mx-4 sm:-mx-6">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Imagem
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Nome
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Categoria
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Preço Base
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Total em Estoque
              </th>
              <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                Variações
              </th>
              <th class="sticky right-0 bg-slate-50 px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider border-l border-slate-200">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-slate-200">
            <tr v-for="product in productStore.products" :key="product.id">
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <ProductThumb
                  :src="getProductImage(product)"
                  :alt="product.name"
                  size-class="h-10 w-10 sm:h-12 sm:w-12"
                  @click="showImagePreview"
                />
              </td>
              <td class="px-4 sm:px-6 py-4">
                <div class="text-sm font-medium text-slate-900 truncate max-w-xs">
                  {{ product.name }}
                </div>
                <div v-if="product.description" class="text-xs text-slate-500 truncate max-w-xs">
                  {{ product.description }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900 truncate max-w-xs">
                  {{ product.category?.name || '-' }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-slate-900">
                  R$ {{ formatPrice(product.sell_price) }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ getTotalStock(product) }}
                </div>
              </td>
              <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-slate-900">
                  {{ product.variants?.length || 0 }}
                </div>
              </td>
              <td class="sticky right-0 bg-white px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium border-l border-slate-200">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="editProduct(product.id)"
                    class="text-amber-600 hover:text-amber-900 p-1 rounded hover:bg-amber-50 transition-colors"
                    title="Editar"
                  >
                    <PencilSquareIcon class="h-5 w-5" />
                  </button>
                  <button
                    @click="deleteProduct(product)"
                    class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50 transition-colors"
                    title="Excluir"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="productStore.pagination" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useProductStore } from '@/stores/product';
import { PencilSquareIcon, TrashIcon, PlusIcon, EllipsisVerticalIcon, QrCodeIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import { useAlert } from '@/composables/useAlert';
import ProductThumb from '@/components/Common/ProductThumb.vue';
import SelectInput from '@/components/Common/SelectInput.vue';
import api from '@/services/api';

let searchTimeout = null;

export default {
  name: 'ProductsIndex',
  components: {
    PencilSquareIcon,
    TrashIcon,
    PlusIcon,
    EllipsisVerticalIcon,
    QrCodeIcon,
    ArrowUpTrayIcon,
    Menu,
    MenuButton,
    MenuItems,
    MenuItem,
    ProductThumb,
    SelectInput,
  },
  setup() {
    const router = useRouter();
    const toast = useToast();
    const { confirm } = useAlert();
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
      router.push({ name: 'products.form.edit', params: { id } });
    };

    const deleteProduct = async (product) => {
      const confirmed = await confirm(
        'Excluir Produto',
        `Tem certeza que deseja excluir o produto "${product.name}"? Esta ação não pode ser desfeita.`
      );

      if (!confirmed) {
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

    const getProductImage = (product) => {
      if (product.variants && product.variants.length > 0) {
        const variantWithImage = product.variants.find((v) => v.image);
        if (variantWithImage) {
          return variantWithImage.image;
        }
      }
      return null;
    };

    const showImagePreview = (imageUrl) => {
      if (!imageUrl) return;
      const newWindow = window.open('', '_blank');
      if (newWindow) {
        newWindow.document.write(`
          <html>
            <head><title>Preview</title></head>
            <body style="margin:0;display:flex;justify-content:center;align-items:center;height:100vh;background:#f3f4f6;">
              <img src="${imageUrl}" style="max-width:90%;max-height:90%;object-fit:contain;" />
            </body>
          </html>
        `);
      }
    };

    onMounted(() => {
      loadProducts();
      loadCategories();
    });

    const categoryOptions = computed(() => {
      const options = [{ value: '', label: 'Todas as categorias' }];
      categories.value.forEach((category) => {
        options.push({
          value: category.id,
          label: category.name,
        });
      });
      return options;
    });

    return {
      productStore,
      categories,
      categoryOptions,
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
      getProductImage,
      showImagePreview,
    };
  },
};
</script>