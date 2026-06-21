<template>
  <PageShell
    class="grossanlass-rounds-shell"
    :title="t('grossanlass.planung.rounds.pageTitle')"
  >
    <GrossanlassKeyDatesPanel v-if="departmentId" :department-id="departmentId" />
    <GrossanlassRoundsTab />
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageShell from '@/components/layout/PageShell.vue'
import GrossanlassKeyDatesPanel from '@/components/grossanlass/GrossanlassKeyDatesPanel.vue'
import GrossanlassRoundsTab from '@/views/grossanlass/GrossanlassRoundsTab.vue'

const route = useRoute()
const { t } = useI18n()
const departmentId = computed(() => String(route.params.departmentId || ''))
</script>

<style scoped>
.grossanlass-rounds-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
