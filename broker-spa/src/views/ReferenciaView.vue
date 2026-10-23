<script setup lang="ts">
import { ApiReference } from '@scalar/api-reference'
import '@scalar/api-reference/style.css'
import { API_BASE_URL } from '@/config/runtime'

/**
 * Referencia de la API de correo, leída del contrato que publica el broker
 * (/docs/api.json). Solo consulta: para probar se usa Postman.
 */
const configuracion = {
  url: `${API_BASE_URL}/docs/api.json`,
  layout: 'modern' as const,
  hideTestRequestButton: true,
  hideClientButton: true,
  hideDarkModeToggle: true,
  forceDarkModeState: 'light' as const,
  showDeveloperTools: 'never' as const,
  documentDownloadType: 'json' as const,
  telemetry: false,
  // Sin el chat de IA de Scalar: el contrato no debe salir a servicios externos.
  agent: { disabled: true },
  // Sin los botones promocionales de Scalar (MCP, generar SDK) en la barra lateral.
  mcp: { disabled: true },
  withDefaultFonts: false,
  customCss: `
    .scalar-app {
      --scalar-font: 'IBM Plex Sans', system-ui, sans-serif;
      --scalar-font-code: 'IBM Plex Mono', ui-monospace, monospace;
      --scalar-color-accent: #2251C5;
      --scalar-color-1: #1B2333;
      --scalar-color-2: #5A6578;
      --scalar-background-1: #FFFFFF;
      --scalar-background-2: #F5F7FA;
      --scalar-border-color: #D6DCE6;
    }
  `,
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <div class="border-b border-linea px-5 py-3 sm:px-8">
      <RouterLink :to="{ name: 'correo' }" class="enlace text-sm">Volver a Correo</RouterLink>
    </div>
    <ApiReference :configuration="configuracion" />
  </div>
</template>
