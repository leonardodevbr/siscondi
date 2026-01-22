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
    toFormData(form) {
      const formData = new FormData();

      const appendValue = (key, value) => {
        if (value === undefined || value === null || value === '') {
          return;
        }

        if (typeof value === 'boolean') {
          formData.append(key, value ? 1 : 0);
          return;
        }

        formData.append(key, value);
      };

      const imageKeys = ['cover_image', 'image'];

      Object.entries(form || {}).forEach(([key, value]) => {
        if (key === 'variants' && Array.isArray(value)) {
          value.forEach((variant, index) => {
            if (!variant || typeof variant !== 'object') return;

            Object.entries(variant).forEach(([vKey, vValue]) => {
              const field = `variants[${index}][${vKey}]`;

              if (vKey === 'image') {
                if (vValue instanceof File) {
                  formData.append(field, vValue);
                }
                return;
              }

              if (vKey === 'attributes') {
                if (vValue && typeof vValue === 'object') {
                  formData.append(field, JSON.stringify(vValue));
                }
                return;
              }

              if (vValue === undefined || vValue === null || vValue === '') {
                return;
              }

              if (typeof vValue === 'boolean') {
                formData.append(field, vValue ? 1 : 0);
                return;
              }

              formData.append(field, vValue);
            });
          });
          return;
        }

        if (key === 'initial_stock' && Array.isArray(value)) {
          value.forEach((stock, index) => {
            if (stock && typeof stock === 'object') {
              Object.entries(stock).forEach(([sKey, sValue]) => {
                const field = `initial_stock[${index}][${sKey}]`;
                appendValue(field, sValue);
              });
            }
          });
          return;
        }

        if (imageKeys.includes(key)) {
          if (value instanceof File) {
            formData.append(key, value);
          }
          return;
        }

        // Trata objetos complexos (como simple_attributes) que precisam ser serializados
        if (key === 'simple_attributes' && value && typeof value === 'object' && !Array.isArray(value)) {
          formData.append(key, JSON.stringify(value));
          return;
        }

        appendValue(key, value);
      });

      return formData;
    },

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
        const payload = productData instanceof FormData ? productData : this.toFormData(productData);

        const response = await api.post('/products', payload, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
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
        const payload = productData instanceof FormData ? productData : this.toFormData(productData);

        if (typeof payload.append === 'function' && !payload.has('_method')) {
          payload.append('_method', 'PUT');
        }

        const response = await api.post(`/products/${id}`, payload, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });
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
