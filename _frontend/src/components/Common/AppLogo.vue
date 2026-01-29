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
    <img
      src="/logo.png"
      alt=""
      class="shrink-0 object-contain"
      :class="iconClass"
    />
    <span class="inline-flex items-baseline gap-0">
      <span
        class="font-semibold tracking-tight"
        :class="[
          textClass,
          light
            ? 'text-white'
            : 'text-[#2C3A51]',
        ]"
      >Nun</span>
      <span
        class="font-normal tracking-tight"
        :class="[
          textClass,
          light
            ? 'text-slate-400'
            : 'text-[#8D939B]',
        ]"
      >PDV</span>
    </span>
  </div>
</template>
