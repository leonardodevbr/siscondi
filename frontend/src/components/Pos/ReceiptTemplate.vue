<template>
  <div class="receipt-container">
    <div class="receipt">
      <div class="header">
        <h1>{{ storeName }}</h1>
        <p v-if="storeDocument">CNPJ: {{ formatDocument(storeDocument) }}</p>
        <p v-if="storeAddress">{{ storeAddress }}</p>
        <p v-if="storePhone">Tel: {{ storePhone }}</p>
      </div>

      <div class="divider"></div>

      <div class="info">
        <p><strong>Venda #{{ sale.id }}</strong></p>
        <p>Data: {{ formatDate(sale.created_at) }}</p>
        <p v-if="sale.customer">Cliente: {{ sale.customer.name }}</p>
        <p v-if="sale.customer?.cpf_cnpj">CPF/CNPJ: {{ formatDocument(sale.customer.cpf_cnpj) }}</p>
        <p>Operador: {{ operatorName }}</p>
      </div>

      <div class="divider"></div>

      <div class="items">
        <table>
          <thead>
            <tr>
              <th class="text-left">Item</th>
              <th class="text-center">Qtd</th>
              <th class="text-right">Valor</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in sale.items" :key="item.id">
              <td class="text-left">{{ item.product_name }}</td>
              <td class="text-center">{{ item.quantity }}</td>
              <td class="text-right">{{ formatCurrency(item.unit_price) }}</td>
              <td class="text-right">{{ formatCurrency(item.total_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="divider"></div>

      <div class="totals">
        <div class="row">
          <span>Subtotal:</span>
          <span>{{ formatCurrency(sale.total_amount) }}</span>
        </div>
        <div v-if="sale.discount_amount > 0" class="row discount">
          <span>Desconto:</span>
          <span>- {{ formatCurrency(sale.discount_amount) }}</span>
        </div>
        <div class="row total">
          <span><strong>TOTAL:</strong></span>
          <span><strong>{{ formatCurrency(sale.final_amount) }}</strong></span>
        </div>
      </div>

      <div class="divider"></div>

      <div class="payments">
        <p><strong>Formas de Pagamento:</strong></p>
        <div v-for="payment in sale.payments" :key="payment.id" class="row">
          <span>{{ formatPaymentMethod(payment.method) }}</span>
          <span>{{ formatCurrency(payment.amount) }}</span>
        </div>
      </div>

      <div class="divider"></div>

      <div class="footer">
        <p>Obrigado pela preferência!</p>
        <p>Volte sempre!</p>
        <p class="barcode">*{{ sale.id }}*</p>
      </div>
    </div>

    <div class="actions no-print">
      <button @click="print" class="btn-print">Imprimir (Ctrl+P)</button>
      <button @click="close" class="btn-close">Fechar</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';

const props = defineProps({
  sale: {
    type: Object,
    required: true,
  },
  storeName: {
    type: String,
    default: 'Adonai PDV',
  },
  storeDocument: {
    type: String,
    default: '',
  },
  storeAddress: {
    type: String,
    default: '',
  },
  storePhone: {
    type: String,
    default: '',
  },
  operatorName: {
    type: String,
    default: 'Operador',
  },
});

const emit = defineEmits(['close']);

function formatCurrency(value) {
  const num = parseFloat(value);
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(num);
}

function formatDate(date) {
  return new Date(date).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDocument(doc) {
  if (!doc) return '';
  const cleaned = doc.replace(/\D/g, '');
  if (cleaned.length === 11) {
    return cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  }
  if (cleaned.length === 14) {
    return cleaned.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  }
  return doc;
}

function formatPaymentMethod(method) {
  const methods = {
    cash: 'Dinheiro',
    money: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    pix: 'PIX',
    point: 'Cartão (Maquininha)',
    store_credit: 'Crédito Loja',
  };
  return methods[method] || method;
}

function print() {
  window.print();
}

function close() {
  emit('close');
  window.close();
}

onMounted(() => {
  // Adiciona listener para Ctrl+P
  const handleKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
      e.preventDefault();
      print();
    }
  };
  
  window.addEventListener('keydown', handleKeydown);
  
  // Cleanup
  return () => {
    window.removeEventListener('keydown', handleKeydown);
  };
});
</script>

<style scoped>
.receipt-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #f3f4f6;
  padding: 2rem;
}

.receipt {
  width: 80mm;
  background: white;
  padding: 1rem;
  font-family: 'Courier New', monospace;
  font-size: 12px;
  line-height: 1.4;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header {
  text-align: center;
  margin-bottom: 1rem;
}

.header h1 {
  font-size: 16px;
  font-weight: bold;
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
}

.header p {
  margin: 0.25rem 0;
  font-size: 11px;
}

.divider {
  border-top: 1px dashed #000;
  margin: 0.75rem 0;
}

.info p {
  margin: 0.25rem 0;
}

.items table {
  width: 100%;
  border-collapse: collapse;
  margin: 0.5rem 0;
}

.items th,
.items td {
  padding: 0.25rem 0;
  font-size: 11px;
}

.items thead th {
  border-bottom: 1px solid #000;
  font-weight: bold;
}

.text-left {
  text-align: left;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.totals .row {
  display: flex;
  justify-content: space-between;
  margin: 0.25rem 0;
}

.totals .row.discount {
  color: #059669;
}

.totals .row.total {
  font-size: 14px;
  margin-top: 0.5rem;
  border-top: 1px solid #000;
  padding-top: 0.5rem;
}

.payments .row {
  display: flex;
  justify-content: space-between;
  margin: 0.25rem 0;
  font-size: 11px;
}

.footer {
  text-align: center;
  margin-top: 1rem;
}

.footer p {
  margin: 0.25rem 0;
}

.footer .barcode {
  font-size: 16px;
  font-weight: bold;
  letter-spacing: 2px;
  margin-top: 0.5rem;
}

.actions {
  margin-top: 2rem;
  display: flex;
  gap: 1rem;
}

.btn-print,
.btn-close {
  padding: 0.75rem 2rem;
  font-size: 14px;
  font-weight: 500;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-print {
  background: #3b82f6;
  color: white;
}

.btn-print:hover {
  background: #2563eb;
}

.btn-close {
  background: #6b7280;
  color: white;
}

.btn-close:hover {
  background: #4b5563;
}

/* Print styles */
@media print {
  .receipt-container {
    background: white;
    padding: 0;
    min-height: auto;
  }

  .receipt {
    width: 80mm;
    box-shadow: none;
    margin: 0;
  }

  .no-print {
    display: none !important;
  }

  @page {
    size: 80mm auto;
    margin: 0;
  }
}
</style>
