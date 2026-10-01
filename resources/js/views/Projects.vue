<script setup>
import ModulePlaceholder from '../components/ModulePlaceholder.vue';
const items = [
  { title: 'Planejamento', description: 'Objetivo, período, responsáveis, eventos e participantes.' },
  { title: 'Execução', description: 'Orçamento, fornecedores, itens de despesa e andamento.' },
  { title: 'Dossiê', description: 'Contratos, notas, comprovantes e pendências em um único lugar.' },
];
</script>
<template><ModulePlaceholder title="Projetos e execução" subtitle="Organize cada ação do planejamento ao fechamento." :items="items" /></template>
