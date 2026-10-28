<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const code = ref('')
const error = ref('')

// Solo rutas internas (evita redirecciones a sitios externos)
const destino = () => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard'
}

const verificar = useMutation({
  mutationFn: () => auth.verifyTwoFactor(code.value),
  onSuccess: () => router.replace(destino()),
  onError: (e) => {
    error.value =
      e.response?.data?.errors?.code?.[0] ||
      e.response?.data?.message ||
      'No se pudo verificar el código'
    code.value = ''
  },
})

const enviar = () => {
  error.value = ''
  if (code.value.length !== 6) {
    error.value = 'Ingresa los 6 dígitos de la aplicación'
    return
  }
  verificar.mutate()
}

const volver = () => {
  auth.clearSession()
  router.replace('/login')
}
</script>

<template>
  <v-card class="pa-6" elevation="4" rounded="lg">
    <div class="text-center mb-6">
      <v-avatar color="primary" variant="tonal" size="56" class="mb-3">
        <v-icon icon="mdi-shield-lock-outline" size="30" />
      </v-avatar>
      <h2 class="text-h5 font-weight-bold">Verificación en dos pasos</h2>
      <p class="text-body-2 text-medium-emphasis mt-1">
        Escribe el código de 6 dígitos de Google Authenticator.
      </p>
    </div>

    <v-form novalidate @submit.prevent="enviar">
      <v-otp-input v-model="code" length="6" variant="outlined" autofocus @finish="enviar" />

      <v-btn type="submit" color="primary" size="large" block class="mt-2" :loading="verificar.isPending.value">
        Verificar
      </v-btn>
    </v-form>

    <v-alert v-if="error" type="error" variant="tonal" class="mt-4" closable>
      {{ error }}
    </v-alert>

    <div class="text-center mt-4">
      <v-btn variant="text" size="small" @click="volver">Volver al inicio de sesión</v-btn>
    </div>
  </v-card>
</template>
