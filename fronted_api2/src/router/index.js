import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // La raíz va PRIMERO: si no, "/" cae en el layout público y se ve una página vacía.
    // Sin sesión, el guard de abajo la manda al login; con sesión, al dashboard.
    { path: '/', redirect: { name: 'dashboard' } },

    // ---------- Zona pública (sin drawer ni app-bar) ----------
    {
      path: '/',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guestOnly: true },
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/views/auth/LoginAuth.vue'),
          meta: { title: 'Iniciar sesión' },
        },
        {
          path: 'login-codigo',
          name: 'login-codigo',
          component: () => import('@/views/auth/LoginCodigo.vue'),
          meta: { title: 'Iniciar sesión con código' },
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/views/auth/RegisterAuth.vue'),
          meta: { title: 'Crear cuenta' },
        },
        {
          // Segundo paso del login cuando la cuenta tiene 2FA. Sin "challenge" no tiene sentido.
          path: 'verificar-2fa',
          name: 'verificar-2fa',
          component: () => import('@/views/auth/Verificar2fa.vue'),
          meta: { title: 'Verificación en dos pasos' },
          beforeEnter: () => (useAuthStore().challenge ? true : { name: 'login' }),
        },
      ],
    },

    // ---------- Cambio de clave obligatorio (con sesión, pero sin drawer) ----------
    {
      path: '/cambiar-clave',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'cambiar-clave',
          component: () => import('@/views/auth/CambiarClave.vue'),
          meta: { title: 'Cambiar contraseña' },
        },
      ],
    },

    // ---------- Zona privada (drawer + app-bar) ----------
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { title: 'Inicio' },
        },
        {
          path: 'productos',
          name: 'productos',
          component: () => import('@/views/ProductosView.vue'),
          meta: { title: 'Productos', permiso: 'ver-productos' },
        },
        {
          path: 'reportes',
          name: 'reportes',
          component: () => import('@/views/ReportesView.vue'),
          meta: { title: 'Reportes', permiso: 'ver-productos' },
        },
        {
          path: 'seguridad',
          name: 'seguridad',
          component: () => import('@/views/SeguridadView.vue'),
          meta: { title: 'Seguridad' },
        },
        {
          path: 'usuarios',
          name: 'usuarios',
          component: () => import('@/views/UsuariosView.vue'),
          meta: { title: 'Usuarios', permiso: 'gestionar-usuarios' },
        },
      ],
    },

    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: () => import('@/views/NotFound.vue'),
      meta: { title: 'Página no encontrada' },
    },
  ],
})

// Guard global: un solo lugar para toda la protección
router.beforeEach((to) => {
  const auth = useAuthStore()

  // Ruta protegida sin sesión → login, recordando a dónde iba
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Con sesión no tiene sentido ver login/registro
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.mustChangePassword ? 'cambiar-clave' : 'dashboard' }
  }

  if (auth.isAuthenticated) {
    // Clave temporal: no se usa el sistema hasta cambiarla
    if (auth.mustChangePassword && to.name !== 'cambiar-clave') {
      return { name: 'cambiar-clave' }
    }
    // Ya la cambió: la pantalla de cambio no tiene sentido
    if (!auth.mustChangePassword && to.name === 'cambiar-clave') {
      return { name: 'dashboard' }
    }
    // Ruta que exige un permiso que el usuario no tiene
    if (to.meta.permiso && !auth.can(to.meta.permiso)) {
      return { name: 'dashboard' }
    }
  }
})

// Título de la pestaña según la ruta
router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | Productos` : 'Productos'
})

export default router
