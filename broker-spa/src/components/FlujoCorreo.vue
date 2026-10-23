<script setup lang="ts">
import type { EstadoCorreo } from '@/api/panel'

/**
 * El recorrido de los correos de las últimas 24 h:
 * en cola → enviando → enviado, con la salida a «fallido».
 */
defineProps<{ conteos: Record<EstadoCorreo, number> }>()

const tramos: { estado: Exclude<EstadoCorreo, 'fallido'>; nombre: string; ayuda: string }[] = [
  { estado: 'en_cola', nombre: 'En cola', ayuda: 'Esperando turno o un reintento' },
  { estado: 'enviando', nombre: 'Enviando', ayuda: 'Entregándose a Office 365' },
  { estado: 'enviado', nombre: 'Enviados', ayuda: 'Office 365 los aceptó' },
]
</script>

<template>
  <figure>
    <ol class="grid gap-px overflow-hidden rounded-lg border border-linea bg-linea sm:grid-cols-[repeat(3,minmax(0,1fr))_minmax(0,0.9fr)]">
      <li v-for="(tramo, i) in tramos" :key="tramo.estado" class="relative bg-white px-5 py-5">
        <p class="text-sm text-texto-suave">{{ tramo.nombre }}</p>
        <p
          class="mt-1 text-4xl font-semibold tabular-nums"
          :class="tramo.estado === 'enviado' ? 'text-bien' : 'text-tinta'"
        >
          {{ conteos[tramo.estado].toLocaleString('es-DO') }}
        </p>
        <p class="mt-2 text-sm text-texto-suave">{{ tramo.ayuda }}</p>
        <!-- Flecha del recorrido entre tramos (solo en pantallas anchas). -->
        <svg
          v-if="i < tramos.length - 1"
          class="absolute -right-3 top-1/2 z-10 hidden h-6 w-6 -translate-y-1/2 sm:block"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="11" fill="#fff" stroke="#D6DCE6" />
          <path d="M10 8l4 4-4 4" fill="none" stroke="#0F2A5C" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </li>
      <li class="bg-white px-5 py-5" :class="conteos.fallido > 0 ? 'bg-falla/5' : ''">
        <p class="text-sm text-texto-suave">Fallidos</p>
        <p class="mt-1 text-4xl font-semibold tabular-nums" :class="conteos.fallido > 0 ? 'text-falla' : 'text-tinta'">
          {{ conteos.fallido.toLocaleString('es-DO') }}
        </p>
        <p class="mt-2 text-sm text-texto-suave">Rechazados o sin más reintentos</p>
      </li>
    </ol>
    <figcaption class="mt-3 text-sm text-texto-suave">
      Correos recibidos en las últimas 24 horas, de todos los sistemas, según su estado actual.
    </figcaption>
  </figure>
</template>
