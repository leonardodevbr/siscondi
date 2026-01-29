import { createRouter, createWebHistory } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

// Views
import HomeView from '@/views/Dashboard/Home.vue';
import LoginView from '@/views/Auth/Login.vue';
import ChooseDepartmentView from '@/views/Auth/ChooseDepartment.vue';
import LegislationsIndex from '@/views/Legislations/Index.vue';
import LegislationForm from '@/views/Legislations/Form.vue';
import ServantsIndex from '@/views/Servants/Index.vue';
import ServantForm from '@/views/Servants/Form.vue';
import DailyRequestsIndex from '@/views/DailyRequests/Index.vue';
import DailyRequestForm from '@/views/DailyRequests/Form.vue';
import DepartmentsIndex from '@/views/Departments/Index.vue';
import UsersIndex from '@/views/Users/Index.vue';
import UserForm from '@/views/Users/UserForm.vue';
import SettingsIndex from '@/views/Settings/Index.vue';
import ProfileIndex from '@/views/Profile/Index.vue';
import MunicipalityProfile from '@/views/Municipality/Profile.vue';
import MunicipalitiesIndex from '@/views/Municipalities/Index.vue';
import MunicipalitiesEdit from '@/views/Municipalities/Edit.vue';

const routes = [
  {
    path: '/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'login',
        component: LoginView,
        meta: { title: 'Entrar', guestOnly: true },
      },
    ],
    meta: { guestOnly: true },
  },
  {
    path: '/choose-department',
    component: AuthLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'choose-department',
        component: ChooseDepartmentView,
        meta: { title: 'Escolher secretaria' },
      },
    ],
  },
  {
    path: '/',
    component: DefaultLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: HomeView,
        meta: { title: 'Dashboard' },
      },
      // Solicitações de Diárias
      {
        path: 'daily-requests',
        name: 'daily-requests.index',
        component: DailyRequestsIndex,
        meta: { title: 'Solicitações de Diárias' },
      },
      {
        path: 'daily-requests/create',
        name: 'daily-requests.create',
        component: DailyRequestForm,
        meta: { title: 'Nova Solicitação' },
      },
      {
        path: 'daily-requests/:id/edit',
        name: 'daily-requests.edit',
        component: DailyRequestForm,
        meta: { title: 'Editar Solicitação' },
      },
      // Servidores
      {
        path: 'servants',
        name: 'servants.index',
        component: ServantsIndex,
        meta: { title: 'Servidores', roles: ['admin', 'super-admin', 'validator'] },
      },
      {
        path: 'servants/create',
        name: 'servants.create',
        component: ServantForm,
        meta: { title: 'Novo Servidor', roles: ['admin', 'super-admin', 'validator'] },
      },
      {
        path: 'servants/:id/edit',
        name: 'servants.edit',
        component: ServantForm,
        meta: { title: 'Editar Servidor', roles: ['admin', 'validator'] },
      },
      // Legislações (Cargos)
      {
        path: 'legislations',
        name: 'legislations.index',
        component: LegislationsIndex,
        meta: { title: 'Legislações', roles: ['admin', 'super-admin'] },
      },
      {
        path: 'legislations/create',
        name: 'legislations.create',
        component: LegislationForm,
        meta: { title: 'Nova Legislação', roles: ['admin', 'super-admin'] },
      },
      {
        path: 'legislations/:id/edit',
        name: 'legislations.edit',
        component: LegislationForm,
        meta: { title: 'Editar Legislação', roles: ['admin', 'super-admin'] },
      },
      // Secretarias
      {
        path: 'departments',
        name: 'departments.index',
        component: DepartmentsIndex,
        meta: { title: 'Secretarias', roles: ['admin', 'super-admin'] },
      },
      // Usuários
      {
        path: 'users',
        name: 'users.index',
        component: UsersIndex,
        meta: { title: 'Usuários', roles: ['admin', 'super-admin'] },
      },
      {
        path: 'users/new',
        name: 'users.create',
        component: UserForm,
        meta: { title: 'Novo Usuário', roles: ['admin'] },
      },
      {
        path: 'users/:id/edit',
        name: 'users.edit',
        component: UserForm,
        meta: { title: 'Editar Usuário', roles: ['admin', 'super-admin'] },
      },
      // Perfil e Configurações
      {
        path: 'profile',
        name: 'profile',
        component: ProfileIndex,
        meta: { title: 'Perfil' },
      },
      {
        path: 'municipality',
        name: 'municipality.profile',
        component: MunicipalityProfile,
        meta: { title: 'Dados do município', roles: ['admin'] },
      },
      {
        path: 'municipalities',
        name: 'municipalities.index',
        component: MunicipalitiesIndex,
        meta: { title: 'Municípios', roles: ['super-admin'] },
      },
      {
        path: 'municipalities/:id/edit',
        name: 'municipalities.edit',
        component: MunicipalitiesEdit,
        meta: { title: 'Editar município', roles: ['super-admin'] },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsIndex,
        meta: { title: 'Configurações', roles: ['super-admin'] },
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const token = window.localStorage.getItem('token');
  const toast = useToast();

  if (to.meta.requiresAuth && !token) {
    next({ name: 'login' });
    return;
  }

  if (to.meta.guestOnly && token) {
    next({ name: 'dashboard' });
    return;
  }

  if (to.meta.requiresAuth && token && to.name !== 'choose-department') {
    const authStore = useAuthStore();
    const user = authStore.user;
    if (user?.needs_primary_department && !user?.primary_department_id) {
      next({ name: 'choose-department' });
      return;
    }
  }

  if (to.meta.roles && token) {
    const authStore = useAuthStore();
    const user = authStore.user;

    if (!user) {
      next({ name: 'login' });
      return;
    }

    const userRoles = user.roles || [];
    const requiredRoles = to.meta.roles;

    const hasAccess = requiredRoles.some((requiredRole) => {
      if (requiredRole === '*') {
        return true;
      }
      return userRoles.some((userRole) => {
        if (typeof userRole === 'string') {
          return userRole === requiredRole;
        }
        return userRole?.name === requiredRole;
      });
    });

    if (!hasAccess) {
      toast.error('Você não tem permissão para acessar esta página.');
      next({ name: 'dashboard' });
      return;
    }
  }

  next();
});

router.afterEach((to) => {
  const title = to.meta.title;
  const appName = document.querySelector('meta[name="apple-mobile-web-app-title"]')?.getAttribute('content') || '';
  document.title = title ? (appName ? `${title} | ${appName}` : title) : appName || 'Sistema';
});

export default router;

