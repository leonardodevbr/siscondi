<script setup>
import ModulePlaceholder from '../components/ModulePlaceholder.vue';
const items = [
  { title: 'Numeração única', description: 'Registro municipal sequencial para cada carteira emitida.' },
  { title: 'QR Code', description: 'Validação rápida da autenticidade sem expor dados privados.' },
  { title: 'Virtual e física', description: 'A mesma identificação poderá ser exibida no celular ou impressa em PVC.' },
];
</script>
<template><ModulePlaceholder title="Carteiras culturais" subtitle="Emissão e validação das identificações culturais." :items="items" /></template>
