<template>
  <PageShell class="ga-logistik-shell" :title="t('grossanlass.logistik.title')" :subtitle="t('grossanlass.logistik.subtitle')">
    <template #filters>
      <v-tabs :model-value="'disposition'" class="materials-view-tabs" color="primary" show-arrows>
        <v-tab value="disposition">
          <v-icon icon="mdi-source-branch" start size="18" />
          {{ t('grossanlass.dispo.tab') }}
        </v-tab>
      </v-tabs>
    </template>
    <router-view />
  </PageShell>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
</script>
