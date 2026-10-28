<script setup>
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from 'chart.js'
import { useAuthStore } from '@/stores/auth.js'
import { fetchAllProducts, exportCsv } from '@/composables/useProductsExport.js'
import PdfPreviewDialog from '@/components/PdfPreviewDialog.vue'

// Chart.js es modular: hay que registrar lo que se usa
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const auth = useAuthStore()

// Misma clave que usan Productos e Inicio, así comparten la caché
const { data: productos, isPending } = useQuery({
  queryKey: ['products', 'all'],
  queryFn: fetchAllProducts,
  enabled: computed(() => auth.can('ver-productos')),
})

const cargando = computed(() => auth.can('ver-productos') && isPending.value)
const hayDatos = computed(() => (productos.value?.length ?? 0) > 0)

// ---------------- Gráfico ----------------
const chartData = computed(() => ({
  labels: (productos.value ?? []).map((p) => p.name),
  datasets: [
    {
      label: 'Stock',
      data: (productos.value ?? []).map((p) => p.stock),
      backgroundColor: '#10b981',
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}

// ---------------- PDF: vista previa en una ventana ----------------
const pdfVisible = ref(false)
</script>

<template>
  <v-alert v-if="!auth.can('ver-productos')" type="warning" variant="tonal">
    No tienes permiso para ver los reportes.
  </v-alert>

  <div v-else-if="cargando" class="d-flex justify-center pa-10">
    <v-progress-circular indeterminate color="primary" size="48" />
  </div>

  <v-alert v-else-if="!hayDatos" type="info" variant="tonal">No hay productos para mostrar.</v-alert>

  <div v-else>
    <div class="d-flex flex-wrap justify-end ga-2 mb-4">
      <v-btn variant="outlined" prepend-icon="mdi-file-delimited" @click="exportCsv(productos)">
        CSV
      </v-btn>
      <v-btn variant="outlined" prepend-icon="mdi-file-pdf-box" @click="pdfVisible = true">
        PDF
      </v-btn>
    </div>

    <v-card title="Stock por producto" class="pa-2">
      <v-card-text>
        <div style="height: 24rem">
          <Bar :data="chartData" :options="chartOptions" />
        </div>
      </v-card-text>
    </v-card>

    <PdfPreviewDialog v-model="pdfVisible" :productos="productos" />
  </div>
</template>
