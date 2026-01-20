<script setup>
const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['close']);
</script>

<template>
  <teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4"
      @click.self="emit('close')"
    >
      <div class="card w-full max-w-lg p-5">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">
            {{ title }}
          </h2>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600"
            @click="emit('close')"
          >
            <span class="sr-only">Fechar</span>
            ✕
          </button>
        </div>

        <div class="text-sm text-slate-700">
          <slot />
        </div>
      </div>
    </div>
  </teleport>
  </template>

