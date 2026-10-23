<script setup lang="ts">
import { panel } from '@/api/panel'
import Cargando from '@/components/Cargando.vue'
import { haceCuanto } from '@/components/fechas'
import { usarCarga } from '@/components/usarCarga'

const sistemas = usarCarga(() => panel.sistemas())
</script>

<template>
  <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-14">
    <h1 class="text-3xl font-semibold sm:text-4xl">Sistemas integrados</h1>
    <p class="mt-3 max-w-lectura text-lg text-texto-suave">
      Los sistemas que tienen llave para usar el broker. Cada uno ve solo sus propios correos.
    </p>

    <div class="mt-10">
      <Cargando :cargando="sistemas.cargando.value" :error="sistemas.error.value" @reintentar="sistemas.recargar">
        <div v-if="sistemas.datos.value?.length === 0" class="rounded-lg border border-dashed border-linea bg-white px-6 py-8">
          <p class="font-semibold text-tinta">Todavía no hay sistemas registrados.</p>
          <p class="mt-2 text-texto-suave">
            Registra el primero en el broker con
            <code class="rounded bg-fondo px-1.5 py-0.5 text-sm">php artisan sistemas:crear sgrh --nombre="…"</code>
            y entrégale la llave que muestra.
          </p>
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-linea bg-white">
          <table class="w-full min-w-[42rem] text-left text-sm">
            <caption class="sr-only">Sistemas registrados en el broker</caption>
            <thead class="border-b border-linea text-texto-suave">
              <tr>
                <th scope="col" class="px-5 py-3 font-medium">Sistema</th>
                <th scope="col" class="px-5 py-3 font-medium">Estado</th>
                <th scope="col" class="px-5 py-3 text-right font-medium">Llaves</th>
                <th scope="col" class="px-5 py-3 text-right font-medium">Correos (30 días)</th>
                <th scope="col" class="px-5 py-3 font-medium">Último uso</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-linea">
              <tr v-for="sistema in sistemas.datos.value ?? []" :key="sistema.codigo">
                <td class="px-5 py-4">
                  <p class="font-semibold text-tinta">{{ sistema.nombre }}</p>
                  <p class="text-texto-suave"><code>{{ sistema.codigo }}</code></p>
                  <p v-if="sistema.responsable" class="text-texto-suave">Responsable: {{ sistema.responsable }}</p>
                </td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center gap-2">
                    <span
                      class="h-2 w-2 rounded-full"
                      :class="sistema.estado === 'activo' ? 'bg-bien' : 'bg-falla'"
                      aria-hidden="true"
                    />
                    {{ sistema.estado === 'activo' ? 'Activo' : 'Suspendido' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right tabular-nums">{{ sistema.llaves_activas }}</td>
                <td class="px-5 py-4 text-right tabular-nums">{{ sistema.correos_30_dias.toLocaleString('es-DO') }}</td>
                <td class="px-5 py-4 text-texto-suave">{{ haceCuanto(sistema.ultimo_uso_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </Cargando>
    </div>
  </div>
</template>
