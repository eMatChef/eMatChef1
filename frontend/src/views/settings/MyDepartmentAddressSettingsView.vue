<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ pageTitle }}</h1>
      <p class="description">{{ pageDescription }}</p>
    </div>

    <div v-if="!departmentId" class="card">
      <p class="muted">{{ t('settings.myDepartment.noDepartmentSelected') }}</p>
    </div>

    <div v-else class="card">
      <DepartmentAddressKindPanel :department-id="departmentId" :address-kind="addressKind" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))
const addressKind = computed(() => (route.meta.addressKind === 'billing' ? 'billing' : 'storage'))

const pageTitle = computed(() =>
  addressKind.value === 'billing'
    ? t('settings.addressPages.billingTitle')
    : t('settings.addressPages.storageTitle')
)
const pageDescription = computed(() =>
  addressKind.value === 'billing'
    ? t('settings.addressPages.billingDescription')
    : t('settings.addressPages.storageDescription')
)
</script>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.header h1 {
  margin: 0;
  font-size: 24px;
}
.description,
.muted {
  color: #6b7280;
}
.card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
</style>
