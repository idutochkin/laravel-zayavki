/*
 * МАРШРУТЫ ФРОНТА. Не путать с routes/api.php в Laravel:
 * там — адреса, по которым сервер отдаёт данные; здесь — адреса экранов в браузере.
 * Переход между экранами не делает запроса за HTML: Vue просто меняет компонент на странице.
 */

import { createRouter, createWebHistory } from 'vue-router'
import { auth } from './auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('./pages/LoginPage.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      name: 'applications',
      component: () => import('./pages/ApplicationsPage.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/applications/:id',
      name: 'application',
      component: () => import('./pages/ApplicationPage.vue'),
      meta: { requiresAuth: true },
      props: true, // :id из адреса придёт в компонент как prop
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

/*
 * Навигационный guard — выполняется перед каждым переходом.
 * Это только удобство для пользователя (не показывать экран, который всё равно не загрузится).
 * Настоящая защита — на сервере: middleware auth:sanctum и политика. Фронту доверять нельзя.
 */
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'applications' }
  }
})

export default router
