import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { API_BASE_URL } from '@/config/runtime'
import { useSesion } from '@/stores/sesion'

/**
 * Cliente del panel (/admin). Reglas de la sesión:
 *
 * - El access token solo vive en memoria (store de sesión), nunca en localStorage.
 * - Antes de cada petición, si el token está por vencer, se renueva primero.
 * - Si aun así llega un 401, se renueva UNA vez y se repite la petición.
 * - Nunca se renueva por temporizador: sin actividad del usuario no hay
 *   peticiones, y así la sesión puede vencer por inactividad como debe.
 */
export const cliente = axios.create({
  baseURL: `${API_BASE_URL}/admin`,
  headers: { Accept: 'application/json' },
})

type ConfigConReintento = InternalAxiosRequestConfig & { _reintentado?: boolean }

cliente.interceptors.request.use(async (config) => {
  const sesion = useSesion()
  const token = await sesion.tokenVigente()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

cliente.interceptors.response.use(
  (respuesta) => respuesta,
  async (error: AxiosError) => {
    const config = error.config as ConfigConReintento | undefined
    if (error.response?.status !== 401 || !config || config._reintentado) {
      throw error
    }

    const sesion = useSesion()
    const token = await sesion.renovar()
    if (!token) {
      sesion.expulsar()
      throw error
    }

    config._reintentado = true
    config.headers.Authorization = `Bearer ${token}`
    return cliente(config)
  },
)

/**
 * Peticiones de /admin/auth: llevan la cookie del refresh token
 * (withCredentials) y no pasan por el interceptor de renovación.
 */
export const clienteAuth = axios.create({
  baseURL: `${API_BASE_URL}/admin/auth`,
  headers: { Accept: 'application/json' },
  withCredentials: true,
})

/** Mensaje en español que devuelve el broker, o uno genérico. */
export function mensajeDeError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (!error.response) return 'No hay conexión con el broker. Revisa tu red o vuelve a intentarlo.'
    const mensaje = (error.response.data as { mensaje?: string } | undefined)?.mensaje
    if (mensaje) return mensaje
  }
  return 'Ocurrió un error inesperado. Vuelve a intentarlo.'
}
