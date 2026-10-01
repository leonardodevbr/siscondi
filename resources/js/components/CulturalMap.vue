<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet.markercluster';
import api from '../services/api';

const el = ref(null);
let map;

onMounted(async () => {
  map = L.map(el.value).setView([-11.69, -41.47], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  const cluster = L.markerClusterGroup();
  try {
    const { data } = await api.get('/map/points');
    for (const point of data.data || []) {
      if (!point.latitude || !point.longitude) continue;
      const marker = L.marker([point.latitude, point.longitude]);
      marker.bindPopup(`<strong>${point.name}</strong><br>${point.type_label || ''}`);
      cluster.addLayer(marker);
    }
  } catch {}
  map.addLayer(cluster);
});

onBeforeUnmount(() => map?.remove());
</script>

<template><div ref="el" class="h-[65vh] min-h-[420px] w-full rounded-2xl"></div></template>
