<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth.js'
import {
  useProductsQuery,
  useCreateProduct,
  useUpdateProduct,
  useDeleteProduct,
} from '@/composables/useProducts.js'
import { fetchAllProducts, exportCsv } from '@/composables/useProductsExport.js'
import PdfPreviewDialog from '@/components/PdfPreviewDialog.vue'

const auth = useAuthStore()

// Refresca usuario y permisos al entrar
onMounted(() => {
  auth.fetchMe().catch(() => {})
})

// --- Notificaciones (reemplazan a Toast de PrimeVue) ---
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

// --- Tabla: página, búsqueda y orden ---
const page = ref(1)
const perPage = ref(5)
const search = ref('')
const searchDebounced = ref('')
// Vuetify maneja el orden como un arreglo: [{ key: 'campo', order: 'asc' | 'desc' }]
const sortBy = ref([{ key: 'id', order: 'asc' }])

let temporizador
watch(search, (valor) => {
  clearTimeout(temporizador)
  temporizador = setTimeout(() => {
    searchDebounced.value = valor
    page.value = 1
  }, 400)
})

// Al cambiar el orden o el tamaño de página volvemos a la página 1
watch([sortBy, perPage], () => {
  page.value = 1
})

const params = computed(() => ({
  page: page.value,
  per_page: perPage.value,
  search: searchDebounced.value || undefined,
  sort: sortBy.value[0]?.key ?? 'id',
  dir: sortBy.value[0]?.order ?? 'asc',
}))

const headers = computed(() => {
  const cols = [
    { title: '#', key: 'id', sortable: true, width: 80 },
    { title: 'Nombre', key: 'name', sortable: true },
    { title: 'Descripción', key: 'description', sortable: false },
    { title: 'Precio', key: 'price', sortable: true, align: 'end' },
    { title: 'Stock', key: 'stock', sortable: true, align: 'end' },
  ]
  if (auth.can('editar-productos') || auth.can('eliminar-productos')) {
    cols.push({ title: 'Acciones', key: 'acciones', sortable: false, align: 'center' })
  }
  return cols
})

// --- Datos ---
const { data: respuesta, isPending, isError, error } = useProductsQuery(params)
const productos = computed(() => respuesta.value?.data ?? [])
const total = computed(() => respuesta.value?.total ?? 0)
watch(respuesta, (r) => {
  if (r && r.last_page && page.value > r.last_page) {
    page.value = r.last_page
  }
})

const crear = useCreateProduct()
const actualizar = useUpdateProduct()
const eliminar = useDeleteProduct()

// Todos los productos (para exportación).
// La clave empieza por 'products', así que invalidateQueries también la refresca.
const { data: todos } = useQuery({
  queryKey: ['products', 'all'],
  queryFn: fetchAllProducts,
  enabled: computed(() => auth.can('ver-productos')),
})

const formatoPrecio = (valor) =>
  Number(valor).toLocaleString('en-US', { style: 'currency', currency: 'USD' })

// --- Vista previa del PDF (ventana con iframe e impresión) ---
const pdfVisible = ref(false)

// --- Exportación ---
const exportando = ref(false)

const exportar = async () => {
  exportando.value = true
  try {
    const lista = todos.value ?? (await fetchAllProducts())
    exportCsv(lista)
  } catch (e) {
    notificar(mensajeError(e), 'error')
  } finally {
    exportando.value = false
  }
}

// --- Formulario ---
const dialogVisible = ref(false)
const form = reactive({ id: null, name: '', description: '', price: 0, stock: 0 })
const errores = reactive({ name: '', description: '', price: '', stock: '' })

const limpiarErrores = () => {
  Object.keys(errores).forEach((k) => (errores[k] = ''))
}

const validarFormulario = () => {
  limpiarErrores()
  let valido = true

  if (!form.name?.trim()) {
    errores.name = 'El nombre es obligatorio'
    valido = false
  } else if (form.name.length > 255) {
    errores.name = 'El nombre no puede superar 255 caracteres'
    valido = false
  }

  if (form.price === '' || form.price === null || form.price === undefined || Number(form.price) < 0) {
    errores.price = 'El precio debe ser 0 o mayor'
    valido = false
  }

  if (form.stock === '' || form.stock === null || form.stock === undefined || Number(form.stock) < 0) {
    errores.stock = 'El stock debe ser 0 o mayor'
    valido = false
  }

  return valido
}

const abrirNuevo = () => {
  limpiarErrores()
  Object.assign(form, { id: null, name: '', description: '', price: 0, stock: 0 })
  dialogVisible.value = true
}

const abrirEditar = (producto) => {
  limpiarErrores()
  Object.assign(form, producto)
  dialogVisible.value = true
}

const guardar = () => {
  if (!validarFormulario()) return

  const { id, ...campos } = form
  campos.price = Number(campos.price)
  campos.stock = Number(campos.stock)

  const opciones = {
    onSuccess: () => {
      dialogVisible.value = false
      notificar('Producto guardado')
    },
    onError: (e) => {
      const erroresServidor = e.response?.data?.errors
      if (e.response?.status === 422 && erroresServidor) {
        Object.keys(erroresServidor).forEach((campo) => {
          if (campo in errores) errores[campo] = erroresServidor[campo][0]
        })
        return
      }
      notificar(mensajeError(e), 'error')
    },
  }

  if (id) actualizar.mutate({ id, ...campos }, opciones)
  else crear.mutate(campos, opciones)
}

// --- Confirmación de borrado (reemplaza a ConfirmDialog) ---
const confirmar = reactive({ visible: false, producto: null })

const pedirEliminar = (producto) => {
  confirmar.producto = producto
  confirmar.visible = true
}

const eliminarConfirmado = () => {
  eliminar.mutate(confirmar.producto.id, {
    onSuccess: () => {
      confirmar.visible = false
      notificar('Producto eliminado')
    },
    onError: (e) => {
      confirmar.visible = false
      notificar(mensajeError(e), 'error')
    },
  })
}
</script>

<template>
  <div>
    <!-- Sin permiso -->
    <v-alert v-if="!auth.can('ver-productos')" type="warning" variant="tonal">
      No tienes permiso para ver los productos.
    </v-alert>

    <template v-else>
      <v-alert v-if="isError" type="error" variant="tonal" class="mb-4">
        {{ mensajeError(error) }}
      </v-alert>

      <!-- Barra de herramientas -->
      <v-row align="center" class="mb-2">
        <v-col cols="12" md="5">
          <v-text-field
            v-model="search"
            label="Buscar por nombre o descripción"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="comfortable"
            hide-details
            clearable
          />
        </v-col>

        <v-col cols="12" md="7" class="d-flex justify-end flex-wrap ga-2">
          <v-btn
            variant="outlined"
            prepend-icon="mdi-file-delimited"
            :loading="exportando"
            @click="exportar"
          >
            CSV
          </v-btn>

          <v-btn
            variant="outlined"
            prepend-icon="mdi-file-pdf-box"
            :disabled="!todos?.length"
            @click="pdfVisible = true"
          >
            PDF
          </v-btn>

          <v-btn
            v-if="auth.can('crear-productos')"
            color="primary"
            prepend-icon="mdi-plus"
            @click="abrirNuevo"
          >
            Nuevo producto
          </v-btn>
        </v-col>
      </v-row>

      <!-- Tabla con paginación en el servidor -->
      <v-card>
        <v-data-table-server
          v-model:page="page"
          v-model:items-per-page="perPage"
          v-model:sort-by="sortBy"
          :headers="headers"
          :items="productos"
          :items-length="total"
          :loading="isPending"
          :items-per-page-options="[5, 10, 20]"
          items-per-page-text="Filas por página"
          no-data-text="No se encontraron productos."
          loading-text="Cargando productos..."
          hover
        >
          <template #item.id="{ index }">
            {{ (page - 1) * perPage + index + 1 }}
          </template>

          <template #item.price="{ item }">
            {{ formatoPrecio(item.price) }}
          </template>

          <template #item.acciones="{ item }">
            <v-btn
              v-if="auth.can('editar-productos')"
              icon="mdi-pencil"
              variant="text"
              size="small"
              @click="abrirEditar(item)"
            />
            <v-btn
              v-if="auth.can('eliminar-productos')"
              icon="mdi-delete"
              variant="text"
              size="small"
              color="error"
              @click="pedirEliminar(item)"
            />
          </template>
        </v-data-table-server>
      </v-card>
    </template>

    <!-- Diálogo de producto -->
    <v-dialog v-model="dialogVisible" max-width="480" persistent>
      <v-card :title="form.id ? 'Editar producto' : 'Nuevo producto'">
        <v-card-text>
          <v-form id="form-producto" novalidate @submit.prevent="guardar">
            <v-text-field
              v-model="form.name"
              label="Nombre"
              variant="outlined"
              :error-messages="errores.name"
            />
            <v-textarea
              v-model="form.description"
              label="Descripción"
              rows="3"
              variant="outlined"
              :error-messages="errores.description"
            />
            <v-text-field
              v-model="form.price"
              label="Precio"
              type="number"
              min="0"
              step="0.01"
              prefix="$"
              variant="outlined"
              :error-messages="errores.price"
            />
            <v-text-field
              v-model="form.stock"
              label="Stock"
              type="number"
              min="0"
              step="1"
              variant="outlined"
              :error-messages="errores.stock"
            />
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn @click="dialogVisible = false">Cancelar</v-btn>
          <v-btn
            type="submit"
            form="form-producto"
            color="primary"
            variant="flat"
            :loading="crear.isPending.value || actualizar.isPending.value"
          >
            Guardar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmar eliminación -->
    <v-dialog v-model="confirmar.visible" max-width="400">
      <v-card title="Confirmar">
        <v-card-text>¿Eliminar "{{ confirmar.producto?.name }}"?</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="confirmar.visible = false">Cancelar</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="eliminar.isPending.value"
            @click="eliminarConfirmado"
          >
            Eliminar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <PdfPreviewDialog v-model="pdfVisible" :productos="todos ?? []" />

    <!-- Notificaciones -->
    <v-snackbar v-model="snackbar.visible" :color="snackbar.color" timeout="3500" location="bottom right">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>
