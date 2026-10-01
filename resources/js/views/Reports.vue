<script setup>
import ModulePlaceholder from '../components/ModulePlaceholder.vue';
const items = [
  { title: 'Mapeamento', description: 'Agentes e manifestações por comunidade e categoria.' },
  { title: 'Busca ativa', description: 'Cadastros realizados por equipe, período e localidade.' },
  { title: 'Gestão', description: 'Projetos, PNAB, carteiras e indicadores de acompanhamento.' },
];
</script>
<template><ModulePlaceholder title="Relatórios" subtitle="Informações úteis para acompanhamento e tomada de decisão." :items="items" /></template>
