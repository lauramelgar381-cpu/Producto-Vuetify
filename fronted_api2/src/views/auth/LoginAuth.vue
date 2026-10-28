<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'
import GoogleButton from '@/components/GoogleButton.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const mensajeError = ref(route.query.expirada ? 'Tu sesión expiró. Inicia sesión de nuevo.' : '')
const verPassword = ref(false)

const credenciales = reactive({ email: '', password: '' })
const errores = reactive({ email: '', password: '' })

const validarFormulario = () => {
  let esValido = true
  errores.email = ''
  errores.password = ''

  if (!credenciales.email.trim()) {
    errores.email = 'El correo electrónico es requerido'
    esValido = false
  } else if (!/\S+@\S+\.\S+/.test(credenciales.email)) {
    errores.email = 'El formato del correo no es válido'
    esValido = false
  }

  if (!credenciales.password) {
    errores.password = 'La contraseña es requerida'
    esValido = false
  } else if (credenciales.password.length < 6) {
    errores.password = 'La contraseña debe tener al menos 6 caracteres'
    esValido = false
  }

  return esValido
}

// Solo aceptamos rutas internas (evita redirecciones a sitios externos)
const destino = () => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard'
}

const loginMutation = useMutation({
  mutationFn: (datos) => auth.login(datos),
  // Con 2FA activo el backend devuelve un challenge: pasamos al paso del código
  onSuccess: (data) => router.push(data.two_factor ? '/verificar-2fa' : destino()),
  onError: (error) => {
    mensajeError.value = error.response
      ? error.response.data?.message || error.response.data?.error || 'Credenciales incorrectas'
      : 'Error de conexión con el servidor'
  },
})

const cargando = computed(() => loginMutation.isPending.value)

const enviarLogin = () => {
  if (!validarFormulario()) return
  mensajeError.value = ''
  loginMutation.mutate({ ...credenciales })
}
</script>

<template>
  <v-card class="pa-6" elevation="4" rounded="lg">
    <div class="text-center mb-6">
      <h2 class="text-h5 font-weight-bold">Iniciar sesión</h2>
      <p class="text-body-2 text-medium-emphasis mt-1">
        Ingresa tus credenciales para acceder al sistema
      </p>
    </div>

    <v-form novalidate @submit.prevent="enviarLogin">
      <v-text-field
        v-model="credenciales.email"
        label="Correo electrónico"
        type="email"
        placeholder="tu@email.com"
        prepend-inner-icon="mdi-email-outline"
        variant="outlined"
        autocomplete="email"
        :error-messages="errores.email"
      />

      <v-text-field
        v-model="credenciales.password"
        label="Contraseña"
        :type="verPassword ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="verPassword ? 'mdi-eye-off' : 'mdi-eye'"
        variant="outlined"
        autocomplete="current-password"
        :error-messages="errores.password"
        @click:append-inner="verPassword = !verPassword"
      />

      <v-btn type="submit" color="primary" size="large" block :loading="cargando">
        Iniciar sesión
      </v-btn>
    </v-form>

    <v-divider class="my-5" />

    <GoogleButton @error="(m) => (mensajeError = m)" />

    <v-alert v-if="mensajeError" type="error" variant="tonal" class="mt-4" closable>
      {{ mensajeError }}
    </v-alert>

    <div class="d-flex flex-column ga-1 mt-4 text-center">
      <v-btn variant="text" size="small" to="/register">¿No tienes cuenta? Regístrate</v-btn>
      <v-btn variant="text" size="small" to="/login-codigo">
        ¿Prefieres un código? Entrar con código por correo
      </v-btn>
    </div>
  </v-card>
</template>