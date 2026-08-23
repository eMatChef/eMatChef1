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
      <div class="ga-preview-detail__banner">
        <GrossanlassPreviewBanner />
      </div>

      <template v-if="item">
        <v-tabs
          v-model="activeTab"
          class="material-detail-tabs"
          align-tabs="start"
          color="primary"
          show-arrows
        >
          <v-tab value="data">{{ t('grossanlass.materials.detailTabData') }}</v-tab>
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
                    </dl>
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
                    <div v-if="item.components?.length" class="combo-components-container">
                      <table class="combo-sub-table">
                        <thead>
                          <tr>
                            <th>{{ t('grossanlass.materialUebersicht.colWho') }}</th>
                            <th>{{ t('grossanlass.materialUebersicht.colRessort') }}</th>
                            <th>{{ t('grossanlass.materialUebersicht.colWhen') }}</th>
                            <th>{{ t('materialsView.subColQty') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="comp in item.components" :key="comp.id">
                            <td class="comp-name">{{ comp.name }}</td>
                            <td>
                              <span class="assignment-badge" :class="comp.assignment === 'fixed' ? 'fix' : 'bulk'">
                                {{ comp.assignment_label }}
                              </span>
                            </td>
                            <td>
                              <span v-if="comp.serial" class="serial-code">{{ comp.serial }}</span>
                              <span v-else class="no-serial">–</span>
                            </td>
                            <td>{{ formatQty(comp.qty) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p v-else class="user-readonly-empty">–</p>
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
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import {
  createGrossanlassMaterialsPreview,
  findPreviewRowById,
  type GaLifecycle,
  type GaMaterialsTabId,
} from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import '@/styles/materials-view.css'

defineOptions({ name: 'GrossanlassMaterialsPreviewDetail' })

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const activeTab = ref('data')

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const catalog = computed(() => createGrossanlassMaterialsPreview((key) => t(key)))

const item = computed(() => {
  const id = String(route.params.itemId || '')
  return findPreviewRowById(catalog.value, id)
})

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
</style>
