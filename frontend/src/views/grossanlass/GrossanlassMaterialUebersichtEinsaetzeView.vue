<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.materialUebersicht.einsaetzeIntro') }}</p>

    <ul v-if="orders.length" class="wish-book__orders">
      <li v-for="order in orders" :key="order.id">
        <strong>{{ order.object_name }}</strong>
        <span>{{ t('grossanlass.materialUebersicht.qty', { n: order.qty }) }} · {{ order.ressort }}</span>
        <em>{{ t('grossanlass.materialUebersicht.orderNoted') }}</em>
      </li>
    </ul>

    <ELoadingState v-if="uebersicht.loading.value" variant="inline" :message="t('common.loading')" />
    <GrossanlassEinsatzPreviewPanel v-else-if="displayRows.length" :rows="displayRows" />
    <EEmptyState
      v-else
      :title="t('grossanlass.materialUebersicht.emptyEinsaetzeTitle')"
      :description="t('grossanlass.materialUebersicht.emptyEinsaetzeText')"
    />

    <GrossanlassEinsatzBookPreviewDialog
      v-model="dialogOpen"
      v-model:draft="draft"
      :mode="mode"
      :wishes="wishes"
      :free-picks="freePicks"
      :rows="displayRows"
      :resources="resources"
      :chauffeurs="chauffeurs"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassEinsatzPreviewPanel from '@/views/grossanlass/GrossanlassEinsatzPreviewPanel.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
  type GaBookPreviewMode,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import { gaEinsatzComposerKey } from '@/views/grossanlass/gaEinsatzComposer'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { articleToResource, zusageOccupancyBars } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { resourceToPickTemplate } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { useToast } from '@/composables/useToast'

const { t, locale } = useI18n()
const toast = useToast()
const { articles } = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const resources = computed(() => articles.value.map((article) => articleToResource(article)))
const freePicks = computed(() =>
  resources.value
    .map((resource) => {
      const template = resourceToPickTemplate(resource, tr)
      const article = articles.value.find((item) => item.id === resource.id)
      return {
        ...template,
        id: `pick-${resource.id}`,
        objectId: resource.id,
        fromIso: article?.presentFromIso || article?.handoverFromIso || template.fromIso,
        toIso: article?.presentToIso || article?.returnToIso || template.toIso,
        stock: resource.stock,
        qty: resource.kind === 'quantity' ? Math.min(2, resource.stock) : 1,
      }
    }),
)
const wishes = computed(() => uebersicht.wishTemplates.value)
const orders = computed(() => uebersicht.data.value?.orders ?? [])
const occupancy = computed(() => zusageOccupancyBars(articles.value, tr, locale.value))
const displayRows = computed(() => [...uebersicht.bookingRows(), ...occupancy.value])

const chauffeurs = computed(() =>
  (uebersicht.data.value?.cards ?? []).map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)

const mode = ref<GaBookPreviewMode>('einsatz')
const dialogOpen = ref(false)
const draft = ref<GaBookPreviewDraft | null>(null)

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

async function onConfirm(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create({
      kind: mode.value === 'order' ? 'order' : 'einsatz',
      commitment_id: current.objectId || undefined,
      wish_line_id: current.fromWish ? current.id : null,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      group_id: current.groupId || null,
      pending: current.hasConflict,
      has_conflict: current.hasConflict,
    })
    toast.success(
      current.hasConflict
        ? t('grossanlass.materialUebersicht.mwNoteSent')
        : t('grossanlass.beschaffung.zusagen.createdToast'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: var(--color-text-muted, #6b7280); font-size: 0.9rem; }
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
