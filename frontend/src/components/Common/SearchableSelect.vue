<template>
  <div :class="label ? 'w-full' : ''">
    <label v-if="label" class="block text-sm font-medium text-slate-700 mb-1">
      {{ label }}
    </label>
    <div
      class="relative"
      :class="filterStyle ? 'filter-select-wrapper' : ''"
    >
      <Multiselect
        v-model="internalValue"
        :options="normalizedOptions"
        mode="single"
        :searchable="true"
        :clear-on-select="false"
        :can-clear="true"
        :close-on-select="true"
        :placeholder="placeholder"
        noOptionsText="Nada encontrado"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Multiselect from '@vueform/multiselect';

const props = defineProps({
  modelValue: {
    type: [String, Number, Object, null],
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
    default: 'Selecione...',
  },
  filterStyle: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const internalValue = ref(props.modelValue);

watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val;
  },
);

const normalizedOptions = computed(() =>
  props.options.map((opt) => {
    if (opt && typeof opt === 'object') {
      if (Object.prototype.hasOwnProperty.call(opt, 'value') || Object.prototype.hasOwnProperty.call(opt, 'label')) {
        return {
          value: opt.value,
          label: opt.label ?? String(opt.value ?? ''),
        };
      }
      if (Object.prototype.hasOwnProperty.call(opt, 'id') || Object.prototype.hasOwnProperty.call(opt, 'name')) {
        return {
          value: opt.id,
          label: opt.name ?? String(opt.id ?? ''),
        };
      }
    }
    return {
      value: opt,
      label: String(opt),
    };
  }),
);

watch(internalValue, (val) => {
  emit('update:modelValue', val);
});
</script>

<style scoped>
.filter-select-wrapper :deep(.multiselect) {
  @apply min-h-10 h-10 border border-slate-300 rounded text-sm;
}
.filter-select-wrapper :deep(.multiselect.is-active) {
  @apply focus:outline-none focus:ring-2 focus:ring-blue-500;
}
</style>

