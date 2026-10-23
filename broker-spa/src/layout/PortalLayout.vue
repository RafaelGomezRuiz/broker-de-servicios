<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useSesion } from '@/stores/sesion'

const sesion = useSesion()
const route = useRoute()
const menuAbierto = ref(false)
const saliendo = ref(false)

const navegacion = [
  { nombre: 'Inicio', ruta: { name: 'inicio' }, exacta: true },
  { nombre: 'Correo', ruta: { name: 'correo' }, exacta: false },
  { nombre: 'Sistemas integrados', ruta: { name: 'integraciones' }, exacta: false },
  { nombre: 'Guías', ruta: { name: 'guias' }, exacta: false },
]

// En móvil el menú se cierra al navegar.
watch(() => route.fullPath, () => (menuAbierto.value = false))

async function salir() {
  saliendo.value = true
  await sesion.salir()
}
</script>

<template>
  <div class="min-h-screen lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
    <header class="flex items-center justify-between border-b border-linea bg-white px-4 py-3 lg:hidden">
      <RouterLink :to="{ name: 'inicio' }" class="flex items-center gap-2 font-semibold text-tinta">
        <img src="/icono.svg" alt="" class="h-7 w-7" />
        Broker de Servicios
      </RouterLink>
      <button
        class="boton-secundario px-3 py-2"
        type="button"
        :aria-expanded="menuAbierto"
        aria-controls="menu-principal"
        @click="menuAbierto = !menuAbierto"
      >
        {{ menuAbierto ? 'Cerrar' : 'Menú' }}
      </button>
    </header>

    <aside
      id="menu-principal"
      :class="menuAbierto ? 'block' : 'hidden'"
      class="border-b border-linea bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:border-b-0 lg:border-r"
    >
      <RouterLink :to="{ name: 'inicio' }" class="hidden items-center gap-3 px-6 py-6 lg:flex">
        <img src="/icono.svg" alt="" class="h-8 w-8" />
        <span class="font-semibold leading-tight text-tinta">Broker de<br />Servicios</span>
      </RouterLink>

      <nav class="px-3 py-3 lg:py-2" aria-label="Principal">
        <RouterLink
          v-for="item in navegacion"
          :key="item.nombre"
          :to="item.ruta"
          :exact-active-class="item.exacta ? 'bg-fondo text-tinta font-semibold border-tinta' : ''"
          :active-class="item.exacta ? '' : 'bg-fondo text-tinta font-semibold border-tinta'"
          class="block border-l-[3px] border-transparent px-3 py-2.5 text-texto-suave hover:text-tinta"
        >
          {{ item.nombre }}
        </RouterLink>
      </nav>

      <div class="mt-auto border-t border-linea px-6 py-5">
        <p class="truncate text-sm font-medium text-tinta">{{ sesion.usuario?.nombre }}</p>
        <p class="truncate text-sm text-texto-suave">{{ sesion.usuario?.correo }}</p>
        <button class="enlace mt-3 text-sm" type="button" :disabled="saliendo" @click="salir">
          {{ saliendo ? 'Cerrando sesión…' : 'Cerrar sesión' }}
        </button>
      </div>
    </aside>

    <main class="min-w-0">
      <RouterView />
    </main>
  </div>
</template>
