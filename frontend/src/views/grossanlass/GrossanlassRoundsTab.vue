<template>
  <div class="grossanlass-planung-tab">
    <EEmptyState
      variant="create"
      icon="mdi-calendar-clock"
      :title="t('grossanlass.planung.rounds.emptyTitle')"
      :description="t('grossanlass.planung.rounds.emptyDescription')"
    >
      <template #actions>
        <EButton variant="primary" disabled>
          {{ t('grossanlass.planung.rounds.addAction') }}
        </EButton>
      </template>
    </EEmptyState>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton } from '@/components/form/base'

const { t } = useI18n()
</script>

<style scoped>
.grossanlass-planung-tab {
  padding: 8px 0 24px;
}
</style>
