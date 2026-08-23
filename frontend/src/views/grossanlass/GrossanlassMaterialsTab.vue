<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t(introKey) }}</p>
    <div class="ga-preview-actions">
      <EButton variant="primary" size="small" @click="createOpen = true">{{ t(addKey) }}</EButton>
    </div>
    <GrossanlassMaterialsPreviewTable :tab="tab" />
    <GrossanlassZusageCreatePreviewDialog v-model="createOpen" :preset="createPreset" @created="onCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassMaterialsPreviewTable from '@/views/grossanlass/GrossanlassMaterialsPreviewTable.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import { EButton } from '@/components/form/base'
import type { GaMaterialsTabId } from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import type { GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import type { GaZusageArticle } from '@/views/grossanlass/grossanlassZusagePreviewData'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const createOpen = ref(false)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tab = computed<GaMaterialsTabId>(() => {
  const value = (route.meta.materialsTab as string) || 'eigen'
  if (value === 'leihweise' || value === 'fahrzeuge' || value === 'eigen') return value
  return 'eigen'
})

const introKey = computed(() => `grossanlass.materials.${tab.value}Intro`)
const addKey = computed(() => `grossanlass.materials.zusage.addFromZusage`)

const createPreset = computed<Partial<GaZusageCreateDraft>>(() => ({
  family: tab.value === 'fahrzeuge' ? 'vehicle' : 'material',
  origin: tab.value === 'eigen' ? 'buy' : 'loan',
}))

function onCreated(article: GaZusageArticle) {
  const id = departmentId.value
  if (!id) return
  const from = article.tabs.includes('fahrzeuge')
    ? 'fahrzeuge'
    : article.tabs.includes('leihweise')
      ? 'leihweise'
      : 'eigen'
  void router.push({ path: `/${id}/materialien/artikel/${article.id}`, query: { from } })
}
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-preview-actions { margin-bottom: 12px; }
</style>
