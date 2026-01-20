<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="$emit('close')"
  >
    <div class="bg-white rounded-lg border border-slate-200 w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-800">
          {{ customer ? 'Editar Cliente' : 'Novo Cliente' }}
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
            Nome *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Nome completo"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            CPF/CNPJ *
          </label>
          <input
            v-model="form.cpf_cnpj"
            type="text"
            required
            @input="formatCpfCnpjInput"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="000.000.000-00 ou 00.000.000/0000-00"
            maxlength="18"
          />
          <p class="mt-1 text-xs text-slate-500">Obrigatório para cadastro</p>
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
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Data de Nascimento
          </label>
          <input
            v-model="form.birth_date"
            type="date"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <AddressForm v-model="form.addressData" />

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
            :disabled="!form.name || saving"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed transition-colors"
          >
            <span v-if="saving">Salvando...</span>
            <span v-else>{{ customer ? 'Atualizar' : 'Criar' }} Cliente</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { useCustomerStore } from '@/stores/customer';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import AddressForm from '@/components/Common/AddressForm.vue';

export default {
  name: 'CustomerForm',
  components: {
    XMarkIcon,
    AddressForm,
  },
  props: {
    customer: {
      type: Object,
      default: null,
    },
  },
  emits: ['close', 'saved'],
  setup(props, { emit }) {
    const toast = useToast();
    const customerStore = useCustomerStore();
    const saving = ref(false);

    const form = ref({
      name: '',
      cpf_cnpj: '',
      email: '',
      phone: '',
      birth_date: '',
      addressData: {
        zip_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
      },
    });

    watch(
      () => props.customer,
      (customer) => {
        if (customer) {
          form.value = {
            name: customer.name || '',
            cpf_cnpj: customer.cpf_cnpj || '',
            email: customer.email || '',
            phone: customer.phone || '',
            birth_date: customer.birth_date ? customer.birth_date.split('T')[0] : '',
            addressData: {
              zip_code: customer.zip_code || '',
              street: customer.street || '',
              number: customer.number || '',
              complement: customer.complement || '',
              neighborhood: customer.neighborhood || '',
              city: customer.city || '',
              state: customer.state || '',
            },
          };
        } else {
          form.value = {
            name: '',
            cpf_cnpj: '',
            email: '',
            phone: '',
            birth_date: '',
            addressData: {
              zip_code: '',
              street: '',
              number: '',
              complement: '',
              neighborhood: '',
              city: '',
              state: '',
            },
          };
        }
      },
      { immediate: true }
    );

    const formatCpfCnpjInput = (event) => {
      let value = event.target.value.replace(/\D/g, '');
      if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
      } else {
        value = value.substring(0, 14);
        value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
      }
      form.value.cpf_cnpj = value;
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
      if (!form.value.name) {
        toast.error('O nome é obrigatório');
        return;
      }

      if (!form.value.cpf_cnpj) {
        toast.error('O CPF/CNPJ é obrigatório');
        return;
      }

      saving.value = true;

      try {
        const { addressData, ...rest } = form.value;
        const payload = {
          ...rest,
          cpf_cnpj: form.value.cpf_cnpj.replace(/\D/g, ''),
          phone: form.value.phone.replace(/\D/g, ''),
          zip_code: addressData?.zip_code || '',
          street: addressData?.street || '',
          number: addressData?.number || '',
          complement: addressData?.complement || '',
          neighborhood: addressData?.neighborhood || '',
          city: addressData?.city || '',
          state: addressData?.state || '',
        };

        if (props.customer) {
          await customerStore.update(props.customer.id, payload);
          toast.success('Cliente atualizado com sucesso!');
        } else {
          await customerStore.create(payload);
          toast.success('Cliente criado com sucesso!');
        }

        emit('saved');
      } catch (error) {
        const message = error.response?.data?.message || 'Erro ao salvar cliente';
        toast.error(message);
      } finally {
        saving.value = false;
      }
    };

    return {
      form,
      saving,
      formatCpfCnpjInput,
      formatPhoneInput,
      handleSubmit,
    };
  },
};
</script>
