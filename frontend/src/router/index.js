import { createRouter, createWebHistory } from 'vue-router';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
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
import SettingsIndex from '@/views/Settings/Index.vue';
import BranchesIndex from '@/views/Branches/Index.vue';

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
        path: 'pos',
        name: 'pos',
        component: PosView,
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
        path: 'customers',
        name: 'customers',
        component: CustomersIndex,
      },
      {
        path: 'suppliers',
        name: 'suppliers',
        component: SuppliersIndex,
      },
      {
        path: 'expenses',
        name: 'expenses',
        component: ExpensesIndex,
      },
      {
        path: 'reports',
        name: 'reports',
        component: ReportsIndex,
      },
      {
        path: 'coupons',
        name: 'coupons',
        component: CouponsIndex,
      },
      {
        path: 'branches',
        name: 'branches.index',
        component: BranchesIndex,
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsIndex,
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

  if (to.meta.requiresAuth && !token) {
    next({ name: 'login' });
    return;
  }

  if (to.meta.guestOnly && token) {
    next({ name: 'dashboard' });
    return;
  }

  next();
});

export default router;

