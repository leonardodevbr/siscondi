import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AuthLayout from '../layouts/AuthLayout.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';
import Login from '../views/Auth/Login.vue';
import Dashboard from '../views/Dashboard.vue';
import Agents from '../views/Agents.vue';
import Mapping from '../views/Mapping.vue';
import FieldSearch from '../views/FieldSearch.vue';
import Cards from '../views/Cards.vue';
import Pnab from '../views/Pnab.vue';
import Projects from '../views/Projects.vue';
import Reports from '../views/Reports.vue';

const routes = [
  {
    path: '/login',
    component: AuthLayout,
    children: [{ path: '', component: Login }],
    meta: { guest: true },
  },
  {
    path: '/',
    component: DefaultLayout,
    children: [
      { path: '', component: Dashboard },
      { path: 'agentes', component: Agents },
      { path: 'mapeamento', component: Mapping },
      { path: 'busca-ativa', component: FieldSearch },
      { path: 'carteiras', component: Cards },
      { path: 'pnab', component: Pnab },
      { path: 'projetos', component: Projects },
      { path: 'relatorios', component: Reports },
    ],
  },
];

const router = createRouter({ history: createWebHistory(), routes });
router.beforeEach((to) => {
  const auth = useAuthStore();
  if (!to.meta.guest && !auth.isAuthenticated) return '/login';
  if (to.meta.guest && auth.isAuthenticated) return '/';
});
export default router;
