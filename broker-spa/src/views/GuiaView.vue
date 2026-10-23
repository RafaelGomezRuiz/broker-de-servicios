<script setup lang="ts">
import MarkdownIt from 'markdown-it'
import { computed, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { buscarGuia, guias } from '@/guias'

const props = defineProps<{ slug: string }>()
const router = useRouter()

// Las guías son nuestras, pero igual no se permite HTML crudo dentro del Markdown.
const md = new MarkdownIt({ html: false, linkify: true, typographer: true })

const guia = computed(() => buscarGuia(props.slug))
const html = computed(() => (guia.value ? md.render(guia.value.contenido) : ''))
const siguiente = computed(() => {
  const i = guias.findIndex((g) => g.slug === props.slug)
  return i >= 0 ? guias[i + 1] : undefined
})

watchEffect(() => {
  if (!guia.value) router.replace({ name: 'guias' })
  else document.title = `${guia.value.titulo} · Broker de Servicios`
})
</script>

<template>
  <article v-if="guia" class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:py-14">
    <RouterLink :to="{ name: 'guias' }" class="enlace text-sm">Todas las guías</RouterLink>

    <!-- eslint-disable-next-line vue/no-v-html -- Markdown propio, renderizado con html:false -->
    <div
      class="prose prose-slate mt-6 max-w-lectura prose-headings:font-semibold prose-headings:text-tinta prose-a:text-accion prose-code:rounded prose-code:bg-white prose-code:px-1 prose-code:py-0.5 prose-code:font-normal prose-code:before:content-none prose-code:after:content-none prose-pre:bg-tinta prose-pre:text-white prose-th:text-tinta [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-white"
      v-html="html"
    />

    <nav v-if="siguiente" class="mt-14 max-w-lectura border-t border-linea pt-6" aria-label="Siguiente guía">
      <p class="text-sm text-texto-suave">Siguiente</p>
      <RouterLink :to="{ name: 'guia', params: { slug: siguiente.slug } }" class="enlace text-lg">
        {{ siguiente.titulo }}
      </RouterLink>
    </nav>
  </article>
</template>
