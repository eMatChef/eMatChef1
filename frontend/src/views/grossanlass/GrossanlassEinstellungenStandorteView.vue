<template>
  <div class="ga-standorte-page">
    <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.standorteIntro') }}</p>
    <div v-if="departmentId" class="ga-standorte-page__panel">
      <DepartmentAddressKindPanel :department-id="departmentId" address-kind="storage" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'

defineOptions({ name: 'GrossanlassEinstellungenStandorte' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})
</script>

<style scoped>
.ga-standorte-page {
  padding: 4px 0 24px;
}

.ga-standorte-page__intro {
  margin: 0 0 16px;
  color: #64748b;
  font-size: 0.9rem;
}

.ga-standorte-page__panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
</style>
