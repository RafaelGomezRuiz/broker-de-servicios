<script setup lang="ts">
import { useSesion } from '@/stores/sesion'

const sesion = useSesion()
</script>

<template>
  <!-- Mientras se comprueba la sesión no se muestra ninguna pantalla:
       así nadie ve el portal un instante para luego ser enviado al login. -->
  <div v-if="!sesion.iniciada" class="grid min-h-screen place-items-center" role="status">
    <div class="flex items-center gap-3 text-texto-suave">
      <span class="h-2 w-2 animate-pulse rounded-full bg-accion" aria-hidden="true" />
      Comprobando tu sesión…
    </div>
  </div>

  <RouterView v-else />
</template>
