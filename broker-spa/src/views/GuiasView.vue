<script setup lang="ts">
import { guias } from '@/guias'
</script>

<template>
  <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-14">
    <h1 class="text-3xl font-semibold sm:text-4xl">Guías</h1>
    <p class="mt-3 max-w-lectura text-lg text-texto-suave">
      Cómo integrar un sistema con el broker. Para el detalle de cada endpoint, consulta la
      <RouterLink :to="{ name: 'correo-referencia' }" class="enlace">referencia de la API</RouterLink>.
    </p>

    <ul class="mt-10 divide-y divide-linea border-y border-linea">
      <li v-for="guia in guias" :key="guia.slug">
        <RouterLink
          :to="{ name: 'guia', params: { slug: guia.slug } }"
          class="group block py-5 sm:grid sm:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] sm:gap-8"
        >
          <span class="text-lg font-semibold text-tinta group-hover:text-accion">{{ guia.titulo }}</span>
          <span class="mt-1 block text-texto-suave sm:mt-0">{{ guia.resumen }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>
