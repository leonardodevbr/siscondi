<template>
  <div class="border border-slate-200 rounded-lg">
    <button
      type="button"
      @click="isOpen = !isOpen"
      class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-50 transition-colors"
    >
      <span class="text-sm font-medium text-slate-700">
        Endereço {{ isOpen ? '' : '(Opcional)' }}
      </span>
      <ChevronDownIcon
        :class="['h-5 w-5 text-slate-400 transition-transform duration-200', isOpen ? 'rotate-180' : '']"
      />
    </button>

    <div v-show="isOpen" class="px-4 pb-4 space-y-4 border-t border-slate-200 pt-4">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            CEP
          </label>
          <input
            v-model="addressData.cep"
            type="text"
            @blur="fetchAddress"
            @input="formatCep"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="00000-000"
            maxlength="9"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Logradouro
          </label>
          <input
            v-model="addressData.street"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Rua, Avenida, etc."
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-1">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Número
          </label>
          <input
            ref="numberInput"
            v-model="addressData.number"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="123"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Complemento
          </label>
          <input
            v-model="addressData.complement"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Apto, Bloco, etc."
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Bairro
          </label>
          <input
            v-model="addressData.neighborhood"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Bairro"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Cidade
          </label>
          <input
            v-model="addressData.city"
            type="text"
            class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Cidade"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Estado (UF)
        </label>
        <input
          v-model="addressData.state"
          type="text"
          maxlength="2"
          class="w-full px-3 py-2 border border-slate-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
          placeholder="SP"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { ref, watch, nextTick } from 'vue';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'AddressForm',
  components: {
    ChevronDownIcon,
  },
  props: {
    modelValue: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const isOpen = ref(false);
    const numberInput = ref(null);
    const loading = ref(false);

    const addressData = ref({
      cep: '',
      street: '',
      number: '',
      complement: '',
      neighborhood: '',
      city: '',
      state: '',
    });

    const formatCep = (event) => {
      let value = event.target.value.replace(/\D/g, '');
      value = value.substring(0, 8);
      value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
      addressData.value.cep = value;
    };

    const fetchAddress = async () => {
      const cep = addressData.value.cep.replace(/\D/g, '');
      
      if (cep.length !== 8) {
        return;
      }

      loading.value = true;

      try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();

        if (data.erro) {
          return;
        }

        addressData.value.street = data.logradouro || '';
        addressData.value.neighborhood = data.bairro || '';
        addressData.value.city = data.localidade || '';
        addressData.value.state = data.uf || '';

        await nextTick();
        if (numberInput.value) {
          numberInput.value.focus();
        }
      } catch (error) {
        console.error('Erro ao buscar CEP:', error);
      } finally {
        loading.value = false;
      }
    };

    const formatAddressString = () => {
      const parts = [];

      if (addressData.value.street) {
        parts.push(addressData.value.street);
      }

      if (addressData.value.number) {
        parts.push(`nº ${addressData.value.number}`);
      }

      if (addressData.value.complement) {
        parts.push(addressData.value.complement);
      }

      if (addressData.value.neighborhood) {
        parts.push(`- ${addressData.value.neighborhood}`);
      }

      if (addressData.value.city || addressData.value.state) {
        const cityState = [addressData.value.city, addressData.value.state]
          .filter(Boolean)
          .join('/');
        if (cityState) {
          parts.push(cityState);
        }
      }

      if (addressData.value.cep) {
        parts.push(`CEP: ${addressData.value.cep}`);
      }

      return parts.join(', ');
    };

    watch(
      () => addressData.value,
      () => {
        const formatted = formatAddressString();
        emit('update:modelValue', formatted);
      },
      { deep: true }
    );

    const parseAddressString = (addressString) => {
      if (!addressString) {
        return {
          cep: '',
          street: '',
          number: '',
          complement: '',
          neighborhood: '',
          city: '',
          state: '',
        };
      }

      const cepMatch = addressString.match(/CEP:\s*(\d{5}-?\d{3})/);
      const cep = cepMatch ? cepMatch[1].replace(/\D/g, '').replace(/(\d{5})(\d{3})/, '$1-$2') : '';

      const parts = addressString.split(',').map((p) => p.trim());
      let street = '';
      let number = '';
      let complement = '';
      let neighborhood = '';
      let city = '';
      let state = '';

      for (let i = 0; i < parts.length; i++) {
        const part = parts[i];
        
        if (part.startsWith('CEP:')) continue;
        
        if (part.includes('/')) {
          const cityState = part.split('/').map((p) => p.trim());
          city = cityState[0] || '';
          state = cityState[1] || '';
        } else if (part.startsWith('-')) {
          neighborhood = part.replace(/^-\s*/, '');
        } else if (part.startsWith('nº') || part.match(/^\d+$/)) {
          number = part.replace(/^nº\s*/, '');
        } else if (street === '') {
          street = part;
        } else if (complement === '' && !part.includes('/')) {
          complement = part;
        }
      }

      return {
        cep,
        street,
        number,
        complement,
        neighborhood,
        city,
        state,
      };
    };

    watch(
      () => props.modelValue,
      (newValue) => {
        if (newValue) {
          const parsed = parseAddressString(newValue);
          if (parsed.street || parsed.city || parsed.cep) {
            addressData.value = parsed;
            isOpen.value = true;
          }
        } else if (!isOpen.value) {
          addressData.value = {
            cep: '',
            street: '',
            number: '',
            complement: '',
            neighborhood: '',
            city: '',
            state: '',
          };
        }
      },
      { immediate: true }
    );

    return {
      isOpen,
      addressData,
      numberInput,
      loading,
      formatCep,
      fetchAddress,
    };
  },
};
</script>
