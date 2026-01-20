<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg border border-slate-200 w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800">
          {{ supplier ? 'Editar Fornecedor' : 'Novo Fornecedor' }}
        </h3>
        <button
          @click="$emit('close')"
          class="text-slate-400 hover:text-slate-600"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Razão Social *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Razão Social"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nome Fantasia
          </label>
          <input
            v-model="form.trade_name"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Nome Fantasia"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            CNPJ *
          </label>
          <input
            v-model="form.cnpj"
            type="text"
            required
            @input="formatCnpjInput"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="00.000.000/0000-00"
            maxlength="18"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Email
            </label>
            <input
              v-model="form.email"
              type="email"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="email@exemplo.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Telefone
            </label>
            <input
              v-model="form.phone"
              type="text"
              @input="formatPhoneInput"
              class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="(00) 00000-0000"
              maxlength="15"
            />
          </div>
        </div>

        <div>
          <label class="flex items-center">
            <input
              v-model="form.active"
              type="checkbox"
              class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="ml-2 text-sm text-slate-700">Ativo</span>
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 transition-colors"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="!form.name || !form.cnpj || saving"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="saving">Salvando...</span>
            <span v-else>{{ supplier ? 'Atualizar' : 'Criar' }} Fornecedor</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { useSupplierStore } from '@/stores/supplier';
import { XMarkIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'SupplierForm',
  components: {
    XMarkIcon,
  },
  props: {
    supplier: {
      type: Object,
      default: null,
    },
  },
  emits: ['close', 'saved'],
  setup(props, { emit }) {
    const toast = useToast();
    const supplierStore = useSupplierStore();
    const saving = ref(false);

    const form = ref({
      name: '',
      trade_name: '',
      cnpj: '',
      email: '',
      phone: '',
      active: true,
    });

    watch(
      () => props.supplier,
      (supplier) => {
        if (supplier) {
          form.value = {
            name: supplier.name || '',
            trade_name: supplier.trade_name || '',
            cnpj: supplier.cnpj || '',
            email: supplier.email || '',
            phone: supplier.phone || '',
            active: supplier.active !== undefined ? supplier.active : true,
          };
        } else {
          form.value = {
            name: '',
            trade_name: '',
            cnpj: '',
            email: '',
            phone: '',
            active: true,
          };
        }
      },
      { immediate: true }
    );

    const formatCnpjInput = (event) => {
      let value = event.target.value.replace(/\D/g, '');
      value = value.substring(0, 14);
      value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
      form.value.cnpj = value;
    };

    const formatPhoneInput = (event) => {
      let value = event.target.value.replace(/\D/g, '');
      if (value.length <= 10) {
        value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
      } else {
        value = value.substring(0, 11);
        value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
      }
      form.value.phone = value;
    };

    const handleSubmit = async () => {
      if (!form.value.name || !form.value.cnpj) {
        toast.error('Razão Social e CNPJ são obrigatórios');
        return;
      }

      saving.value = true;

      try {
        const payload = {
          ...form.value,
          cnpj: form.value.cnpj.replace(/\D/g, ''),
          phone: form.value.phone.replace(/\D/g, ''),
        };

        if (props.supplier) {
          await supplierStore.update(props.supplier.id, payload);
          toast.success('Fornecedor atualizado com sucesso!');
        } else {
          await supplierStore.create(payload);
          toast.success('Fornecedor criado com sucesso!');
        }

        emit('saved');
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar fornecedor';
        toast.error(message);
      } finally {
        saving.value = false;
      }
    };

    return {
      form,
      saving,
      formatCnpjInput,
      formatPhoneInput,
      handleSubmit,
    };
  },
};
</script>
