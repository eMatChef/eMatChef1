<template>
  <div class="help-coming-soon">
    <EEmptyState
      variant="generic"
      icon="mdi-clock-outline"
      :title="pageTitle"
      :description="t('help.comingSoon.description')"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'

const { t } = useI18n()

const pageTitle = computed(() => t('help.overview.title'))
</script>

<style scoped>
.help-coming-soon {
  padding: 24px 16px;
  min-height: 320px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
