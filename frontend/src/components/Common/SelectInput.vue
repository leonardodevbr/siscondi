<template>
  <div>
    <label v-if="label" class="block text-sm font-medium text-slate-700 mb-1">
      {{ label }}
    </label>
    <Multiselect
      :model-value="modelValue"
      :options="options"
      :mode="mode"
      :searchable="searchable"
      :placeholder="placeholder"
      :disabled="disabled"
      :close-on-select="closeOnSelect"
      :can-clear="true"
      @update:model-value="handleUpdate"
    />
  </div>
</template>

<script>
import { defineComponent } from 'vue';
import Multiselect from '@vueform/multiselect';

export default defineComponent({
  name: 'SelectInput',
  components: {
    Multiselect,
  },
  props: {
    modelValue: {
      type: [String, Number, Array, Object],
      default: null,
    },
    options: {
      type: Array,
      required: true,
    },
    label: {
      type: String,
      default: '',
    },
    mode: {
      type: String,
      default: 'single',
    },
    searchable: {
      type: Boolean,
      default: true,
    },
    placeholder: {
      type: String,
      default: 'Selecione uma opção',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    closeOnSelect: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const handleUpdate = (value) => {
      emit('update:modelValue', value);
    };

    return {
      handleUpdate,
    };
  },
});
</script>

<style scoped>
:deep(.multiselect-container) {
  @apply relative;
}

:deep(.multiselect-single-label),
:deep(.multiselect-placeholder) {
  @apply text-sm text-slate-900;
}

:deep(.multiselect-container .multiselect-single-label),
:deep(.multiselect-container .multiselect-placeholder) {
  @apply px-3 py-2 border border-slate-300 rounded text-sm;
  min-height: 2.5rem;
  display: flex;
  align-items: center;
}

:deep(.multiselect-container .multiselect-search) {
  @apply px-3 py-2 text-sm border-0 outline-none;
  width: 100%;
}

:deep(.multiselect-container-open .multiselect-search) {
  display: block !important;
  opacity: 1 !important;
}

:deep(.multiselect-container .multiselect-single-label .multiselect-search) {
  position: absolute;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
}

:deep(.multiselect-dropdown) {
  @apply border border-slate-300 rounded-lg bg-white mt-1 z-50;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

:deep(.multiselect-option) {
  @apply px-3 py-2 text-sm text-slate-900 cursor-pointer;
}

:deep(.multiselect-option:hover) {
  @apply bg-blue-50;
}

:deep(.multiselect-option-pointed) {
  @apply bg-blue-50 text-blue-900;
}

:deep(.multiselect-option-selected) {
  @apply bg-blue-600 text-white;
}

:deep(.multiselect-option-selected-pointed) {
  @apply bg-blue-700 text-white;
}

:deep(.multiselect-container-active .multiselect-single-label),
:deep(.multiselect-container-active .multiselect-placeholder) {
  @apply border-blue-500 ring-2 ring-blue-500;
}

:deep(.multiselect-caret) {
  @apply text-slate-400;
}

:deep(.multiselect-clear) {
  @apply text-slate-400 hover:text-slate-600;
}

:deep(.multiselect-tag) {
  @apply bg-blue-600 text-white rounded px-2 py-1 text-xs;
}

:deep(.multiselect-tag-remove) {
  @apply text-white hover:bg-blue-700 rounded;
}
</style>
