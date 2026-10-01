<script setup>
import ModulePlaceholder from '../components/ModulePlaceholder.vue';
const items = [
  { title: 'Inscrições', description: 'Projetos e documentos recebidos por edital.' },
  { title: 'Conferência', description: 'Checagem documental antes da avaliação.' },
  { title: 'Avaliação', description: 'Comissão, critérios e lançamento de notas.' },
  { title: 'Resultado e recursos', description: 'Classificação, publicação e análise de recursos.' },
  { title: 'Execução', description: 'Acompanhamento dos projetos contemplados.' },
  { title: 'Prestação de contas', description: 'Entrega, análise e encerramento do projeto.' },
];
</script>
<template><ModulePlaceholder title="PNAB" subtitle="Da inscrição à prestação de contas em um fluxo organizado." :items="items" /></template>
