<script setup lang="ts">
/**
 * Envuelve una carga: muestra el contenido, un error con «Reintentar» o un
 * texto de espera. El error siempre dice qué pasó (mensaje del broker).
 */
defineProps<{ cargando: boolean; error: string | null }>()
defineEmits<{ reintentar: [] }>()
</script>

<template>
  <p v-if="error" class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border-l-4 border-falla bg-white px-4 py-3" role="alert">
    <span>{{ error }}</span>
    <button class="enlace text-sm" type="button" @click="$emit('reintentar')">Reintentar</button>
  </p>
  <p v-else-if="cargando" class="text-texto-suave" role="status">Cargando…</p>
  <slot v-else />
</template>
