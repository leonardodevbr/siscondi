<script setup>
defineProps({
  /** Tamanho do ícone (logo): h-6 w-6, h-8 w-8, etc. */
  iconClass: {
    type: String,
    default: 'h-6 w-6',
  },
  /** Tamanho do texto (NunPDV): text-sm, text-base, text-lg, etc. */
  textClass: {
    type: String,
    default: 'text-sm',
  },
  /** Usar estilo claro (para fundos escuros, ex. sidebar/pos header) */
  light: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div class="inline-flex items-center gap-2">
    <svg
      :class="iconClass"
      class="shrink-0"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    <span class="inline-flex items-baseline gap-0">
      <span
        class="font-bold tracking-tight"
        :class="[
          textClass,
          light ? 'text-white' : 'text-blue-600',
        ]"
      >SISCONDI</span>
    </span>
  </div>
</template>
