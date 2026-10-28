<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const mensajeError = ref('')
const verPassword = ref(false)
const datos = reactive({ name: '', email: '', password: '' })
const errores = reactive({ name: '', email: '', password: '' })

const validar = () => {
  Object.keys(errores).forEach((k) => (errores[k] = ''))
  let ok = true

  if (!datos.name.trim()) {
    errores.name = 'El nombre es requerido'
    ok = false
  }
  if (!datos.email.trim()) {
    errores.email = 'El correo electrónico es requerido'
    ok = false
  } else if (!/\S+@\S+\.\S+/.test(datos.email)) {
    errores.email = 'El formato del correo no es válido'
    ok = false
  }
  if (datos.password.length < 6) {
    errores.password = 'La contraseña debe tener al menos 6 caracteres'
    ok = false
  }
  return ok
}

const registroMutation = useMutation({
  mutationFn: (payload) => auth.register(payload),
  onSuccess: () => router.push('/dashboard'),
  onError: (e) => {
    const erroresApi = e.response?.data?.errors
    if (e.response?.status === 422 && erroresApi) {
      Object.keys(erroresApi).forEach((campo) => {
        if (campo in errores) errores[campo] = erroresApi[campo][0]
      })
      return
    }
    mensajeError.value = e.response
      ? e.response.data?.message || 'No se pudo completar el registro'
      : 'Error de conexión con el servidor'
  },
})

const cargando = computed(() => registroMutation.isPending.value)

const enviar = () => {
  mensajeError.value = ''
  if (!validar()) return
  registroMutation.mutate({ ...datos })
}
</script>

<template>
  <v-card class="pa-6" elevation="4" rounded="lg">
    <div class="text-center mb-6">
      <h2 class="text-h5 font-weight-bold">Crear cuenta</h2>
      <p class="text-body-2 text-medium-emphasis mt-1">Completa los datos para registrarte</p>
    </div>

    <v-form novalidate @submit.prevent="enviar">
      <v-text-field
        v-model="datos.name"
        label="Nombre"
        placeholder="Tu nombre"
        prepend-inner-icon="mdi-account-outline"
        variant="outlined"
        autocomplete="name"
        :error-messages="errores.name"
      />

      <v-text-field
        v-model="datos.email"
        label="Correo electrónico"
        type="email"
        placeholder="tu@email.com"
        prepend-inner-icon="mdi-email-outline"
        variant="outlined"
        autocomplete="email"
        :error-messages="errores.email"
      />

      <v-text-field
        v-model="datos.password"
        label="Contraseña"
        placeholder="Mínimo 6 caracteres"
        :type="verPassword ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="verPassword ? 'mdi-eye-off' : 'mdi-eye'"
        variant="outlined"
        autocomplete="new-password"
        :error-messages="errores.password"
        @click:append-inner="verPassword = !verPassword"
      />

      <v-btn type="submit" color="primary" size="large" block :loading="cargando">
        Registrarme
      </v-btn>
    </v-form>

    <v-alert v-if="mensajeError" type="error" variant="tonal" class="mt-4" closable>
      {{ mensajeError }}
    </v-alert>

    <div class="text-center mt-4">
      <v-btn variant="text" size="small" to="/login">¿Ya tienes cuenta? Inicia sesión</v-btn>
    </div>
  </v-card>
</template>