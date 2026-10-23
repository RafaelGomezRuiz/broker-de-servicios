import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { clienteAuth } from '@/api/cliente'
import { router } from '@/router'

export interface Usuario {
  id: number
  nombre: string
  correo: string
}

interface RespuestaToken {
  access_token: string
  expira_en: number
  usuario: Usuario
}

/** Se renueva el token si le quedan menos de estos segundos. */
const MARGEN_SEGUNDOS = 30

/**
 * Sesión de la SPA.
 *
 * Al abrir la app, `iniciar()` pide un access token con la cookie httpOnly
 * del refresh token ANTES de mostrar cualquier pantalla protegida. Si la
 * cookie venció (inactividad, vida máxima, logout en otra pestaña), se va
 * directo al login: no se muestra una pantalla para echar al usuario después.
 */
export const useSesion = defineStore('sesion', () => {
  const accessToken = ref<string | null>(null)
  const expiraEnMs = ref(0)
  const usuario = ref<Usuario | null>(null)
  const iniciada = ref(false)

  let renovacionEnCurso: Promise<string | null> | null = null
  let inicio: Promise<void> | null = null

  const autenticado = computed(() => accessToken.value !== null)

  function guardar(datos: RespuestaToken) {
    accessToken.value = datos.access_token
    expiraEnMs.value = Date.now() + datos.expira_en * 1000
    usuario.value = datos.usuario
  }

  function limpiar() {
    accessToken.value = null
    expiraEnMs.value = 0
    usuario.value = null
  }

  /** Una sola vez por carga de la app; las llamadas siguientes esperan la misma. */
  function iniciar(): Promise<void> {
    inicio ??= renovar().then(() => {
      iniciada.value = true
    })
    return inicio
  }

  async function entrar(correo: string, password: string) {
    const { data } = await clienteAuth.post<RespuestaToken>('/login', { correo, password })
    guardar(data)
  }

  /**
   * Pide un access token nuevo con la cookie. Si varias peticiones lo
   * necesitan a la vez, todas esperan la MISMA renovación.
   */
  function renovar(): Promise<string | null> {
    renovacionEnCurso ??= clienteAuth
      .post<RespuestaToken>('/refresh')
      .then(({ data }) => {
        guardar(data)
        return data.access_token
      })
      .catch(() => {
        limpiar()
        return null
      })
      .finally(() => {
        renovacionEnCurso = null
      })
    return renovacionEnCurso
  }

  /** El token actual, renovado antes si está por vencer. Null si no hay sesión. */
  async function tokenVigente(): Promise<string | null> {
    if (!accessToken.value) return null
    if (Date.now() < expiraEnMs.value - MARGEN_SEGUNDOS * 1000) return accessToken.value
    return renovar()
  }

  async function salir() {
    try {
      await clienteAuth.post('/logout', null, {
        headers: accessToken.value ? { Authorization: `Bearer ${accessToken.value}` } : {},
      })
    } finally {
      limpiar()
      await router.replace({ name: 'login' })
    }
  }

  /** La sesión ya no vale (p. ej. otro 401 tras renovar): al login, recordando a dónde iba. */
  function expulsar() {
    limpiar()
    const actual = router.currentRoute.value
    if (actual.name !== 'login') {
      router.replace({ name: 'login', query: { volver: actual.fullPath, motivo: 'vencida' } })
    }
  }

  return { usuario, autenticado, iniciada, iniciar, entrar, renovar, tokenVigente, salir, expulsar }
})
