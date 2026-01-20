import { defineStore } from 'pinia';
import currency from 'currency.js';

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
  }),
  getters: {
    totalAmount(state) {
      return state.items.reduce(
        (sum, item) => currency(sum).add(currency(item.price).multiply(item.quantity)).value,
        0,
      );
    },
    totalQuantity(state) {
      return state.items.reduce((sum, item) => sum + item.quantity, 0);
    },
  },
  actions: {
    addItem(product, quantity = 1) {
      const existing = this.items.find((i) => i.id === product.id);

      if (existing) {
        existing.quantity += quantity;
        return;
      }

      this.items.push({
        id: product.id,
        name: product.name,
        price: product.effective_price ?? product.sell_price,
        quantity,
      });
    },
    removeItem(productId) {
      this.items = this.items.filter((item) => item.id !== productId);
    },
    clear() {
      this.items = [];
    },
  },
});

