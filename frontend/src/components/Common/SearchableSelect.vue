<template>
  <div class="relative">
    <label
      v-if="label"
      class="block text-sm font-medium text-slate-700 mb-1"
    >
      {{ label }}
    </label>

    <Combobox v-model="internalValue" nullable>
      <div class="relative mt-1">
        <div
          class="relative w-full cursor-default overflow-hidden rounded-lg border border-slate-300 bg-white text-left shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500 sm:text-sm"
        >
          <ComboboxInput
            class="w-full border-none py-2 pl-3 pr-10 text-sm text-slate-900 focus:ring-0 placeholder-slate-400"
            :display-value="displayValue"
            :placeholder="placeholder"
            @change="handleInputChange"
          />
          <ComboboxButton
            class="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400"
          >
            <ChevronUpDownIcon class="h-4 w-4" aria-hidden="true" />
          </ComboboxButton>
        </div>

        <ComboboxOptions
          v-if="open && filteredOptions.length > 0"
          class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg border border-slate-200 focus:outline-none"
        >
          <ComboboxOption
            v-for="option in filteredOptions"
            :key="option.id"
            :value="option.id"
            v-slot="{ active, selected }"
          >
            <li
              class="relative cursor-pointer select-none py-2 pl-3 pr-3"
              :class="active ? 'bg-blue-50 text-blue-900' : 'text-slate-900'"
            >
              <span
                class="block truncate"
                :class="selected ? 'font-semibold' : 'font-normal'"
              >
                {{ option.name }}
              </span>
            </li>
          </ComboboxOption>
        </ComboboxOptions>

        <div
          v-else-if="open && filteredOptions.length === 0"
          class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-2 px-3 text-sm shadow-lg border border-slate-200 text-slate-500"
        >
          Nada encontrado.
        </div>
      </div>
    </Combobox>
  </div>
</template>

<script>
import {
  Combobox,
  ComboboxInput,
  ComboboxButton,
  ComboboxOptions,
  ComboboxOption,
} from '@headlessui/vue';
import { ChevronUpDownIcon } from '@heroicons/vue/24/outline';
import { defineComponent, ref, computed, watch } from 'vue';

export default defineComponent({
  name: 'SearchableSelect',
  components: {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
    ChevronUpDownIcon,
  },
  props: {
    modelValue: {
      type: [String, Number, null],
      default: null,
    },
    options: {
      type: Array,
      default: () => [],
    },
    label: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: 'Selecione uma opção',
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const query = ref('');
    const internalValue = ref(props.modelValue);
    const open = ref(false);

    const filteredOptions = computed(() => {
      if (!query.value) {
        return props.options;
      }
      const q = query.value.toLowerCase();
      return props.options.filter((option) =>
        option.name.toLowerCase().includes(q),
      );
    });

    const displayValue = (id) => {
      const option = props.options.find((opt) => opt.id === id);
      return option ? option.name : '';
    };

    const handleInputChange = (event) => {
      const value = event.target.value;
      query.value = value;
      open.value = true;

      if (value === '') {
        internalValue.value = null;
        emit('update:modelValue', null);
      }
    };

    watch(
      () => props.modelValue,
      (newVal) => {
        internalValue.value = newVal;
      },
    );

    watch(internalValue, (val) => {
      emit('update:modelValue', val);
    });

    return {
      query,
      internalValue,
      open,
      filteredOptions,
      displayValue,
      handleInputChange,
    };
  },
});
</script>

