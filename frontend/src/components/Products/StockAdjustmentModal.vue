<template>
  <Modal :is-open="show" title="Ajustar Estoque" @close="handleClose">
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div v-if="productName" class="rounded-lg border-l-4 border-blue-500 bg-blue-50 p-4">
        <p class="text-xl font-bold text-blue-900">{{ productName }}</p>
        <div class="mt-2 flex items-center gap-2">
          <span class="text-sm text-blue-700">Estoque Atual:</span>
          <span class="text-3xl font-bold text-blue-900">{{ currentStock }}</span>
          <span class="text-sm text-blue-700">un.</span>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Tipo de Movimentação *
        </label>
        <select
          v-model="form.type"
          required
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">Selecione o tipo</option>
          <option value="entry">Entrada</option>
          <option value="exit">Saída/Perda</option>
          <option value="adjustment">Ajuste</option>
          <option value="return">Devolução</option>
        </select>
      </div>

      <div v-if="form.type === 'adjustment'">
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Operação *
        </label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="form.operation"
              type="radio"
              value="add"
              required
              class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500"
            />
            <span class="text-sm text-slate-700">Adicionar Saldo</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="form.operation"
              type="radio"
              value="sub"
              required
              class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500"
            />
            <span class="text-sm text-slate-700">Remover Saldo</span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Quantidade *
        </label>
        <input
          ref="quantityInput"
          v-model.number="form.quantity"
          type="number"
          min="1"
          step="1"
          required
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Digite a quantidade (sempre positiva)"
        />
        <p class="text-xs text-slate-500 mt-1">
          Digite sempre um valor positivo. A operação será aplicada automaticamente.
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Motivo
          <span v-if="form.type === 'exit' || form.type === 'adjustment'" class="text-red-500">*</span>
        </label>
        <textarea
          v-model="form.reason"
          :required="form.type === 'exit' || form.type === 'adjustment'"
          rows="3"
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Descreva o motivo da movimentação"
        ></textarea>
        <p v-if="form.type === 'exit' || form.type === 'adjustment'" class="text-xs text-slate-500 mt-1">
          Obrigatório para saídas e ajustes
        </p>
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
        <button
          type="button"
          @click="handleClose"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          Cancelar
        </button>
        <button
          type="button"
          @click="handleSubmit"
          :disabled="loading"
          class="px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
        >
          <span v-if="loading">Salvando...</span>
          <span v-else>Confirmar Ajuste</span>
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import Modal from '@/components/Common/Modal.vue'
import api from '@/services/api'
import { useToast } from 'vue-toastification'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  productId: {
    type: Number,
    required: true,
  },
  variationId: {
    type: Number,
    default: null,
  },
  currentStock: {
    type: Number,
    default: 0,
  },
  productName: {
    type: String,
    default: '',
  },
  autoFocusQuantity: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'success', 'adjustment-made'])

const toast = useToast()
const loading = ref(false)
const quantityInput = ref(null)

const form = ref({
  type: '',
  operation: 'add',
  quantity: 1,
  reason: '',
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    form.value = {
      type: props.autoFocusQuantity ? 'adjustment' : '',
      operation: 'add',
      quantity: 1,
      reason: '',
    }
    
    if (props.autoFocusQuantity) {
      nextTick(() => {
        quantityInput.value?.focus()
        quantityInput.value?.select()
      })
    }
  }
}, { immediate: true })

watch(() => form.value.type, (newType) => {
  if (newType === 'entry' || newType === 'return') {
    form.value.operation = 'add'
  } else if (newType === 'exit') {
    form.value.operation = 'sub'
  }
})

const handleClose = () => {
  emit('close')
}

const handleSubmit = async () => {
  if (!form.value.type) {
    toast.error('Selecione o tipo de movimentação')
    return
  }

  if (form.value.quantity < 1) {
    toast.error('A quantidade deve ser maior que zero')
    return
  }

  if (form.value.type === 'adjustment' && !form.value.operation) {
    toast.error('Selecione a operação (Adicionar ou Remover)')
    return
  }

  if ((form.value.type === 'exit' || form.value.type === 'adjustment') && !form.value.reason.trim()) {
    toast.error('O motivo é obrigatório para saídas e ajustes')
    return
  }

  try {
    loading.value = true

    const quantity = Math.abs(form.value.quantity)
    const operation = form.value.type === 'adjustment' 
      ? form.value.operation 
      : (form.value.type === 'entry' || form.value.type === 'return' ? 'add' : 'sub')

    const response = await api.post('/inventory/adjustment', {
      product_id: props.productId,
      variation_id: props.variationId,
      type: form.value.type,
      operation: operation,
      quantity: quantity,
      reason: form.value.reason || null,
    })

    toast.success('Movimentação registrada com sucesso')
    emit('success', response.data.current_stock)
    
    // Emit detailed event for QuickAdjustment view
    emit('adjustment-made', {
      ...response.data.movement,
      operation: operation,
      type_label: form.value.type === 'adjustment' ? 'Ajuste' : (form.value.type === 'entry' ? 'Entrada' : (form.value.type === 'exit' ? 'Saída' : 'Devolução')),
      current_stock: response.data.current_stock
    })
    
    handleClose()
  } catch (error) {
    const message = error.response?.data?.message || 'Erro ao registrar movimentação'
    toast.error(message)
  } finally {
    loading.value = false
  }
}
</script>
