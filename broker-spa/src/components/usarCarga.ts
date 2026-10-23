import { onMounted, ref, type Ref } from 'vue'
import { mensajeDeError } from '@/api/cliente'

/** Carga datos al montar la pantalla, con estado de carga y error. Sin recargas automáticas. */
export function usarCarga<T>(cargar: () => Promise<T>) {
  const datos = ref<T | null>(null) as Ref<T | null>
  const cargando = ref(true)
  const error = ref<string | null>(null)

  async function ejecutar() {
    cargando.value = true
    error.value = null
    try {
      datos.value = await cargar()
    } catch (e) {
      error.value = mensajeDeError(e)
    } finally {
      cargando.value = false
    }
  }

  onMounted(ejecutar)

  return { datos, cargando, error, recargar: ejecutar }
}
