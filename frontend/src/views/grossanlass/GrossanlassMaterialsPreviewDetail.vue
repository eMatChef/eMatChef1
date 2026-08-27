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
          <span class="material-code">{{ item.barcode }}</span>
          <h1>{{ item.name }}</h1>
          <span class="combo-type-badge" :class="lifecycleBadgeClass(item.lifecycle)">
            {{ lifecycleLabel(item.lifecycle) }}
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
          <v-tab value="window">{{ t('grossanlass.materials.detailTabWindow') }}</v-tab>
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
                      <div class="user-readonly-row">
                        <dt>{{ t('components.materialDetail.labelEan') }}</dt>
                        <dd>{{ item.barcode }}</dd>
                      </div>
                      <div v-if="item.category_name" class="user-readonly-row">
                        <dt>{{ t('components.materialDetail.sidebarCategory') }}</dt>
                        <dd>{{ item.category_name }}</dd>
                      </div>
                      <div class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.detailFieldLifecycle') }}</dt>
                        <dd>{{ lifecycleLabel(item.lifecycle) }}</dd>
                      </div>
                      <div v-if="item.location" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colLocation') }}</dt>
                        <dd>{{ item.location }}</dd>
                      </div>
                      <div v-if="item.source" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colSource') }}</dt>
                        <dd>{{ item.source }}</dd>
                      </div>
                      <div v-if="item.validFrom" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colFrom') }}</dt>
                        <dd>{{ item.validFrom }}</dd>
                      </div>
                      <div v-if="item.validTo" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colTo') }}</dt>
                        <dd>{{ item.validTo }}</dd>
                      </div>
                      <div v-if="item.plate" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colPlate') }}</dt>
                        <dd>{{ item.plate }}</dd>
                      </div>
                      <div v-if="item.vehicleStatus" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.colStatus') }}</dt>
                        <dd>{{ item.vehicleStatus }}</dd>
                      </div>
                      <div v-if="item.pack_unit" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.detailFieldUnit') }}</dt>
                        <dd>{{ item.pack_unit }}</dd>
                      </div>
                      <div v-if="details.weight" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.fieldWeight') }}</dt>
                        <dd>{{ details.weight }}</dd>
                      </div>
                      <div v-if="details.notes" class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.fieldNotes') }}</dt>
                        <dd>{{ details.notes }}</dd>
                      </div>
                    </dl>
                    <ul v-if="details.parts?.length" class="service-list">
                      <li v-for="(part, index) in details.parts" :key="`${part.name}-${index}`">
                        <strong>{{ part.name }}</strong>
                        <span>{{ t('grossanlass.materials.zusage.qtyShort', { n: part.qty }) }}</span>
                      </li>
                    </ul>
                  </section>
                </v-tabs-window-item>

                <v-tabs-window-item value="window" class="material-detail-window-item">
                  <section class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.detailTabWindow') }}</h2>
                    <p class="window-intro">{{ t('grossanlass.materials.zusage.windowIntro') }}</p>
                    <dl v-if="zusage && zusage.presentFromIso" class="user-readonly-fields">
                      <div class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.fieldPartner') }}</dt>
                        <dd>{{ zusage.source }}</dd>
                      </div>
                      <div class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.sectionPresent') }}</dt>
                        <dd>{{ formatIso(zusage.presentFromIso) }} – {{ formatIso(zusage.presentToIso) }}</dd>
                      </div>
                      <div class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.sectionHandover') }}</dt>
                        <dd>{{ formatIso(zusage.handoverFromIso) }} – {{ formatIso(zusage.handoverToIso) }}</dd>
                      </div>
                      <div class="user-readonly-row">
                        <dt>{{ t('grossanlass.materials.zusage.sectionReturn') }}</dt>
                        <dd>{{ formatIso(zusage.returnFromIso) }} – {{ formatIso(zusage.returnToIso) }}</dd>
                      </div>
                    </dl>
                    <p v-else class="user-readonly-empty">{{ t('grossanlass.materials.zusage.noWindow') }}</p>
                    <ESwitch
                      v-if="zusage"
                      v-model="releasedModel"
                      :label="t('grossanlass.materials.zusage.fieldRelease')"
                      :hint="t('grossanlass.materials.zusage.fieldReleaseHint')"
                      persistent-hint
                      class="window-switch"
                    />
                    <p v-if="zusage?.feinWish" class="window-fein">
                      {{ t('grossanlass.planung.feinPartner.wishWindow', {
                        wish: zusage.feinWish.label,
                        from: formatIso(zusage.feinWish.fromIso),
                        to: formatIso(zusage.feinWish.toIso),
                      }) }}
                    </p>
                  </section>

                  <section v-if="zusage?.family === 'vehicle'" class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.zusage.sectionService') }}</h2>
                    <p class="window-intro">{{ t('grossanlass.materials.zusage.serviceHint') }}</p>
                    <ul v-if="zusage.services.length" class="service-list">
                      <li v-for="service in zusage.services" :key="service.id">
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

                <v-tabs-window-item value="stock" class="material-detail-window-item">
                  <section class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.detailTabStock') }}</h2>
                    <div class="stock-summary">
                      <div class="stock-stat warehouse">
                        <span class="stock-number">{{ formatQty(item.total_stock) }}</span>
                        <span class="stock-label">{{ t('materialsView.colTotal') }}</span>
                      </div>
                      <div v-if="item.issued_out > 0" class="stock-stat issued">
                        <span class="stock-number">{{ formatQty(item.issued_out) }}</span>
                        <span class="stock-label">{{ t('components.materialDetail.stockLabelOut') }}</span>
                      </div>
                      <div class="stock-stat available">
                        <span class="stock-number">{{ formatQty(item.available) }}</span>
                        <span class="stock-label">{{ t('components.materialDetail.stockLabelAvailable') }}</span>
                      </div>
                    </div>
                  </section>
                </v-tabs-window-item>

                <v-tabs-window-item value="usage" class="material-detail-window-item">
                  <section class="section-card">
                    <h2 class="section-title">{{ t('grossanlass.materials.detailTabUsage') }}</h2>
                    <p class="user-readonly-empty">{{ t('grossanlass.materials.detailUsageEmpty') }}</p>
                  </section>
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
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDateField, ESelect, ESwitch, ETimeField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { useToast } from '@/composables/useToast'
import { updateGrossanlassCommitment } from '@/api/grossanlassCommitments'
import {
  findPreviewRowById,
  type GaLifecycle,
  type GaMaterialsTabId,
} from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import { commitmentDetails } from '@/views/grossanlass/grossanlassCommitmentMap'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import {
  combineIso,
  formatGaIsoLabel,
  parkServiceLabel,
  type GaParkServiceKind,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import '@/styles/materials-view.css'

defineOptions({ name: 'GrossanlassMaterialsPreviewDetail' })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t, locale } = useI18n()
const toast = useToast()
const { loading, rows, articles, commitments, upsert } = useGaCommitmentCatalog()

const activeTab = ref('data')
const newServiceKind = ref<GaParkServiceKind>('clean')
const newServiceDate = ref('')
const newServiceFrom = ref('06:00')
const newServiceTo = ref('08:00')
const saving = ref(false)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const itemId = computed(() => String(route.params.itemId || ''))

const item = computed(() => findPreviewRowById(rows.value, itemId.value))
const zusage = computed(() => articles.value.find((article) => article.id === itemId.value))
const details = computed(() => {
  const row = commitments.value.find((entry) => entry.id === itemId.value)
  return row ? commitmentDetails(row) : {}
})

const releasedModel = computed({
  get: () => zusage.value?.released ?? false,
  set: (value: boolean | null) => {
    void saveReleased(Boolean(value))
  },
})

const serviceItems = computed(() => [
  { title: t('grossanlass.materials.zusage.service.clean'), value: 'clean' },
  { title: t('grossanlass.materials.zusage.service.grease'), value: 'grease' },
  { title: t('grossanlass.materials.zusage.service.other'), value: 'other' },
])

const canAddService = computed(() =>
  Boolean(zusage.value && newServiceDate.value && newServiceFrom.value && newServiceTo.value && !saving.value),
)

function formatIso(iso: string): string {
  if (!iso) return '—'
  return formatGaIsoLabel(iso, locale.value)
}

function parkLabel(kind: GaParkServiceKind, custom?: string): string {
  return parkServiceLabel(kind, (key) => String(t(key)), custom)
}

async function saveReleased(released: boolean) {
  const id = zusage.value?.id
  if (!id || !departmentId.value || saving.value) return
  saving.value = true
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, id, { released })
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
  const current = zusage.value
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

function lifecycleLabel(kind: GaLifecycle): string {
  return t(`grossanlass.materials.lifecycle.${kind}`)
}

function lifecycleBadgeClass(kind: GaLifecycle): string {
  if (kind === 'loan' || kind === 'cut_consumable') return 'virtual_combo'
  return 'physical_combo'
}

function formatQty(qty: number): string {
  const unit = item.value?.pack_unit
  return unit ? `${qty} ${unit}` : String(qty)
}

function listPath(tab: string): string {
  const id = departmentId.value
  if (tab === 'eigen' || tab === 'leihweise' || tab === 'fahrzeuge') {
    return `/${id}/materialien/${tab}`
  }
  return `/${id}/material-uebersicht`
}

function goBack() {
  const from = String(route.query.from || '') as GaMaterialsTabId | ''
  void router.push(listPath(from))
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
