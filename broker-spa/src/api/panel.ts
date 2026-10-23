import { cliente } from './cliente'

/** Respuestas de /admin (ver broker-api: Admin\PanelController). */

export interface Servicio {
  codigo: string
  nombre: string
  descripcion: string
  estado: 'activo' | 'proximamente'
  documentacion_url: string | null
  openapi_url: string | null
}

export interface SistemaIntegrado {
  codigo: string
  nombre: string
  responsable: string | null
  estado: 'activo' | 'suspendido'
  llaves_activas: number
  ultimo_uso_at: string | null
  correos_30_dias: number
  creado_at: string | null
}

export type EstadoCorreo = 'en_cola' | 'enviando' | 'enviado' | 'fallido'

export interface EstadoCuentaCorreo {
  servidor: string
  usuario: string | null
  remitente: string
  ultima_verificacion: {
    exito: boolean
    etapa: string
    mensaje: string
    origen: 'manual' | 'programada'
    fecha: string
  } | null
  correos_24h: Record<EstadoCorreo, number>
}

export const panel = {
  servicios: () => cliente.get<{ data: Servicio[] }>('/servicios').then((r) => r.data.data),
  sistemas: () => cliente.get<{ data: SistemaIntegrado[] }>('/sistemas').then((r) => r.data.data),
  estadoCorreo: () => cliente.get<EstadoCuentaCorreo>('/correo/estado').then((r) => r.data),
}
