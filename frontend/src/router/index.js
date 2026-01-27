import { createRouter, createWebHistory } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import HomeView from '@/views/Dashboard/Home.vue';
import LoginView from '@/views/Auth/Login.vue';
import PosView from '@/views/POS/Pos.vue';
import ProductsIndex from '@/views/Products/Index.vue';
import ProductForm from '@/views/Products/Form.vue';
import LabelGenerator from '@/views/Products/LabelGenerator.vue';
import SalesIndex from '@/views/Sales/Index.vue';
import CustomersIndex from '@/views/Customers/Index.vue';
import SuppliersIndex from '@/views/Suppliers/Index.vue';
import ExpensesIndex from '@/views/Expenses/Index.vue';
import ReportsIndex from '@/views/Reports/Index.vue';
import CouponsIndex from '@/views/Coupons/Index.vue';
import CouponForm from '@/views/Coupons/Form.vue';
import SettingsIndex from '@/views/Settings/Index.vue';
import BranchesIndex from '@/views/Branches/Index.vue';
import UsersIndex from '@/views/Users/Index.vue';
import UserForm from '@/views/Users/UserForm.vue';
import ProfileIndex from '@/views/Profile/Index.vue';
import MovementsIndex from '@/views/Inventory/MovementsIndex.vue';

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
    path: '/pos',
    component: PosLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'pos',
        component: PosView,
        meta: { title: 'PDV' },
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
        meta: { title: 'Painel' },
      },
      {
        path: 'sales',
        name: 'sales',
        component: SalesIndex,
        meta: { title: 'Vendas' },
      },
      {
        path: 'products',
        name: 'products.index',
        component: ProductsIndex,
        meta: { title: 'Produtos' },
      },
      {
        path: 'products/new',
        name: 'products.form',
        component: ProductForm,
        meta: { title: 'Novo Produto' },
      },
      {
        path: 'products/:id/edit',
        name: 'products.form.edit',
        component: ProductForm,
        meta: { title: 'Editar Produto' },
      },
      {
        path: 'products/labels',
        name: 'products.labels',
        component: LabelGenerator,
        meta: { title: 'Gerar Etiquetas' },
      },
      {
        path: 'inventory/movements',
        name: 'inventory.movements',
        component: MovementsIndex,
        meta: { title: 'Histórico de Movimentações', roles: ['super-admin', 'manager', 'stockist'] },
      },
      {
        path: 'customers',
        name: 'customers',
        component: CustomersIndex,
        meta: { title: 'Clientes' },
      },
      {
        path: 'suppliers',
        name: 'suppliers',
        component: SuppliersIndex,
        meta: { title: 'Fornecedores', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'expenses',
        name: 'expenses',
        component: ExpensesIndex,
        meta: { title: 'Despesas', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'reports',
        name: 'reports',
        component: ReportsIndex,
        meta: { title: 'Relatórios', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons',
        name: 'coupons',
        component: CouponsIndex,
        meta: { title: 'Cupons', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons/create',
        name: 'coupons.create',
        component: CouponForm,
        meta: { title: 'Novo Cupom', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons/:id/edit',
        name: 'coupons.edit',
        component: CouponForm,
        meta: { title: 'Editar Cupom', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'branches',
        name: 'branches.index',
        component: BranchesIndex,
        meta: { title: 'Filiais', roles: ['super-admin'] },
      },
      {
        path: 'users',
        name: 'users',
        component: UsersIndex,
        meta: { title: 'Usuários', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'users/new',
        name: 'users.form',
        component: UserForm,
        meta: { title: 'Novo Usuário', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'users/:id/edit',
        name: 'users.form.edit',
        component: UserForm,
        meta: { title: 'Editar Usuário', roles: ['super-admin', 'manager'] },
      },
      {
        path: 'profile',
        name: 'profile',
        component: ProfileIndex,
        meta: { title: 'Perfil' },
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
  document.title = title ? `${title} | NunPDV` : 'NunPDV';
});

export default router;

