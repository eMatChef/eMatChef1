<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.einsaetzeIntro') }}</p>

    <ul v-if="orders.length" class="wish-book__orders">
      <li v-for="order in orders" :key="order.id">
        <strong>{{ order.objectName }}</strong>
        <span>{{ t('grossanlass.materialUebersicht.qty', { n: order.qty }) }} · {{ order.ressort }}</span>
        <em>{{ t('grossanlass.materialUebersicht.orderNoted') }}</em>
      </li>
    </ul>

    <p v-if="mwNote" class="ga-preview-mw">{{ mwNote }}</p>

    <GrossanlassEinsatzPreviewPanel :rows="displayRows" />

    <GrossanlassEinsatzBookPreviewDialog
      v-model="dialogOpen"
      v-model:draft="draft"
      :mode="mode"
      :wishes="wishes"
      :free-picks="freePicks"
      :rows="displayRows"
      @confirm="onConfirmPreview"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import GrossanlassEinsatzBookPreviewDialog from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import type { GaBookPreviewDraft, GaBookPreviewMode } from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import { gaEinsatzComposerKey } from '@/views/grossanlass/gaEinsatzComposer'
import {
  createGrossanlassEinsatzPreview,
  createGrossanlassWishBookingTemplates,
  resourceToPickTemplate,
  wishTemplateToEinsatz,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { mergedEinsatzResources, occupancyBarsForPreview } from '@/views/grossanlass/grossanlassZusagePreviewStore'

const { t, locale } = useI18n()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const preview = computed(() => createGrossanlassEinsatzPreview(tr))
const wishes = computed(() => createGrossanlassWishBookingTemplates(tr))
const freePicks = computed(() => {
  const all = mergedEinsatzResources(tr).map((resource) => resourceToPickTemplate(resource, tr))
  return mode.value === 'order' ? all.filter((item) => item.objectId !== 'gator' && item.objectId !== 'teleskop') : all
})
const occupancy = computed(() => occupancyBarsForPreview(tr, locale.value))
const extraRows = ref<GaPreviewEinsatz[]>([])
const orders = ref<GaPreviewEinsatz[]>([])
const mwNote = ref('')
const mode = ref<GaBookPreviewMode>('einsatz')
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)

const displayRows = computed(() => [...preview.value.einsaetze, ...occupancy.value, ...extraRows.value])

function openModal(next: GaBookPreviewMode) {
  mode.value = next
  draft.value = null
  dialogOpen.value = true
}

const composer = inject(gaEinsatzComposerKey, null)
onMounted(() => {
  if (composer) composer.open = openModal
})
onBeforeUnmount(() => {
  if (composer) composer.open = () => {}
})

function onConfirmPreview(current: GaBookPreviewDraft) {
  if (mode.value === 'einsatz') {
    extraRows.value = [
      ...extraRows.value,
      wishTemplateToEinsatz(current, `preview-book-${Date.now()}`),
    ]
    mwNote.value = current.hasConflict
      ? t('grossanlass.materialUebersicht.mwNoteSent')
      : ''
    return
  }
  orders.value = [...orders.value, wishTemplateToEinsatz(current, `preview-order-${Date.now()}`)]
  mwNote.value = ''
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
.ga-preview-mw {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  font-size: 0.85rem;
}
.wish-book__orders {
  list-style: none;
  margin: 0 0 16px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  display: grid;
  gap: 8px;
}
.wish-book__orders li { display: flex; flex-direction: column; gap: 2px; }
.wish-book__orders span, .wish-book__orders em { font-size: 0.8rem; color: #64748b; font-style: normal; }
</style>
