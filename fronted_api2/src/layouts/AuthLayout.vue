<template>
  <v-main class="bg-grey-lighten-4">
    <v-container class="fill-height" fluid>
      <v-row justify="center" align="center">
        <v-col cols="12" sm="8" md="5" lg="4">
          <router-view />
        </v-col>
      </v-row>
    </v-container>
  </v-main>
</template>