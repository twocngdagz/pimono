import './bootstrap';
import './echo';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import LoginView from './Pages/LoginView.vue';
import Dashboard from './Pages/Dashboard.vue';
import { ensureAuthFetched, isAuthenticated } from './auth';

const routes = [
  { path: '/', name: 'login', component: LoginView },
  { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { requiresAuth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  await ensureAuthFetched();
  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return { name: 'login' };
  }
  if (to.name === 'login' && isAuthenticated.value) {
    return { name: 'dashboard' };
  }
  return true;
});

createApp(App).use(router).mount('#app');
