<script setup>
defineProps({
  /** Tamanho do ícone (logo): h-6 w-6, h-8 w-8, etc. */
  iconClass: {
    type: String,
    default: 'h-8 w-8',
  },
  /** Tamanho do texto do nome: text-sm, text-base, text-lg, etc. */
  textClass: {
    type: String,
    default: 'text-base',
  },
  /** Usar estilo claro (para fundos escuros, ex. sidebar) */
  light: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div class="inline-flex items-center gap-3">
    <img
      src="/logo.png"
      alt="SisConDi"
      :class="iconClass"
      class="shrink-0 object-contain"
    />
    <div class="flex flex-col leading-tight">
      <span
        class="font-bold tracking-tight"
        :class="textClass"
      >
        <span :class="light ? 'text-white' : 'text-[#1e3a5f]'">Sis</span>
        <span :class="light ? 'text-emerald-300' : 'text-emerald-600'">Con</span>
        <span :class="light ? 'text-white' : 'text-[#1e3a5f]'">Di</span>
      </span>
    </div>
  </div>
</template>
