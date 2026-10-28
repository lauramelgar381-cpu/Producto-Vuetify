<script setup>
import { computed, reactive, ref } from 'vue'
import { useMutation } from '@tanstack/vue-query'
import QRCode from 'qrcode'
import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()

// El backend informa si el 2FA está confirmado (user.two_factor_enabled)
const activo = computed(() => !!auth.user?.two_factor_enabled)

const snackbar = reactive({ visible: false, text: '', color: 'success' })
const notificar = (text, color = 'success') => {
  snackbar.text = text
  snackbar.color = color
  snackbar.visible = true
}

const mensajeError = (e) => {
  const errores = e.response?.data?.errors
  if (errores) return Object.values(errores)[0][0]
  return e.response?.data?.message || 'Ocurrió un error al comunicarse con el servidor'
}

// ---------------------------------------------------------------------
// Activar: 1) pedir el secreto  2) escanear el QR  3) confirmar con un código
// ---------------------------------------------------------------------
const qr = ref('') // imagen del QR (data URL)
const secreto = ref('') // la misma clave en texto, por si no se puede escanear
const codigo = ref('')
const errorCodigo = ref('')

const enConfiguracion = computed(() => !!qr.value)
const secretoLegible = computed(() => (secreto.value.match(/.{1,4}/g) ?? []).join(' '))

const iniciar = useMutation({
  mutationFn: () => auth.setupTwoFactor(),
  onSuccess: async (data) => {
    secreto.value = data.secret
    // El backend entrega la URL otpauth://; aquí se dibuja como imagen
    qr.value = await QRCode.toDataURL(data.otpauth_url, { width: 240, margin: 1 })
    codigo.value = ''
    errorCodigo.value = ''
  },
  onError: (e) => notificar(mensajeError(e), 'error'),
})

const cancelarConfiguracion = () => {
  qr.value = ''
  secreto.value = ''
  codigo.value = ''
  errorCodigo.value = ''
}

const confirmar = useMutation({
  mutationFn: () => auth.enableTwoFactor(codigo.value),
  onSuccess: () => {
    cancelarConfiguracion()
    notificar('Verificación en dos pasos activada')
  },
  onError: (e) => {
    errorCodigo.value = mensajeError(e)
    codigo.value = ''
  },
})

const enviarCodigo = () => {
  errorCodigo.value = ''
  if (codigo.value.length !== 6) {
    errorCodigo.value = 'Ingresa los 6 dígitos de la aplicación'
    return
  }
  confirmar.mutate()
}

const copiarSecreto = async () => {
  try {
    await navigator.clipboard.writeText(secreto.value)
    notificar('Clave copiada')
  } catch {
    notificar('No se pudo copiar. Selecciónala y cópiala a mano.', 'warning')
  }
}

// ---------------------------------------------------------------------
// Desactivar (pide la contraseña)
// ---------------------------------------------------------------------
const dialogDesactivar = ref(false)
const password = ref('')
const verPassword = ref(false)
const errorPassword = ref('')

const abrirDesactivar = () => {
  password.value = ''
  errorPassword.value = ''
  verPassword.value = false
  dialogDesactivar.value = true
}

const desactivar = useMutation({
  mutationFn: () => auth.disableTwoFactor(password.value),
  onSuccess: () => {
    dialogDesactivar.value = false
    notificar('Verificación en dos pasos desactivada')
  },
  onError: (e) => {
    errorPassword.value = mensajeError(e)
  },
})

const enviarDesactivar = () => {
  errorPassword.value = ''
  if (!password.value) {
    errorPassword.value = 'Ingresa tu contraseña'
    return
  }
  desactivar.mutate()
}
</script>

<template>
  <v-row justify="center">
    <v-col cols="12" md="8" lg="6">
      <v-card>
        <v-card-item>
          <template #prepend>
            <v-avatar :color="activo ? 'success' : 'grey'" variant="tonal" size="52">
              <v-icon icon="mdi-shield-lock-outline" size="28" />
            </v-avatar>
          </template>
          <v-card-title>Verificación en dos pasos</v-card-title>
          <v-card-subtitle>
            {{ activo ? 'Activada: al iniciar sesión se pedirá un código.' : 'Desactivada' }}
          </v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <!-- Ya activada -->
          <template v-if="activo">
            <v-alert type="success" variant="tonal" class="mb-4">
              Tu cuenta está protegida con Google Authenticator (u otra aplicación compatible).
              Si pierdes el teléfono, un administrador puede resetear el QR.
            </v-alert>
            <v-btn color="error" variant="outlined" prepend-icon="mdi-shield-off-outline" @click="abrirDesactivar">
              Desactivar
            </v-btn>
          </template>

          <!-- Sin activar y sin empezar -->
          <template v-else-if="!enConfiguracion">
            <p class="text-body-2 mb-4">
              Añade una capa extra de seguridad: además de la contraseña, se pedirá un código de
              6 dígitos que genera una aplicación en tu teléfono, como Google Authenticator.
            </p>
            <v-btn color="primary" prepend-icon="mdi-qrcode" :loading="iniciar.isPending.value" @click="iniciar.mutate()">
              Activar con código QR
            </v-btn>
          </template>

          <!-- Configurando: QR + confirmación -->
          <template v-else>
            <p class="text-body-2 mb-2">
              <strong>1.</strong> Abre Google Authenticator en tu teléfono y escanea este código.
            </p>

            <div class="d-flex justify-center my-4">
              <v-sheet border rounded class="pa-2">
                <img :src="qr" alt="Código QR para la aplicación de autenticación" width="240" height="240" />
              </v-sheet>
            </div>

            <p class="text-body-2 mb-1">¿No puedes escanearlo? Escribe esta clave en la aplicación:</p>
            <div class="d-flex align-center ga-2 mb-4">
              <code class="text-body-1 font-weight-medium">{{ secretoLegible }}</code>
              <v-btn icon="mdi-content-copy" size="x-small" variant="text" title="Copiar" @click="copiarSecreto" />
            </div>

            <p class="text-body-2 mb-2">
              <strong>2.</strong> Escribe el código de 6 dígitos que muestra la aplicación.
            </p>
            <v-otp-input v-model="codigo" length="6" variant="outlined" @finish="enviarCodigo" />
            <p v-if="errorCodigo" class="text-error text-body-2 mb-2">{{ errorCodigo }}</p>

            <div class="d-flex ga-2 mt-2">
              <v-btn color="primary" :loading="confirmar.isPending.value" @click="enviarCodigo">
                Confirmar y activar
              </v-btn>
              <v-btn variant="text" @click="cancelarConfiguracion">Cancelar</v-btn>
            </div>
          </template>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Desactivar -->
  <v-dialog v-model="dialogDesactivar" max-width="420" persistent>
    <v-card title="Desactivar verificación en dos pasos">
      <v-card-text>
        <p class="text-body-2 mb-4">Por seguridad, confirma tu contraseña.</p>
        <v-text-field
          v-model="password"
          label="Contraseña"
          :type="verPassword ? 'text' : 'password'"
          :append-inner-icon="verPassword ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          autocomplete="current-password"
          :error-messages="errorPassword"
          @click:append-inner="verPassword = !verPassword"
          @keyup.enter="enviarDesactivar"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="dialogDesactivar = false">Cancelar</v-btn>
        <v-btn color="error" variant="flat" :loading="desactivar.isPending.value" @click="enviarDesactivar">
          Desactivar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.visible" :color="snackbar.color" timeout="3500" location="bottom right">
    {{ snackbar.text }}
  </v-snackbar>
</template>
