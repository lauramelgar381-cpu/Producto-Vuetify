<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { pdfComoUrl } from '@/composables/useProductsExport.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  productos: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const url = ref('')
const marco = ref(null)

// El PDF vive en memoria (blob): se crea al abrir y se libera al cerrar
const liberar = () => {
  if (url.value) URL.revokeObjectURL(url.value)
  url.value = ''
}

watch(
  () => props.modelValue,
  (abierto) => {
    liberar()
    if (abierto) url.value = pdfComoUrl(props.productos)
  },
)

onBeforeUnmount(liberar)

const cerrar = () => emit('update:modelValue', false)

const imprimir = () => {
  try {
    marco.value.contentWindow.focus()
    marco.value.contentWindow.print()
  } catch {
    // Si el navegador no deja imprimir desde el iframe, se abre en otra pestaña
    window.open(url.value, '_blank')
  }
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="1100"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card title="Vista previa del listado de productos">
      <v-card-text class="pa-0" style="height: 72vh">
        <iframe
          v-if="url"
          ref="marco"
          :src="`${url}#toolbar=1&navpanes=0&view=FitH`"
          title="Vista previa del PDF"
          style="width: 100%; height: 100%; border: 0; display: block"
        />
        <div v-else class="d-flex justify-center align-center fill-height">
          <v-progress-circular indeterminate color="primary" />
        </div>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="flat" prepend-icon="mdi-printer" :disabled="!url" @click="imprimir">
          Imprimir
        </v-btn>
        <v-btn @click="cerrar">Cerrar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
