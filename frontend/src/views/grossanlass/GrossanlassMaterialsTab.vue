<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t(introKey) }}</p>
    <div class="ga-preview-actions">
      <EButton v-if="canManageMaterials" variant="primary" size="small" @click="createOpen = true">{{ t(addKey) }}</EButton>
    </div>
    <GrossanlassMaterialsPreviewTable :tab="tab" />
    <GrossanlassZusageCreatePreviewDialog
      v-model="createOpen"
      :preset="createPreset"
      :allow-vehicle="false"
      @created="onCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import GrossanlassMaterialsPreviewTable from '@/views/grossanlass/GrossanlassMaterialsPreviewTable.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import { EButton } from '@/components/form/base'
import { gaCanManageProcurement } from '@/utils/grossanlassAccess'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import type { GaMaterialsTabId } from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import type { GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { commitmentTabs } from '@/views/grossanlass/grossanlassCommitmentMap'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const createOpen = ref(false)
const catalog = useGaCommitmentCatalog()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tab = computed<GaMaterialsTabId>(() => {
  const value = (route.meta.materialsTab as string) || 'eigen'
  if (value === 'leihweise' || value === 'eigen') return value
  return 'eigen'
})

const canManageMaterials = computed(() => gaCanManageProcurement(authStore.currentDepartmentRole))

const introKey = computed(() => `grossanlass.materials.${tab.value}Intro`)
const addKey = computed(() => `grossanlass.materials.zusage.addFromZusage`)

const createPreset = computed<Partial<GaZusageCreateDraft>>(() => ({
  family: 'material',
  origin: tab.value === 'eigen' ? 'buy' : 'loan',
}))

watch(
  () => String(route.query.family || ''),
  (family) => {
    const id = departmentId.value
    if (family === 'vehicle' && id) {
      void router.replace(`/${id}/fahrzeuge`)
    }
  },
  { immediate: true },
)

function onCreated(row: GrossanlassCommitment) {
  catalog.upsert(row)
  const id = departmentId.value
  if (!id) return
  const tabs = commitmentTabs(row)
  const from = tabs.includes('leihweise') ? 'leihweise' : 'eigen'
  void router.push({ path: `/${id}/materialien/artikel/${row.id}`, query: { from } })
}
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-actions { margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
</style>
