<script setup lang="ts">
import { panel } from '@/api/panel'
import Cargando from '@/components/Cargando.vue'
import EstadoCuenta from '@/components/EstadoCuenta.vue'
import { usarCarga } from '@/components/usarCarga'
import { API_BASE_URL } from '@/config/runtime'
import { guias } from '@/guias'

const estado = usarCarga(() => panel.estadoCorreo())
const urlOpenApi = `${API_BASE_URL}/docs/api.json`
</script>

<template>
  <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-14">
    <h1 class="text-3xl font-semibold sm:text-4xl">Correo</h1>
    <p class="mt-3 max-w-lectura text-lg text-texto-suave">
      Los sistemas le piden al broker que envíe sus correos. Todos salen desde la cuenta institucional, con copia,
      copia oculta, adjuntos y envíos masivos.
    </p>

    <section class="mt-10 rounded-lg border border-linea bg-white px-6 py-6" aria-labelledby="titulo-cuenta">
      <h2 id="titulo-cuenta" class="text-lg font-semibold">Cuenta de envío</h2>
      <div class="mt-4">
        <Cargando :cargando="estado.cargando.value" :error="estado.error.value" @reintentar="estado.recargar">
          <EstadoCuenta v-if="estado.datos.value" :estado="estado.datos.value" detallado />
        </Cargando>
      </div>
    </section>

    <section class="mt-12 grid gap-10 md:grid-cols-2" aria-label="Documentación">
      <div>
        <h2 class="text-xl font-semibold">Referencia de la API</h2>
        <p class="mt-2 text-texto-suave">
          Cada endpoint con sus campos, ejemplos y respuestas. Se genera del código del broker, así que siempre está al día.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <RouterLink :to="{ name: 'correo-referencia' }" class="boton">Ver la referencia</RouterLink>
          <a :href="urlOpenApi" class="boton-secundario" target="_blank" rel="noopener">Contrato OpenAPI</a>
        </div>
        <p class="mt-3 text-sm text-texto-suave">
          El contrato OpenAPI se importa en Postman con <em>Import → URL</em>.
        </p>
      </div>

      <div>
        <h2 class="text-xl font-semibold">Guías</h2>
        <ul class="mt-2 divide-y divide-linea">
          <li v-for="guia in guias" :key="guia.slug" class="py-3">
            <RouterLink :to="{ name: 'guia', params: { slug: guia.slug } }" class="enlace">{{ guia.titulo }}</RouterLink>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>
