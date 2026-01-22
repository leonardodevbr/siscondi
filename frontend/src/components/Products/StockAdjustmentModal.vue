<template>
  <Modal :show="show" @close="handleClose" size="md">
    <template #header>
      <h3 class="text-lg font-semibold text-slate-800">Ajustar Estoque</h3>
    </template>

    <template #body>
      <form @submit.prevent="handleSubmit" class="space-y-4">
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

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Quantidade *
          </label>
          <input
            v-model.number="form.quantity"
            type="number"
            min="1"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Digite a quantidade"
          />
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
      </form>
    </template>

    <template #footer>
      <div class="flex justify-end gap-3">
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
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="loading">Salvando...</span>
          <span v-else>Salvar</span>
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
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
})

const emit = defineEmits(['close', 'success'])

const toast = useToast()
const loading = ref(false)

const form = ref({
  type: '',
  quantity: 1,
  reason: '',
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    form.value = {
      type: '',
      quantity: 1,
      reason: '',
    }
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

  if ((form.value.type === 'exit' || form.value.type === 'adjustment') && !form.value.reason.trim()) {
    toast.error('O motivo é obrigatório para saídas e ajustes')
    return
  }

  try {
    loading.value = true

    const response = await api.post('/inventory/adjustment', {
      product_id: props.productId,
      variation_id: props.variationId,
      type: form.value.type,
      quantity: form.value.quantity,
      reason: form.value.reason || null,
    })

    toast.success('Movimentação registrada com sucesso')
    emit('success', response.data.current_stock)
    handleClose()
  } catch (error) {
    const message = error.response?.data?.message || 'Erro ao registrar movimentação'
    toast.error(message)
  } finally {
    loading.value = false
  }
}
</script>
