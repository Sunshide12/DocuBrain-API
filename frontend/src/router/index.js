/**
 * router/index.js
 *
 * Manual routes for ./src/pages/*.vue
 */

// Composables
import { createRouter, createWebHistory } from 'vue-router'
import Auth from '@/pages/Auth.vue'
import Dashboard from '@/pages/Dashboard.vue'
import DocumentChat from '@/pages/DocumentChat.vue'

const routes = [
  { path: '/', name: 'Auth', component: Auth, meta: { requiresGuest: true } },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/chat/:id', name: 'DocumentChat', component: DocumentChat, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')

  if (to.meta.requiresAuth && !token) {
    // Si la ruta requiere auth y no hay token, enviar a login
    next({ name: 'Auth' })
  } else if (to.meta.requiresGuest && token) {
    // Si la ruta es solo para invitados y hay token, enviar a dashboard
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router
