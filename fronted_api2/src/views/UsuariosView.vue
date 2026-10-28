<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/service/api'
import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()
const queryClient = useQueryClient()

// ---------------------------------------------------------------------
// Catálogos de los filtros
// ---------------------------------------------------------------------
const TIPOS = [
  { title: 'Administrador', value: 'admin' },
  { title: 'Usuario', value: 'usuario' },
]
const FILTRO_TIPOS = [
  { title: 'Todos', value: '' },
  { title: 'Superadmin', value: 'superadmin' },
  ...TIPOS,
]
const ETIQUETAS = { superadmin: 'Superadmin', admin: 'Administrador', usuario: 'Usuario' }

// El superadmin crea administradores y usuarios; el admin, solo usuarios (lo valida también el backend)
const tiposPermitidos = computed(() =>
  auth.user?.roles?.includes('superadmin') ? TIPOS : TIPOS.filter((t) => t.value === 'usuario'),
)
const FILTRO_ESTADOS = [
  { title: 'Todos', value: '' },
  { title: 'Activo', value: 'activo' },
  { title: 'Inactivo', value: 'inactivo' },
]
const etiquetaTipo = (rol) => ETIQUETAS[rol] ?? rol ?? '—'

// ---------------------------------------------------------------------
// Notificaciones y errores
// ---------------------------------------------------------------------
const snackbar = reactive({ visible: false, text: '', color: 'success' })
const notificar = (text, color = 'success') => {
  snackbar.text = text
  snackbar.color = color
  snackbar.visible = true
}

const mensajeError = (e) => {
  if (e.response?.status === 403) return 'No tienes permiso para realizar esta acción'
  const erroresApi = e.response?.data?.errors
  if (erroresApi) return Object.values(erroresApi)[0][0]
  return e.response?.data?.message || 'Ocurrió un error al comunicarse con el servidor'
}

// ---------------------------------------------------------------------
// Filtros: lo que se escribe (filtros) no se envía hasta pulsar "Buscar" (aplicados)
// ---------------------------------------------------------------------
const filtros = reactive({ texto: '', tipo: '', estado: '' })
const aplicados = reactive({ texto: '', tipo: '', estado: '' })
const page = ref(1)
const perPage = ref(10)

const buscar = () => {
  aplicados.texto = (filtros.texto ?? '').trim()
  aplicados.tipo = filtros.tipo ?? ''
  aplicados.estado = filtros.estado ?? ''
  page.value = 1
}

const limpiar = () => {
  filtros.texto = ''
  filtros.tipo = ''
  filtros.estado = ''
  buscar()
}

watch(perPage, () => {
  page.value = 1
})

const params = computed(() => ({
  page: page.value,
  per_page: perPage.value,
  search: aplicados.texto || undefined,
  role: aplicados.tipo || undefined,
  status: aplicados.estado || undefined,
}))

// ---------------------------------------------------------------------
// Datos
// ---------------------------------------------------------------------
const { data: respuesta, isFetching, isError, error } = useQuery({
  queryKey: ['users', params],
  queryFn: () => api.get('/users', { params: params.value }).then((r) => r.data),
  placeholderData: keepPreviousData,
  enabled: computed(() => auth.can('gestionar-usuarios')),
})

const usuarios = computed(() => respuesta.value?.data ?? [])
const total = computed(() => respuesta.value?.total ?? 0)

const headers = [
  { title: 'Nombre', key: 'name', sortable: false },
  { title: 'Correo', key: 'email', sortable: false },
  { title: 'Tipo', key: 'role', sortable: false },
  { title: 'Estado', key: 'active', sortable: false },
  { title: 'QR (2FA)', key: 'two_factor_enabled', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'acciones', sortable: false, align: 'center' },
]

const invalidar = () => queryClient.invalidateQueries({ queryKey: ['users'] })

// ---------------------------------------------------------------------
// Agregar usuario
// ---------------------------------------------------------------------
const dialogNuevo = ref(false)
const verClave = ref(false)
const form = reactive({ name: '', email: '', role: 'usuario', password: '' })
const erroresForm = reactive({ name: '', email: '', role: '', password: '' })

const limpiarErroresForm = () => Object.keys(erroresForm).forEach((k) => (erroresForm[k] = ''))

const abrirNuevo = () => {
  limpiarErroresForm()
  Object.assign(form, { name: '', email: '', role: 'usuario', password: '' })
  verClave.value = false
  dialogNuevo.value = true
}

const generarClave = () => {
  const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
  const bytes = crypto.getRandomValues(new Uint8Array(10))
  form.password = Array.from(bytes, (b) => letras[b % letras.length]).join('')
  verClave.value = true
}

const copiarClave = async () => {
  if (!form.password) return
  try {
    await navigator.clipboard.writeText(form.password)
    notificar('Contraseña copiada')
  } catch {
    notificar('No se pudo copiar. Selecciónala y cópiala a mano.', 'warning')
  }
}

const validarForm = () => {
  limpiarErroresForm()
  let ok = true
  if (!form.name.trim()) {
    erroresForm.name = 'El nombre es obligatorio'
    ok = false
  }
  if (!form.email.trim()) {
    erroresForm.email = 'El correo es obligatorio'
    ok = false
  } else if (!/\S+@\S+\.\S+/.test(form.email)) {
    erroresForm.email = 'El formato del correo no es válido'
    ok = false
  }
  if (!form.role) {
    erroresForm.role = 'Elige el tipo de usuario'
    ok = false
  }
  if (form.password.length < 6) {
    erroresForm.password = 'La contraseña temporal debe tener al menos 6 caracteres'
    ok = false
  }
  return ok
}

const crear = useMutation({
  mutationFn: (payload) => api.post('/users', payload).then((r) => r.data),
  onSuccess: () => {
    dialogNuevo.value = false
    notificar('Usuario creado. Deberá cambiar su contraseña al iniciar sesión.')
    invalidar()
  },
  onError: (e) => {
    const erroresServidor = e.response?.data?.errors
    if (e.response?.status === 422 && erroresServidor) {
      Object.keys(erroresServidor).forEach((campo) => {
        if (campo in erroresForm) erroresForm[campo] = erroresServidor[campo][0]
      })
      return
    }
    notificar(mensajeError(e), 'error')
  },
})

const guardarNuevo = () => {
  if (!validarForm()) return
  crear.mutate({ ...form, name: form.name.trim(), email: form.email.trim() })
}

// ---------------------------------------------------------------------
// Confirmaciones: resetear QR y activar/desactivar
// ---------------------------------------------------------------------
const confirmar = reactive({
  visible: false,
  titulo: '',
  mensaje: '',
  boton: '',
  color: 'primary',
  accion: null,
  ocupado: false,
})

const pedirConfirmacion = (cfg) => Object.assign(confirmar, { visible: true, ocupado: false, ...cfg })

const ejecutarConfirmacion = async () => {
  confirmar.ocupado = true
  try {
    await confirmar.accion()
  } catch (e) {
    notificar(mensajeError(e), 'error')
  } finally {
    confirmar.ocupado = false
    confirmar.visible = false
  }
}

const esUnoMismo = (u) => u.id === auth.user?.id

const pedirResetQr = (u) =>
  pedirConfirmacion({
    titulo: 'Resetear código QR',
    mensaje: `Se desactivará la verificación en dos pasos de "${u.name}" (por pérdida del teléfono, por ejemplo). Podrá iniciar sesión sin código y volver a configurarla con un QR nuevo.`,
    boton: 'Resetear',
    color: 'warning',
    accion: async () => {
      await api.post(`/users/${u.id}/reset-2fa`)
      notificar(`Se reseteó el QR de ${u.name}`)
      invalidar()
    },
  })

const pedirCambioEstado = (u) => {
  const activar = !u.active
  pedirConfirmacion({
    titulo: activar ? 'Activar usuario' : 'Desactivar usuario',
    mensaje: activar
      ? `"${u.name}" podrá iniciar sesión de nuevo.`
      : `"${u.name}" ya no podrá iniciar sesión y se cerrarán sus sesiones abiertas.`,
    boton: activar ? 'Activar' : 'Desactivar',
    color: activar ? 'success' : 'error',
    accion: async () => {
      await api.patch(`/users/${u.id}/status`, { active: activar })
      notificar(activar ? 'Usuario activado' : 'Usuario desactivado')
      invalidar()
    },
  })
}
</script>

<template>
  <v-alert v-if="!auth.can('gestionar-usuarios')" type="warning" variant="tonal">
    No tienes permiso para administrar usuarios.
  </v-alert>

  <div v-else>
    <!-- Filtros -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filtros.texto"
              label="Filtro"
              placeholder="Nombre o correo"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              @keyup.enter="buscar"
            />
          </v-col>

          <v-col cols="6" md="2">
            <v-select
              v-model="filtros.tipo"
              :items="FILTRO_TIPOS"
              label="Tipo"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>

          <v-col cols="6" md="2">
            <v-select
              v-model="filtros.estado"
              :items="FILTRO_ESTADOS"
              label="Estado"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="4" class="d-flex flex-wrap ga-2 justify-md-end">
            <v-btn color="primary" prepend-icon="mdi-magnify" @click="buscar">Buscar</v-btn>
            <v-btn variant="text" @click="limpiar">Limpiar</v-btn>
            <v-btn color="success" variant="flat" prepend-icon="mdi-account-plus" @click="abrirNuevo">
              Agregar usuario
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-alert v-if="isError" type="error" variant="tonal" class="mb-4">
      {{ mensajeError(error) }}
    </v-alert>

    <!-- Tabla -->
    <v-card>
      <v-data-table-server
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="usuarios"
        :items-length="total"
        :loading="isFetching"
        :items-per-page-options="[10, 25, 50]"
        items-per-page-text="Filas por página"
        no-data-text="No se encontraron usuarios."
        loading-text="Cargando usuarios..."
        hover
      >
        <template #item.role="{ item }">
          <v-chip size="small" :color="['admin', 'superadmin'].includes(item.role) ? 'primary' : undefined" variant="tonal">
            {{ etiquetaTipo(item.role) }}
          </v-chip>
        </template>

        <template #item.active="{ item }">
          <v-chip size="small" :color="item.active ? 'success' : 'grey'" variant="tonal">
            {{ item.active ? 'Activo' : 'Inactivo' }}
          </v-chip>
        </template>

        <template #item.two_factor_enabled="{ item }">
          <v-icon
            :icon="item.two_factor_enabled ? 'mdi-qrcode' : 'mdi-minus'"
            :color="item.two_factor_enabled ? 'success' : 'grey'"
            :title="item.two_factor_enabled ? 'QR configurado' : 'Sin QR'"
          />
        </template>

        <template #item.acciones="{ item }">
          <v-btn
            icon="mdi-qrcode-remove"
            variant="text"
            size="small"
            color="warning"
            title="Resetear QR por pérdida"
            :disabled="!item.two_factor_enabled || !item.puede_gestionar"
            @click="pedirResetQr(item)"
          />
          <v-btn
            :icon="item.active ? 'mdi-account-off' : 'mdi-account-check'"
            variant="text"
            size="small"
            :color="item.active ? 'error' : 'success'"
            :title="item.active ? 'Desactivar' : 'Activar'"
            :disabled="esUnoMismo(item) || !item.puede_gestionar"
            @click="pedirCambioEstado(item)"
          />
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Agregar usuario -->
    <v-dialog v-model="dialogNuevo" max-width="480" persistent>
      <v-card title="Agregar usuario">
        <v-card-text>
          <v-form id="form-usuario" novalidate @submit.prevent="guardarNuevo">
            <v-text-field
              v-model="form.name"
              label="Nombre"
              variant="outlined"
              :error-messages="erroresForm.name"
            />
            <v-text-field
              v-model="form.email"
              label="Correo electrónico"
              type="email"
              variant="outlined"
              :error-messages="erroresForm.email"
            />
            <v-select
              v-model="form.role"
              :items="tiposPermitidos"
              label="Tipo"
              variant="outlined"
              :error-messages="erroresForm.role"
            />
            <v-text-field
              v-model="form.password"
              label="Contraseña temporal"
              :type="verClave ? 'text' : 'password'"
              :append-inner-icon="verClave ? 'mdi-eye-off' : 'mdi-eye'"
              variant="outlined"
              autocomplete="new-password"
              hint="El usuario deberá cambiarla al iniciar sesión por primera vez."
              persistent-hint
              :error-messages="erroresForm.password"
              @click:append-inner="verClave = !verClave"
            />
            <div class="d-flex ga-2 mt-3">
              <v-btn size="small" variant="tonal" prepend-icon="mdi-auto-fix" @click="generarClave">
                Generar
              </v-btn>
              <v-btn
                size="small"
                variant="tonal"
                prepend-icon="mdi-content-copy"
                :disabled="!form.password"
                @click="copiarClave"
              >
                Copiar
              </v-btn>
            </div>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn @click="dialogNuevo = false">Cancelar</v-btn>
          <v-btn
            type="submit"
            form="form-usuario"
            color="primary"
            variant="flat"
            :loading="crear.isPending.value"
          >
            Guardar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmación genérica -->
    <v-dialog v-model="confirmar.visible" max-width="440" :persistent="confirmar.ocupado">
      <v-card :title="confirmar.titulo">
        <v-card-text>{{ confirmar.mensaje }}</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn :disabled="confirmar.ocupado" @click="confirmar.visible = false">Cancelar</v-btn>
          <v-btn
            :color="confirmar.color"
            variant="flat"
            :loading="confirmar.ocupado"
            @click="ejecutarConfirmacion"
          >
            {{ confirmar.boton }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" timeout="4000" location="bottom right">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>
