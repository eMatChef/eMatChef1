<template>
  <PageShell
    class="grossanlass-planung-shell"
    :title="t('grossanlass.planung.tabWishes')"
  >
    <GrossanlassPlanungRoundsPane />
  </PageShell>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import GrossanlassPlanungRoundsPane from '@/views/grossanlass/GrossanlassPlanungRoundsPane.vue'

const { t } = useI18n()
</script>

<style scoped>
.grossanlass-planung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
