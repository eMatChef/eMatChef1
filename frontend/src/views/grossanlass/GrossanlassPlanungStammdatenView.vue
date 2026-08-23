<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.planung.stammdaten.intro') }}</p>

    <div class="ga-preview-form">
      <ETextField
        :model-value="deptName"
        :label="t('grossanlass.planung.stammdaten.name')"
        disabled
        hide-details
      />
      <ETextField
        :model-value="periodLabel"
        class="mt-3"
        :label="t('grossanlass.planung.stammdaten.period')"
        disabled
        hide-details
      />
      <ETextField
        :model-value="''"
        class="mt-3"
        :label="t('grossanlass.planung.stammdaten.location')"
        :placeholder="t('grossanlass.planung.stammdaten.locationPlaceholder')"
        disabled
        hide-details
      />
      <ETextField
        :model-value="''"
        class="mt-3"
        :label="t('grossanlass.planung.stammdaten.notes')"
        :placeholder="t('grossanlass.planung.stammdaten.notesPlaceholder')"
        disabled
        hide-details
      />
      <div class="ga-preview-actions">
        <EButton variant="primary" size="small" disabled>
          {{ t('common.save') }}
        </EButton>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton, ETextField } from '@/components/form/base'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const membership = computed(() =>
  authStore.departments.find((d) => d.department_id === String(route.params.departmentId || '')),
)

const deptName = computed(() => membership.value?.department?.name || '')

const periodLabel = computed(() => {
  const cfg = membership.value?.department?.grossanlass_config
  const start = cfg?.planned_event_start
  const end = cfg?.planned_event_end
  if (!start) return t('grossanlass.planung.stammdaten.periodEmpty')
  const from = new Date(start).toLocaleDateString('de-CH')
  if (!end) return from
  return `${from} – ${new Date(end).toLocaleDateString('de-CH')}`
})
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-form { max-width: 480px; }
.mt-3 { margin-top: 12px; }
.ga-preview-actions { margin-top: 16px; }
</style>
