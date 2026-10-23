<script setup lang="ts">
import { panel } from '@/api/panel'
import Cargando from '@/components/Cargando.vue'
import EstadoCuenta from '@/components/EstadoCuenta.vue'
import FlujoCorreo from '@/components/FlujoCorreo.vue'
import { usarCarga } from '@/components/usarCarga'
import { useSesion } from '@/stores/sesion'

const sesion = useSesion()
const estado = usarCarga(() => panel.estadoCorreo())
const servicios = usarCarga(() => panel.servicios())

const destinos: Record<string, { name: string }> = {
  correo: { name: 'correo' },
}
</script>

<template>
  <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-14">
    <h1 class="text-3xl font-semibold sm:text-4xl">Hola, {{ sesion.usuario?.nombre?.split(' ')[0] }}</h1>
    <p class="mt-3 max-w-lectura text-lg text-texto-suave">
      Así está el correo que los sistemas del Ministerio envían a través del broker.
    </p>

    <section class="mt-10" aria-labelledby="titulo-correo">
      <h2 id="titulo-correo" class="sr-only">Estado del correo</h2>
      <Cargando :cargando="estado.cargando.value" :error="estado.error.value" @reintentar="estado.recargar">
        <template v-if="estado.datos.value">
          <EstadoCuenta :estado="estado.datos.value" />
          <div class="mt-6">
            <FlujoCorreo :conteos="estado.datos.value.correos_24h" />
          </div>
        </template>
      </Cargando>
    </section>

    <section class="mt-14" aria-labelledby="titulo-servicios">
      <h2 id="titulo-servicios" class="text-xl font-semibold">Servicios</h2>
      <Cargando :cargando="servicios.cargando.value" :error="servicios.error.value" @reintentar="servicios.recargar">
        <ul class="mt-4 divide-y divide-linea border-y border-linea">
          <li
            v-for="servicio in servicios.datos.value ?? []"
            :key="servicio.codigo"
            class="grid gap-3 py-5 sm:grid-cols-[12rem_minmax(0,1fr)_auto] sm:items-center"
          >
            <p class="text-lg font-semibold" :class="servicio.estado === 'activo' ? 'text-tinta' : 'text-texto-suave'">
              {{ servicio.nombre }}
            </p>
            <p class="text-texto-suave">{{ servicio.descripcion }}</p>
            <RouterLink v-if="servicio.estado === 'activo' && destinos[servicio.codigo]" :to="destinos[servicio.codigo]" class="boton-secundario">
              Abrir
            </RouterLink>
            <span v-else class="text-sm text-texto-suave">Próximamente</span>
          </li>
        </ul>
      </Cargando>
    </section>
  </div>
</template>
