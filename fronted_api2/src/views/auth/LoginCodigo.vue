<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const code = ref('')
const paso = ref(1) // 1 = pedir código, 2 = verificar
const mensaje = ref('')
const error = ref('')

const pedir = useMutation({
  mutationFn: () => auth.requestCode(email.value),
  onSuccess: () => {
    paso.value = 2
    mensaje.value = 'Si el correo existe, enviamos un código. Revisa tu bandeja.'
  },
  onError: (e) => {
    error.value = e.response?.data?.errors?.email?.[0] || 'No se pudo enviar el código'
  },
})

const verificar = useMutation({
  mutationFn: () => auth.verifyCode(email.value, code.value),
  // Si el usuario tiene 2FA, pasa al paso del código de Google Authenticator
  onSuccess: (data) => router.push(data.two_factor ? '/verificar-2fa' : '/dashboard'),
  onError: (e) => {
    error.value = e.response?.data?.errors?.code?.[0] || 'No se pudo verificar el código'
  },
})

const cargando = computed(() => pedir.isPending.value || verificar.isPending.value)

const enviar = () => {
  error.value = ''
  if (paso.value === 1) pedir.mutate()
  else verificar.mutate()
}
</script>

<template>
  <v-card class="pa-6" elevation="4" rounded="lg">
    <div class="text-center mb-6">
      <h2 class="text-h5 font-weight-bold">Entrar con código</h2>
      <p class="text-body-2 text-medium-emphasis mt-1">
        {{ paso === 1 ? 'Te enviaremos un código a tu correo' : mensaje }}
      </p>
    </div>

    <v-form novalidate @submit.prevent="enviar">
      <v-text-field
        v-model="email"
        label="Correo electrónico"
        type="email"
        prepend-inner-icon="mdi-email-outline"
        variant="outlined"
        autocomplete="email"
        :disabled="paso === 2"
      />

      <!-- v-otp-input: seis casillas, una por dígito -->
      <div v-if="paso === 2" class="mb-4">
        <p class="text-body-2 mb-2">Código de 6 dígitos</p>
        <v-otp-input v-model="code" length="6" variant="outlined" @finish="enviar" />
      </div>

      <v-btn type="submit" color="primary" size="large" block :loading="cargando">
        {{ paso === 1 ? 'Enviar código' : 'Verificar' }}
      </v-btn>
    </v-form>

    <v-alert v-if="error" type="error" variant="tonal" class="mt-4" closable>
      {{ error }}
    </v-alert>

    <div class="text-center mt-4">
      <v-btn variant="text" size="small" to="/login">Volver al inicio de sesión</v-btn>
    </div>
  </v-card>
</template>