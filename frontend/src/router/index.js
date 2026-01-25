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
      },
      {
        path: 'sales',
        name: 'sales',
        component: SalesIndex,
      },
      {
        path: 'products',
        name: 'products.index',
        component: ProductsIndex,
      },
      {
        path: 'products/new',
        name: 'products.form',
        component: ProductForm,
      },
      {
        path: 'products/:id/edit',
        name: 'products.form.edit',
        component: ProductForm,
      },
      {
        path: 'products/labels',
        name: 'products.labels',
        component: LabelGenerator,
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
      },
      {
        path: 'suppliers',
        name: 'suppliers',
        component: SuppliersIndex,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'expenses',
        name: 'expenses',
        component: ExpensesIndex,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'reports',
        name: 'reports',
        component: ReportsIndex,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons',
        name: 'coupons',
        component: CouponsIndex,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons/create',
        name: 'coupons.create',
        component: CouponForm,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'coupons/:id/edit',
        name: 'coupons.edit',
        component: CouponForm,
        meta: { roles: ['super-admin', 'manager'] },
      },
      {
        path: 'branches',
        name: 'branches.index',
        component: BranchesIndex,
        meta: { roles: ['super-admin'] },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsIndex,
        meta: { roles: ['super-admin'] },
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

export default router;

