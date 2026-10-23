import { createRouter, createWebHistory } from 'vue-router'
import { useSesion } from '@/stores/sesion'

declare module 'vue-router' {
  interface RouteMeta {
    publica?: boolean
    titulo?: string
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/entrar',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { publica: true, titulo: 'Entrar' },
    },
    {
      path: '/',
      component: () => import('@/layout/PortalLayout.vue'),
      children: [
        { path: '', name: 'inicio', component: () => import('@/views/InicioView.vue'), meta: { titulo: 'Inicio' } },
        { path: 'correo', name: 'correo', component: () => import('@/views/CorreoView.vue'), meta: { titulo: 'Correo' } },
        {
          path: 'correo/referencia',
          name: 'correo-referencia',
          component: () => import('@/views/ReferenciaView.vue'),
          meta: { titulo: 'Referencia de la API de correo' },
        },
        {
          path: 'integraciones',
          name: 'integraciones',
          component: () => import('@/views/IntegracionesView.vue'),
          meta: { titulo: 'Sistemas integrados' },
        },
        { path: 'guias', name: 'guias', component: () => import('@/views/GuiasView.vue'), meta: { titulo: 'Guías' } },
        {
          path: 'guias/:slug',
          name: 'guia',
          component: () => import('@/views/GuiaView.vue'),
          props: true,
          meta: { titulo: 'Guía' },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'inicio' } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

/**
 * Antes de la primera pantalla se comprueba la sesión (App.vue muestra el
 * splash mientras tanto). Sin sesión, a las rutas protegidas no se entra.
 */
router.beforeEach(async (destino) => {
  const sesion = useSesion()
  await sesion.iniciar()

  if (destino.meta.publica) {
    return destino.name === 'login' && sesion.autenticado ? { name: 'inicio' } : true
  }

  if (!sesion.autenticado) {
    return { name: 'login', query: destino.fullPath !== '/' ? { volver: destino.fullPath } : {} }
  }

  return true
})

router.afterEach((destino) => {
  document.title = destino.meta.titulo ? `${destino.meta.titulo} · Broker de Servicios` : 'Broker de Servicios'
})
