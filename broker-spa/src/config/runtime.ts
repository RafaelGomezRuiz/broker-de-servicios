/**
 * Fuente única de la URL del broker-api, igual que en SGRH-frontend.
 *
 *   1. window.__APP_CONFIG__ → Kubernetes. Lo escribe docker/entrypoint.sh al
 *      arrancar con el ConfigMap: cambiar la URL = editar el ConfigMap y
 *      reiniciar el pod, sin reconstruir la imagen.
 *   2. VITE_API_URL          → desarrollo local, desde .env.
 *   3. Valor por defecto     → `npm run dev` sin .env.
 */

declare global {
  interface Window {
    __APP_CONFIG__?: {
      API_BASE_URL?: string
    }
  }
}

const limpiar = (u: string) => u.replace(/\/+$/, '')

/** Un placeholder sin sustituir (`__ALGO__`) no debe ganarle al .env. */
const usable = (v?: string | null): v is string => !!v && v.trim() !== '' && !v.startsWith('__')

function resolver(runtime: string | undefined, buildTime: string | undefined, porDefecto: string): string {
  if (usable(runtime)) return limpiar(runtime)
  if (usable(buildTime)) return limpiar(buildTime)
  return limpiar(porDefecto)
}

/** URL base del broker-api, sin barra final. Ej.: https://broker-api.minc.gob.do */
export const API_BASE_URL: string = resolver(
  typeof window !== 'undefined' ? window.__APP_CONFIG__?.API_BASE_URL : undefined,
  import.meta.env.VITE_API_URL,
  'http://localhost:8091',
)
