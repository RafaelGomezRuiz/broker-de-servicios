<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mensajeDeError } from '@/api/cliente'
import { useSesion } from '@/stores/sesion'

const sesion = useSesion()
const route = useRoute()
const router = useRouter()

const correo = ref('')
const password = ref('')
const enviando = ref(false)
const error = ref<string | null>(null)

const aviso = computed(() =>
  route.query.motivo === 'vencida' ? 'Tu sesión venció. Vuelve a entrar para continuar.' : null,
)

async function entrar() {
  error.value = null
  enviando.value = true
  try {
    await sesion.entrar(correo.value.trim(), password.value)
    const volver = typeof route.query.volver === 'string' && route.query.volver.startsWith('/') ? route.query.volver : '/'
    await router.replace(volver)
  } catch (e) {
    error.value = mensajeDeError(e)
    password.value = ''
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <main class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    <section class="flex flex-col justify-between bg-tinta px-6 py-10 text-white sm:px-12 lg:py-16">
      <p class="flex items-center gap-3 font-semibold">
        <img src="/icono.svg" alt="" class="h-8 w-8 rounded-md ring-1 ring-white/30" />
        Ministerio de Cultura
      </p>
      <div class="mt-16 max-w-md lg:mt-0">
        <h1 class="text-4xl font-semibold leading-tight text-white sm:text-5xl">Broker de Servicios</h1>
        <p class="mt-5 text-lg leading-relaxed text-white/80">
          Los servicios que comparten los sistemas del Ministerio, su documentación y su estado, en un solo lugar.
        </p>
      </div>
      <p class="mt-16 text-sm text-white/60 lg:mt-0">Acceso para el equipo de tecnología.</p>
    </section>

    <section class="flex items-center px-6 py-12 sm:px-12">
      <form class="w-full max-w-sm" novalidate @submit.prevent="entrar">
        <h2 class="text-2xl font-semibold">Entrar</h2>
        <p class="mt-2 text-texto-suave">Usa el correo y la contraseña que te dio el administrador del broker.</p>

        <p
          v-if="aviso && !error"
          class="mt-6 rounded-md border-l-4 border-aviso bg-white px-4 py-3 text-sm"
          role="status"
        >
          {{ aviso }}
        </p>
        <p v-if="error" class="mt-6 rounded-md border-l-4 border-falla bg-white px-4 py-3 text-sm" role="alert">
          {{ error }}
        </p>

        <label class="mt-8 block text-sm font-medium" for="correo">Correo</label>
        <input
          id="correo"
          v-model="correo"
          class="campo mt-1.5"
          type="email"
          autocomplete="username"
          required
          autofocus
        />

        <label class="mt-5 block text-sm font-medium" for="password">Contraseña</label>
        <input
          id="password"
          v-model="password"
          class="campo mt-1.5"
          type="password"
          autocomplete="current-password"
          required
        />

        <button class="boton mt-8 w-full" type="submit" :disabled="enviando || !correo || !password">
          {{ enviando ? 'Entrando…' : 'Entrar' }}
        </button>
      </form>
    </section>
  </main>
</template>
