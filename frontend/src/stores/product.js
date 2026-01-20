import { defineStore } from 'pinia';
import api from '@/services/api';

export const useProductStore = defineStore('product', {
  state: () => ({
    products: [],
    currentProduct: null,
    loading: false,
    error: null,
    pagination: null,
  }),
  getters: {
    totalStock: (state) => (productId) => {
      const product = state.products.find((p) => p.id === productId);
      if (!product || !product.variants) return 0;
      
      return product.variants.reduce((total, variant) => {
        if (variant.inventories) {
          return total + variant.inventories.reduce((sum, inv) => sum + (inv.quantity || 0), 0);
        }
        return total;
      }, 0);
    },
  },
  actions: {
    async fetchAll(params = {}) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get('/products', { params });
        this.products = response.data.data || response.data || [];
        this.pagination = response.data.meta || null;
        return this.products;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar produtos';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async fetchOne(id) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.get(`/products/${id}`);
        this.currentProduct = response.data;
        return this.currentProduct;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao carregar produto';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async create(productData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.post('/products', productData);
        const newProduct = response.data;
        this.products.unshift(newProduct);
        return newProduct;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao criar produto';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async update(id, productData) {
      this.loading = true;
      this.error = null;

      try {
        const response = await api.put(`/products/${id}`, productData);
        const updatedProduct = response.data;
        const index = this.products.findIndex((p) => p.id === id);
        if (index !== -1) {
          this.products[index] = updatedProduct;
        }
        if (this.currentProduct?.id === id) {
          this.currentProduct = updatedProduct;
        }
        return updatedProduct;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao atualizar produto';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async delete(id) {
      this.loading = true;
      this.error = null;

      try {
        await api.delete(`/products/${id}`);
        this.products = this.products.filter((p) => p.id !== id);
        if (this.currentProduct?.id === id) {
          this.currentProduct = null;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao excluir produto';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    async importExcel(file) {
      this.loading = true;
      this.error = null;

      try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await api.post('/products/import', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        await this.fetchAll();
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Erro ao importar produtos';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    clearCurrent() {
      this.currentProduct = null;
    },
  },
});
