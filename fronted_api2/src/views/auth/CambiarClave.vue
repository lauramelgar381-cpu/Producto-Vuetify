<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const MIN = 6 // mismo mínimo que el registro; cámbialo aquí y en el backend si quieres más
const mensajeError = ref('')
const ver = reactive({ actual: false, nueva: false, confirmar: false })

const datos = reactive({ current_password: '', password: '', password_confirmation: '' })
const errores = reactive({ current_password: '', password: '', password_confirmation: '' })

const validar = () => {
  Object.keys(errores).forEach((k) => (errores[k] = ''))
  let ok = true

  if (!datos.current_password) {
    errores.current_password = 'Ingresa tu contraseña actual'
    ok = false
  }
  if (datos.password.length < MIN) {
    errores.password = `La nueva contraseña debe tener al menos ${MIN} caracteres`
    ok = false
  } else if (datos.password === datos.current_password) {
    errores.password = 'La nueva contraseña debe ser distinta a la actual'
    ok = false
  }
  if (datos.password_confirmation !== datos.password) {
    errores.password_confirmation = 'Las contraseñas no coinciden'
    ok = false
  }
  return ok
}

const cambiar = useMutation({
  mutationFn: (payload) => auth.changePassword(payload),
  onSuccess: () => router.replace('/dashboard'),
  onError: (e) => {
    const erroresApi = e.response?.data?.errors
    if (e.response?.status === 422 && erroresApi) {
      Object.keys(erroresApi).forEach((campo) => {
        if (campo in errores) errores[campo] = erroresApi[campo][0]
      })
      return
    }
    mensajeError.value = e.response
      ? e.response.data?.message || 'No se pudo cambiar la contraseña'
      : 'Error de conexión con el servidor'
  },
})

const enviar = () => {
  mensajeError.value = ''
  if (!validar()) return
  cambiar.mutate({ ...datos })
}

const salir = async () => {
  await auth.logout()
  router.replace('/login')
}
</script>

<template>
  <v-card class="pa-6" elevation="4" rounded="lg">
    <div class="text-center mb-6">
      <v-avatar color="primary" variant="tonal" size="56" class="mb-3">
        <v-icon icon="mdi-lock-reset" size="30" />
      </v-avatar>
      <h2 class="text-h5 font-weight-bold">Cambia tu contraseña</h2>
      <p class="text-body-2 text-medium-emphasis mt-1">
        Por seguridad, debes definir una contraseña nueva antes de continuar.
      </p>
    </div>

    <v-form novalidate @submit.prevent="enviar">
      <v-text-field
        v-model="datos.current_password"
        label="Contraseña actual"
        :type="ver.actual ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-outline"
        :append-inner-icon="ver.actual ? 'mdi-eye-off' : 'mdi-eye'"
        variant="outlined"
        autocomplete="current-password"
        :error-messages="errores.current_password"
        @click:append-inner="ver.actual = !ver.actual"
      />

      <v-text-field
        v-model="datos.password"
        label="Nueva contraseña"
        :type="ver.nueva ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-plus-outline"
        :append-inner-icon="ver.nueva ? 'mdi-eye-off' : 'mdi-eye'"
        variant="outlined"
        autocomplete="new-password"
        :hint="`Mínimo ${MIN} caracteres`"
        :error-messages="errores.password"
        @click:append-inner="ver.nueva = !ver.nueva"
      />

      <v-text-field
        v-model="datos.password_confirmation"
        label="Confirmar nueva contraseña"
        :type="ver.confirmar ? 'text' : 'password'"
        prepend-inner-icon="mdi-lock-check-outline"
        :append-inner-icon="ver.confirmar ? 'mdi-eye-off' : 'mdi-eye'"
        variant="outlined"
        autocomplete="new-password"
        :error-messages="errores.password_confirmation"
        @click:append-inner="ver.confirmar = !ver.confirmar"
      />

      <v-btn type="submit" color="primary" size="large" block :loading="cambiar.isPending.value">
        Guardar contraseña
      </v-btn>
    </v-form>

    <v-alert v-if="mensajeError" type="error" variant="tonal" class="mt-4" closable>
      {{ mensajeError }}
    </v-alert>

    <div class="text-center mt-4">
      <v-btn variant="text" size="small" @click="salir">Cerrar sesión</v-btn>
    </div>
  </v-card>
</template>
