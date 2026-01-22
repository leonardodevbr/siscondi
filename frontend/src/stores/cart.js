import { defineStore } from 'pinia';
import currency from 'currency.js';

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
  }),
  getters: {
    subtotal(state) {
      return state.items.reduce(
        (sum, item) => currency(sum).add(currency(item.unit_price).multiply(item.quantity)).value,
        0,
      );
    },
    totalCount(state) {
      return state.items.reduce((sum, item) => sum + item.quantity, 0);
    },
  },
  actions: {
    addItem(product, quantity = 1, productVariantId = null) {
      const variantId = productVariantId ?? product.variants?.[0]?.id;
      if (!variantId) {
        throw new Error('Produto sem variação válida.');
      }

      const stock = product.stock_quantity ?? product.current_stock ?? 0;
      if (!stock || stock < quantity) {
        throw new Error(`Estoque insuficiente. Disponível: ${stock || 0}`);
      }

      const existingIndex = this.items.findIndex((i) => i.product_variant_id === variantId);

      if (existingIndex !== -1) {
        const existing = this.items[existingIndex];
        const newQuantity = existing.quantity + quantity;

        if (existing.product.stock_quantity < newQuantity) {
          throw new Error(`Estoque insuficiente. Disponível: ${existing.product.stock_quantity}`);
        }

        existing.quantity = newQuantity;
        existing.total = currency(existing.unit_price).multiply(existing.quantity).value;
      } else {
        const unitPrice = parseFloat(product.effective_price ?? product.price ?? product.sell_price ?? 0);
        this.items.push({
          product: {
            id: product.id,
            name: product.name,
            sku: product.sku,
            barcode: product.barcode,
            stock_quantity: stock,
          },
          product_variant_id: variantId,
          quantity,
          unit_price: unitPrice,
          total: currency(unitPrice).multiply(quantity).value,
        });
      }
    },
    removeItem(index) {
      if (index >= 0 && index < this.items.length) {
        this.items.splice(index, 1);
      }
    },
    updateQuantity(index, quantity) {
      if (index >= 0 && index < this.items.length) {
        const item = this.items[index];

        if (quantity <= 0) {
          this.removeItem(index);
          return;
        }

        if (item.product.stock_quantity < quantity) {
          throw new Error(`Estoque insuficiente. Disponível: ${item.product.stock_quantity}`);
        }

        item.quantity = quantity;
        item.total = currency(item.unit_price).multiply(quantity).value;
      }
    },
    clearCart() {
      this.items = [];
    },
  },
});
