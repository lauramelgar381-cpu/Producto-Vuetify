<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/stores/auth.js'
import logo from '@/assets/logo.jpg'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const queryClient = useQueryClient()

// Menú expandible: rail = solo iconos
const rail = ref(false)

// Encabezado dinámico: toma el título de la ruta activa
const titulo = computed(() => route.meta.title ?? 'Inicio')

// Cada item aparece solo si el usuario tiene el permiso (o si no se exige ninguno)
const items = computed(() =>
  [
    { title: 'Dashboard', icon: 'mdi-view-dashboard', to: '/dashboard' },
    { title: 'Productos', icon: 'mdi-package-variant', to: '/productos', permiso: 'ver-productos' },
    { title: 'Reportes', icon: 'mdi-chart-bar', to: '/reportes', permiso: 'ver-productos' },
    { title: 'Usuarios', icon: 'mdi-account-group', to: '/usuarios', permiso: 'gestionar-usuarios' },
    // { title: 'Usuarios', icon: 'mdi-account-group', to: '/usuarios' }, // futura
  ].filter((i) => !i.permiso || auth.can(i.permiso)),
)

const logoutMutation = useMutation({
  mutationFn: () => auth.logout(),
  onSettled: async () => {
    await router.replace('/login')
    queryClient.clear()
  },
})
</script>

<template>
  <v-navigation-drawer :rail="rail" permanent>
    <!-- Logo + botón para contraer/expandir -->
    <v-list-item :prepend-avatar="logo" title="Productos" nav>
      <template #append>
        <v-btn
          :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
          variant="text"
          size="small"
          @click="rail = !rail"
        />
      </template>
    </v-list-item>

    <v-divider />

    <v-list nav density="comfortable">
      <v-list-item
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        :prepend-icon="item.icon"
        :title="item.title"
      />
    </v-list>

    <template #append>
      <v-list nav density="comfortable">
        <v-list-item
          prepend-icon="mdi-logout"
          title="Cerrar sesión"
          :disabled="logoutMutation.isPending.value"
          @click="logoutMutation.mutate()"
        />
      </v-list>
    </template>
  </v-navigation-drawer>

  <v-app-bar flat border>
    <v-app-bar-title>{{ titulo }}</v-app-bar-title>
    <v-spacer />
    <v-chip prepend-icon="mdi-account-circle" variant="tonal" class="mr-4">
      {{ auth.user?.name }}
    </v-chip>
  </v-app-bar>

  <v-main>
    <v-container fluid>
      <router-view />
    </v-container>
  </v-main>
</template>
