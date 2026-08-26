<template>
  <div class="fixed-dates-settings">
    <div class="page-header">
      <h2 class="settings-title">{{ t('settings.fixedDates.title') }}</h2>
      <p class="settings-description">{{ pageDescription }}</p>
      <p v-if="isGrossanlassDept" class="settings-hint">{{ t('settings.fixedDates.grossanlassHint') }}</p>
    </div>

    <ECard v-if="!canManage">
      <p class="muted">{{ t('settings.fixedDates.noPermission') }}</p>
    </ECard>

    <DepartmentFixedDatesManager v-else :department-id="departmentId" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { ECard } from '@/components/form/base'
import DepartmentFixedDatesManager from '@/components/settings/DepartmentFixedDatesManager.vue'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()
const canManage = canManageMaterials

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))

const pageDescription = computed(() =>
  isGrossanlassDept.value
    ? t('settings.fixedDates.descriptionGrossanlass')
    : t('settings.fixedDates.description'),
)
</script>

<style scoped>
.fixed-dates-settings {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 960px;
  padding: 4px 8px 16px;
  box-sizing: border-box;
}

.page-header {
  padding: 0 4px;
}

.settings-title {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.settings-description,
.settings-hint,
.muted {
  color: var(--color-text-muted, #6b7280);
  margin: 0;
}

.settings-description {
  margin-top: 6px;
}

.settings-hint {
  margin-top: 10px;
  font-size: 0.88rem;
  padding: 8px 12px;
  background: #eff6ff;
  border-radius: 8px;
  border: 1px solid #bfdbfe;
  color: #1e40af;
}
</style>
