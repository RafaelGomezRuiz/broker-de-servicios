<script setup lang="ts">
import type { EstadoCuentaCorreo } from '@/api/panel'
import { fecha, haceCuanto } from './fechas'

defineProps<{ estado: EstadoCuentaCorreo; detallado?: boolean }>()
</script>

<template>
  <div>
    <p v-if="!estado.ultima_verificacion" class="flex items-start gap-3">
      <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-aviso" aria-hidden="true" />
      <span>
        La cuenta de correo todavía no se ha verificado. Ejecuta
        <code class="rounded bg-fondo px-1.5 py-0.5 text-sm">php artisan correo:verificar</code>
        en el broker; después se verifica sola cada hora.
      </span>
    </p>

    <template v-else>
      <p class="flex items-start gap-3">
        <span
          class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
          :class="estado.ultima_verificacion.exito ? 'bg-bien' : 'bg-falla'"
          aria-hidden="true"
        />
        <span>
          <strong class="font-semibold" :class="estado.ultima_verificacion.exito ? 'text-bien' : 'text-falla'">
            {{ estado.ultima_verificacion.exito ? 'La cuenta de correo funciona.' : 'La cuenta de correo tiene un problema.' }}
          </strong>
          {{ estado.ultima_verificacion.exito ? '' : estado.ultima_verificacion.mensaje }}
          <span class="block text-sm text-texto-suave">
            Verificada {{ haceCuanto(estado.ultima_verificacion.fecha) }}
            ({{ estado.ultima_verificacion.origen === 'programada' ? 'automática' : 'manual' }}).
          </span>
        </span>
      </p>
    </template>

    <dl v-if="detallado" class="mt-6 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-3">
      <div>
        <dt class="text-texto-suave">Servidor</dt>
        <dd class="mt-0.5 font-medium">{{ estado.servidor }}</dd>
      </div>
      <div>
        <dt class="text-texto-suave">Cuenta</dt>
        <dd class="mt-0.5 font-medium">{{ estado.usuario ?? 'Sin autenticación' }}</dd>
      </div>
      <div>
        <dt class="text-texto-suave">Remitente de todos los correos</dt>
        <dd class="mt-0.5 font-medium">{{ estado.remitente }}</dd>
      </div>
      <div v-if="estado.ultima_verificacion" class="sm:col-span-3">
        <dt class="text-texto-suave">Última verificación</dt>
        <dd class="mt-0.5">{{ fecha(estado.ultima_verificacion.fecha) }}: {{ estado.ultima_verificacion.mensaje }}</dd>
      </div>
    </dl>
  </div>
</template>
