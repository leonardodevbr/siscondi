import { defineStore } from 'pinia';
import currency from 'currency.js';

const PERSIST_KEY = 'pdv_cart';

function persist(state) {
  const payload = {
    items: state.items,
    customer: state.customer,
    discount: state.discount,
    saleStarted: state.saleStarted,
  };
  if (!payload.saleStarted && payload.items.length === 0 && !payload.customer) {
    try {
      window.localStorage.removeItem(PERSIST_KEY);
    } catch {
      // ignore
    }
    return;
  }
  try {
    window.localStorage.setItem(PERSIST_KEY, JSON.stringify(payload));
  } catch {
    // ignore
  }
}

function load() {
  try {
    const raw = window.localStorage.getItem(PERSIST_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    if (!data || typeof data !== 'object') return null;
    return data;
  } catch {
    return null;
  }
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    customer: null,
    discount: 0,
    saleStarted: false,
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
    hydrate() {
      const data = load();
      if (!data) return;
      if (Array.isArray(data.items)) this.items = data.items;
      if (data.customer != null) this.customer = data.customer;
      if (typeof data.discount === 'number') this.discount = data.discount;
      if (data.saleStarted === true) this.saleStarted = true;
    },
    clearPersisted() {
      try {
        window.localStorage.removeItem(PERSIST_KEY);
      } catch {
        // ignore
      }
    },
    setCustomer(customer) {
      this.customer = customer;
    },
    setSaleStarted(value) {
      this.saleStarted = !!value;
    },
    setDiscount(value) {
      this.discount = typeof value === 'number' ? value : 0;
    },
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
        const variant = product.variants?.find((v) => v.id === variantId) ?? product.variants?.[0];
        const variantAttributes = variant?.attributes ?? {};
        this.items.push({
          product: {
            id: product.id,
            name: product.name,
            sku: product.sku,
            barcode: product.barcode,
            stock_quantity: stock,
          },
          product_variant_id: variantId,
          variant_attributes: variantAttributes,
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
      this.customer = null;
      this.discount = 0;
      this.saleStarted = false;
    },
    clearForCancel() {
      this.clearCart();
      this.clearPersisted();
    },
    clearForFinalize() {
      this.clearCart();
      this.clearPersisted();
    },
  },
});

export function setupCartPersist(store) {
  store.$subscribe(() => {
    persist(store.$state);
  });
}
