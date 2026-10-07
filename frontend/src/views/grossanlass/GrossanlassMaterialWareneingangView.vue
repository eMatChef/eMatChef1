<template>
  <div class="wareneingang">
    <v-tabs v-model="variant" class="materials-view-tabs wareneingang__tabs" color="primary" density="compact">
      <v-tab value="bestehend">{{ t('grossanlass.material.wareneingangVariant.bestehend') }}</v-tab>
      <v-tab value="neu">
        {{ t('grossanlass.material.wareneingangVariant.neu') }}
        <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.material.wareneingangVariant.temporary') }}</v-chip>
      </v-tab>
    </v-tabs>

    <GrossanlassWareneingangNeu v-if="variant === 'neu'" />
    <GrossanlassWareneingangBestehend v-else />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import GrossanlassWareneingangBestehend from '@/views/grossanlass/wareneingang/GrossanlassWareneingangBestehend.vue'
import GrossanlassWareneingangNeu from '@/views/grossanlass/wareneingang/GrossanlassWareneingangNeu.vue'
import '@/styles/views/materials-view-tabs.css'

/** Vorübergehend zwei Varianten: «Bestehend» (unveränderte bisherige Ansicht) und «Neu» (Entwurf, Standard). */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const variant = computed<'bestehend' | 'neu'>({
  get: () => (route.query.ansicht === 'bestehend' ? 'bestehend' : 'neu'),
  set: (value) => {
    void router.replace({ query: { ...route.query, ansicht: value === 'neu' ? undefined : value } })
  },
})
</script>

<style scoped>
.wareneingang__tabs {
  margin-bottom: 12px;
}
</style>
