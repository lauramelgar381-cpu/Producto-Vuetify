<script setup>
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth.js'
import { fetchAllProducts } from '@/composables/useProductsExport.js'

const auth = useAuthStore()

// Misma clave que usan Productos y Reportes: comparten la caché
const { data: productos, isPending } = useQuery({
  queryKey: ['products', 'all'],
  queryFn: fetchAllProducts,
  enabled: computed(() => auth.can('ver-productos')),
})

const lista = computed(() => productos.value ?? [])

// Todo se calcula con la lista completa de productos, así que las cifras son exactas
const totalProductos = computed(() => lista.value.length)
const unidades = computed(() => lista.value.reduce((suma, p) => suma + Number(p.stock), 0))
const conStock = computed(() => lista.value.filter((p) => Number(p.stock) > 0).length)
const sinStock = computed(() => totalProductos.value - conStock.value)

const formato = (n) => Number(n).toLocaleString('en-US')

const tarjetas = computed(() => [
  { titulo: 'Productos registrados', valor: totalProductos.value, icono: 'mdi-package-variant', color: 'primary' },
  { titulo: 'Unidades en stock', valor: unidades.value, icono: 'mdi-warehouse', color: 'success' },
  { titulo: 'Productos con stock', valor: conStock.value, icono: 'mdi-check-circle-outline', color: 'info' },
  { titulo: 'Productos sin stock', valor: sinStock.value, icono: 'mdi-alert-circle-outline', color: 'warning' },
])

// ---------------- Tabla: unidades en stock por producto ----------------
const busqueda = ref('')
const headers = [
  { title: 'Producto', key: 'name' },
  { title: 'Unidades en stock', key: 'stock', value: (p) => Number(p.stock), align: 'end' },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
]
</script>

<template>
  <v-alert v-if="!auth.can('ver-productos')" type="warning" variant="tonal">
    No tienes permiso para ver los datos del inicio.
  </v-alert>

  <div v-else>
    <v-row>
      <v-col v-for="t in tarjetas" :key="t.titulo" cols="12" sm="6" lg="3">
        <v-card :loading="isPending" class="pa-2">
          <v-card-item>
            <template #prepend>
              <v-avatar :color="t.color" variant="tonal" size="52">
                <v-icon :icon="t.icono" size="28" />
              </v-avatar>
            </template>
            <v-card-subtitle>{{ t.titulo }}</v-card-subtitle>
            <v-card-title class="text-h5 font-weight-bold">
              {{ isPending ? '…' : formato(t.valor) }}
            </v-card-title>
          </v-card-item>
        </v-card>
      </v-col>
    </v-row>

    <v-card title="Unidades en stock por producto" class="mt-4">
      <template #append>
        <v-text-field
          v-model="busqueda"
          label="Buscar producto"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          style="min-width: 260px"
        />
      </template>

      <v-data-table
        :headers="headers"
        :items="lista"
        :search="busqueda"
        :loading="isPending"
        :items-per-page="10"
        :items-per-page-options="[10, 25, 50]"
        items-per-page-text="Filas por página"
        no-data-text="No hay productos para mostrar."
        loading-text="Cargando productos..."
        hover
      >
        <template #item.stock="{ item }">
          <strong>{{ formato(item.stock) }}</strong>
        </template>

        <template #item.estado="{ item }">
          <v-chip size="small" :color="Number(item.stock) > 0 ? 'success' : 'error'" variant="tonal">
            {{ Number(item.stock) > 0 ? 'Con stock' : 'Sin stock' }}
          </v-chip>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>
