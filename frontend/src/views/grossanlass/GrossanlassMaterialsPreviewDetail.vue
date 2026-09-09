<template>
  <div class="material-detail-view ga-preview-detail">
    <header class="detail-header">
      <div class="header-left">
        <EButton
          variant="secondary"
          size="small"
          class="material-detail-back-btn"
          @click="goBack"
        >
          <v-icon icon="mdi-arrow-left" start size="20" />
          {{ t('components.materialDetail.backToList') }}
        </EButton>
        <div v-if="item" class="header-title">
          <h1>{{ item.name }}</h1>
          <span v-if="stemCharges.length > 1" class="combo-type-badge physical_combo">
            {{ t('grossanlass.materials.chargeCount', { count: stemCharges.length }) }}
          </span>
        </div>
      </div>
    </header>

    <div class="detail-body">
      <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

      <template v-else-if="item">
        <v-tabs
          v-model="activeTab"
          class="material-detail-tabs"
          align-tabs="start"
          color="primary"
          show-arrows
        >
          <v-tab value="data">{{ t('grossanlass.materials.detailTabData') }}</v-tab>
          <v-tab value="wishes">{{ t('grossanlass.materials.detailTabWishes') }}</v-tab>
          <v-tab value="stock">{{ t('grossanlass.materials.detailTabStock') }}</v-tab>
          <v-tab value="usage">{{ t('grossanlass.materials.detailTabUsage') }}</v-tab>
        </v-tabs>

        <div class="detail-content">
          <div class="content-layout">
            <main class="content-main">
              <v-tabs-window v-model="activeTab" class="material-detail-tabs-window">
                <v-tabs-window-item value="data" class="material-detail-window-item">
                  <section class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.detailTabData') }}</h2>
                    <dl class="user-readonly-fields">
                      <div class="user-readonly-row">
                        <dt>{{ t('common.name') }}</dt>
                        <dd>{{ item.name }}</dd>
                      </div>
                      <div v-if="item.category_name" class="user-readonly-row">
                        <dt>{{ t('components.materialDetail.sidebarCategory') }}</dt>
                        <dd>{{ item.category_name }}</dd>
                      </div>
                      <div v-if="item.pack_unit" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.detailFieldUnit') }}</dt>
                        <dd>{{ item.pack_unit }}</dd>
                      </div>
                    </dl>
                  </section>
                </v-tabs-window-item>

                <v-tabs-window-item value="wishes" class="material-detail-window-item">
                  <GrossanlassArticleWishesPanel
                    v-if="wishArticle"
                    :department-id="departmentId"
                    :article="wishArticle"
                    :wishes="articleWishes"
                    @book="openBookFromWish"
                    @saved="onWishSaved"
                  />
                </v-tabs-window-item>

                <v-tabs-window-item value="stock" class="material-detail-window-item">
                  <GrossanlassArticleChargesPanel
                    :charges="stemCharges"
                    @release="onChargeRelease"
                  />
                  <section v-if="vehicleCharge" class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.zusage.sectionService') }}</h2>
                    <p class="window-intro">{{ t('grossanlass.materials.zusage.serviceHint') }}</p>
                    <ul v-if="vehicleArticle?.services.length" class="service-list">
                      <li v-for="service in vehicleArticle.services" :key="service.id">
                        <strong>{{ parkLabel(service.kind, service.label) }}</strong>
                        <span>{{ formatIso(service.fromIso) }} – {{ formatIso(service.toIso) }}</span>
                        <span>{{ service.who }}</span>
                      </li>
                    </ul>
                    <p v-else class="user-readonly-empty">{{ t('grossanlass.materials.zusage.noServices') }}</p>
                    <div class="service-add">
                      <ESelect
                        v-model="newServiceKind"
                        :items="serviceItems"
                        item-title="title"
                        item-value="value"
                        :label="t('grossanlass.materials.zusage.fieldService')"
                        hide-details
                      />
                      <EDateField
                        v-model="newServiceDate"
                        :department-id="departmentId"
                        :label="t('grossanlass.materials.zusage.fieldServiceDay')"
                        allow-past
                      />
                      <div class="service-times">
                        <ETimeField v-model="newServiceFrom" :label="t('grossanlass.materials.zusage.fieldFrom')" />
                        <ETimeField v-model="newServiceTo" :label="t('grossanlass.materials.zusage.fieldTo')" />
                      </div>
                      <EButton variant="secondary" size="small" :disabled="!canAddService" @click="addService">
                        {{ t('grossanlass.materials.zusage.addService') }}
                      </EButton>
                    </div>
                  </section>
                </v-tabs-window-item>

                <v-tabs-window-item value="usage" class="material-detail-window-item">
                  <GrossanlassArticleEinsatzPanel
                    v-if="zusage"
                    :article="zusage"
                    :rows="articleEinsaetze"
                    @book="openBook"
                    @book-wish="openBookFromWish"
                  />
                </v-tabs-window-item>
              </v-tabs-window>
            </main>
          </div>
        </div>
      </template>

      <div v-else class="detail-content">
        <div class="content-layout">
          <EEmptyState
            variant="search"
            :title="t('grossanlass.materials.detailNotFoundTitle')"
            :description="t('grossanlass.materials.detailNotFoundDescription')"
          />
        </div>
      </div>
    </div>

    <GrossanlassEinsatzBookPreviewDialog
      v-model="bookOpen"
      v-model:draft="bookDraft"
      mode="einsatz"
      :wishes="articleWishes"
      :free-picks="articleFreePicks"
      :rows="articleEinsaetze"
      :resources="articleResources"
      :chauffeurs="chauffeurs"
      :places="places"
      :preset-object-id="itemId"
      :preset-wish-id="bookWishId"
      @confirm="onBookConfirm"
      @place-created="uebersicht.addPlace"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDateField, ESelect, ETimeField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useToast } from '@/composables/useToast'
import { updateGrossanlassCommitment, type GrossanlassCommitment } from '@/api/grossanlassCommitments'
import {
  listGrossanlassProcurementLines,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import {
  findPreviewRowById,
  type GaMaterialsTabId,
} from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { commitmentsOnStem } from '@/views/grossanlass/gaCharge'
import {
  articleToResource,
  combineIso,
  formatGaIsoLabel,
  parkServiceLabel,
  type GaParkServiceKind,
  type GaZusageArticle,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  resourceToPickTemplate,
  type GaPreviewWishTemplate,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import GrossanlassArticleWishesPanel from '@/views/grossanlass/GrossanlassArticleWishesPanel.vue'
import GrossanlassArticleChargesPanel from '@/views/grossanlass/GrossanlassArticleChargesPanel.vue'
import GrossanlassArticleEinsatzPanel from '@/views/grossanlass/GrossanlassArticleEinsatzPanel.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import '@/styles/materials-view.css'

defineOptions({ name: 'GrossanlassMaterialsPreviewDetail' })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t, locale } = useI18n()
const toast = useToast()
const { loading, rows, articles, commitments, upsert, load: loadCatalog } = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

const TAB_IDS = ['data', 'wishes', 'stock', 'usage'] as const
const activeTab = ref(String(route.query.tab || 'data'))
const bookOpen = ref(false)
const bookDraft = ref<GaBookPreviewDraft | null>(null)
const bookWishId = ref<string | null>(null)
const newServiceKind = ref<GaParkServiceKind>('clean')
const newServiceDate = ref('')
const newServiceFrom = ref('06:00')
const newServiceTo = ref('08:00')
const saving = ref(false)
const procurementLine = ref<GrossanlassProcurementLine | null>(null)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const itemId = computed(() => String(route.params.itemId || ''))

const item = computed(() => findPreviewRowById(rows.value, itemId.value))
const zusage = computed(() => articles.value.find((article) => article.id === itemId.value))
const currentCommitment = computed(() =>
  commitments.value.find((entry) => entry.id === itemId.value) ?? null,
)
const stemCharges = computed(() => {
  const current = currentCommitment.value
  if (!current) return []
  return commitmentsOnStem(commitments.value, current)
})
const stemIds = computed(() => new Set(stemCharges.value.map((row) => row.id)))
const vehicleCharge = computed(() => stemCharges.value.find((row) => row.family === 'vehicle') ?? null)
const vehicleArticle = computed(() =>
  vehicleCharge.value
    ? articles.value.find((article) => article.id === vehicleCharge.value!.id) ?? null
    : null,
)

const wishArticle = computed<GaZusageArticle | undefined>(() => {
  const base = zusage.value
  if (!base) return undefined
  if (stemCharges.value.length <= 1) return base
  return { ...base, presentFromIso: '', presentToIso: '', source: '' }
})

const articleWishes = computed<GaPreviewWishTemplate[]>(() => {
  const stock = stemCharges.value.reduce((sum, row) => sum + (row.quantity || 0), 0)
  const objectId = itemId.value
  const fromLine = procurementLine.value
  if (fromLine?.source_wishes?.length) {
    return fromLine.source_wishes.map((wish) => poolWishToTemplate(wish, objectId, item.value?.name || fromLine.label, stock))
  }
  const lineId = currentCommitment.value?.item_details?.from_line_id || zusage.value?.fromLineId || ''
  return uebersicht.wishTemplates.value.filter((wish) =>
    stemIds.value.has(wish.objectId) || (lineId !== '' && wish.id === lineId),
  )
})

const articleEinsaetze = computed(() =>
  uebersicht.bookingRows().filter((row) => stemIds.value.has(row.objectId)),
)

const articleResources = computed(() =>
  stemCharges.value
    .map((row) => articles.value.find((article) => article.id === row.id))
    .filter((article): article is GaZusageArticle => Boolean(article))
    .map((article) => articleToResource(article)),
)

const articleFreePicks = computed(() =>
  articleResources.value.map((resource) => {
    const template = resourceToPickTemplate(resource, (key, values) =>
      values ? String(t(key, values)) : String(t(key)),
    )
    const article = zusage.value
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
const places = computed(() => uebersicht.data.value?.places ?? [])

watch(
  () => String(route.query.tab || ''),
  (tab) => {
    if ((TAB_IDS as readonly string[]).includes(tab) && activeTab.value !== tab) {
      activeTab.value = tab
    }
  },
)

watch(activeTab, (tab) => {
  if (String(route.query.tab || '') === tab) return
  void router.replace({ query: { ...route.query, tab } })
})

watch(
  () => [departmentId.value, currentCommitment.value?.item_details?.from_line_id || ''] as const,
  async ([dept, lineId]) => {
    if (!dept || !lineId) {
      procurementLine.value = null
      return
    }
    try {
      const lines = await listGrossanlassProcurementLines(dept)
      procurementLine.value = lines.find((line) => line.id === lineId) ?? null
    } catch {
      procurementLine.value = null
    }
  },
  { immediate: true },
)

const serviceItems = computed(() => [
  { title: t('grossanlass.materials.zusage.service.clean'), value: 'clean' },
  { title: t('grossanlass.materials.zusage.service.grease'), value: 'grease' },
  { title: t('grossanlass.materials.zusage.service.other'), value: 'other' },
])

const canAddService = computed(() =>
  Boolean(vehicleArticle.value && newServiceDate.value && newServiceFrom.value && newServiceTo.value && !saving.value),
)

function poolWishToTemplate(
  wish: GrossanlassProcurementPoolWish,
  objectId: string,
  objectName: string,
  stock: number,
): GaPreviewWishTemplate {
  return {
    id: wish.id,
    label: wish.label,
    objectId,
    objectName,
    kind: 'quantity',
    qty: wish.quantity,
    stock,
    fromIso: wish.valid_from,
    toIso: wish.valid_to,
    fromLabel: formatGaIsoLabel(wish.valid_from, locale.value),
    toLabel: formatGaIsoLabel(wish.valid_to, locale.value),
    ressort: wish.group_name,
    who: wish.created_by_name,
    hasConflict: false,
    groupId: wish.group_id,
    roundId: wish.round_id,
    lastStage: wish.last_stage || undefined,
    createdAt: wish.created_at,
    enoughOnHand: Boolean(wish.enough_on_hand),
    enoughOnHandSource: wish.enough_on_hand_source ?? null,
    enoughOnHandDetail: wish.enough_on_hand_detail ?? null,
    enoughOnHandRefId: wish.enough_on_hand_ref_id ?? null,
  }
}

function formatIso(iso: string): string {
  if (!iso) return '—'
  return formatGaIsoLabel(iso, locale.value)
}

function parkLabel(kind: GaParkServiceKind, custom?: string): string {
  return parkServiceLabel(kind, (key) => String(t(key)), custom)
}

async function onChargeRelease(row: GrossanlassCommitment, released: boolean) {
  if (!departmentId.value || saving.value) return
  saving.value = true
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, row.id, { released })
    upsert(updated)
    toast.success(t('grossanlass.beschaffung.zusagen.releasedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    saving.value = false
  }
}

async function addService() {
  const current = vehicleArticle.value
  if (!current || !canAddService.value || !departmentId.value) return
  saving.value = true
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, current.id, {
      services: [
        ...current.services.map((service) => ({
          id: service.id,
          kind: service.kind,
          fromIso: service.fromIso,
          toIso: service.toIso,
          who: service.who,
          label: service.label ?? null,
        })),
        {
          kind: newServiceKind.value,
          fromIso: combineIso(newServiceDate.value, newServiceFrom.value),
          toIso: combineIso(newServiceDate.value, newServiceTo.value),
          who: '',
        },
      ],
    })
    upsert(updated)
    toast.success(t('grossanlass.beschaffung.zusagen.releasedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    saving.value = false
  }
}

function listPath(tab: string): string {
  const id = departmentId.value
  if (tab === 'eigen' || tab === 'leihweise') {
    return `/${id}/materialien/${tab}`
  }
  if (tab === 'fahrzeuge') return `/${id}/materialien/eigen?family=vehicle`
  if (tab === 'wareneingang') return `/${id}/material-uebersicht/wareneingang`
  return `/${id}/material-uebersicht`
}

function goBack() {
  const from = String(route.query.from || '') as GaMaterialsTabId | ''
  void router.push(listPath(from))
}

function openBook(wishId?: string) {
  bookWishId.value = typeof wishId === 'string' && wishId ? wishId : null
  bookDraft.value = null
  bookOpen.value = true
}

function openBookFromWish(wishId?: string) {
  openBook(wishId || articleWishes.value[0]?.id)
}

async function onWishSaved() {
  await Promise.all([uebersicht.load(), loadCatalog()])
}

async function onBookConfirm(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create({
      kind: 'einsatz',
      commitment_id: current.objectId || itemId.value,
      wish_line_id: current.fromWish ? current.id : null,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      delivery: current.delivery || 'pickup',
      destination_place_id: current.destinationPlaceId || null,
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

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped>
.ga-preview-detail__banner {
  flex-shrink: 0;
  padding: 12px 24px 0;
}

.ga-preview-detail__banner :deep(.ga-preview-banner) {
  margin-bottom: 12px;
}

.user-readonly-fields {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.user-readonly-row {
  display: grid;
  grid-template-columns: minmax(8rem, 11rem) 1fr;
  gap: 0.75rem 1rem;
  align-items: baseline;
}

.user-readonly-row dt {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #6b7280;
}

.user-readonly-row dd {
  margin: 0;
  font-size: 0.9375rem;
  color: #111827;
}

.user-readonly-empty {
  margin: 0;
  color: #6b7280;
  font-size: 0.9375rem;
}

.window-intro,
.window-fein {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: #64748b;
}

.window-switch {
  margin: 12px 0;
}

.service-list {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: grid;
  gap: 8px;
}

.service-list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 0.82rem;
}

.service-add {
  display: grid;
  gap: 12px;
}

.service-times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
</style>
