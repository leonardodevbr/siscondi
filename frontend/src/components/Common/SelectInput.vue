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
      :classes="multiselectClasses"
      @update:model-value="$emit('update:modelValue', $event)"
    />
  </div>
</template>

<script>
import Multiselect from '@vueform/multiselect';

export default {
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
  computed: {
    multiselectClasses() {
      return {
        container: 'multiselect-container',
        containerActive: 'multiselect-container-active',
        containerDisabled: 'multiselect-container-disabled',
        containerOpen: 'multiselect-container-open',
        containerOpenTop: 'multiselect-container-open-top',
        containerActiveTop: 'multiselect-container-active-top',
        singleLabel: 'multiselect-single-label',
        multipleLabel: 'multiselect-multiple-label',
        search: 'multiselect-search',
        tags: 'multiselect-tags',
        tagsSearch: 'multiselect-tags-search',
        tag: 'multiselect-tag',
        tagDisabled: 'multiselect-tag-disabled',
        tagRemove: 'multiselect-tag-remove',
        tagRemoveIcon: 'multiselect-tag-remove-icon',
        tagsSearchWrapper: 'multiselect-tags-search-wrapper',
        tagsSearchCount: 'multiselect-tags-search-count',
        placeholder: 'multiselect-placeholder',
        caret: 'multiselect-caret',
        caretOpen: 'multiselect-caret-open',
        clear: 'multiselect-clear',
        clearIcon: 'multiselect-clear-icon',
        spinner: 'multiselect-spinner',
        dropdown: 'multiselect-dropdown',
        dropdownTop: 'multiselect-dropdown-top',
        dropdownHidden: 'multiselect-dropdown-hidden',
        options: 'multiselect-options',
        optionsTop: 'multiselect-options-top',
        group: 'multiselect-group',
        groupLabel: 'multiselect-group-label',
        groupLabelPointable: 'multiselect-group-label-pointable',
        groupLabelPointed: 'multiselect-group-label-pointed',
        groupLabelSelected: 'multiselect-group-label-selected',
        groupLabelDisabled: 'multiselect-group-label-disabled',
        groupOptions: 'multiselect-group-options',
        option: 'multiselect-option',
        optionPointed: 'multiselect-option-pointed',
        optionSelected: 'multiselect-option-selected',
        optionDisabled: 'multiselect-option-disabled',
        optionSelectedPointed: 'multiselect-option-selected-pointed',
        optionSelectedDisabled: 'multiselect-option-selected-disabled',
        noOptions: 'multiselect-no-options',
        noResults: 'multiselect-no-results',
        fakeInput: 'multiselect-fake-input',
        spacer: 'multiselect-spacer',
      };
    },
  },
};
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
  @apply px-3 py-2 border border-slate-300 rounded;
}

:deep(.multiselect-container .multiselect-search) {
  @apply px-3 py-2 text-sm;
}

:deep(.multiselect-dropdown) {
  @apply border border-slate-300 rounded-lg shadow-lg bg-white mt-1 z-50;
}

:deep(.multiselect-option) {
  @apply px-3 py-2 text-sm text-slate-900;
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
