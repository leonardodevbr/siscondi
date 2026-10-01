import { createApp } from 'vue';
import { createPinia } from 'pinia';
import '../css/app.css';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import App from './App.vue';
import router from './router';
import { flushSyncQueue } from './offline/sync';

createApp(App).use(createPinia()).use(router).mount('#app');

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
window.addEventListener('online', () => flushSyncQueue().catch(() => {}));
