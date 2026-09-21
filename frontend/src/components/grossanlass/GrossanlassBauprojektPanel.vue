<template>
  <div class="ga-bauprojekt-panel">
    <p v-if="error" class="ga-bauprojekt-panel__error">{{ error }}</p>
    <p v-else-if="loading" class="muted">{{ t('common.loading') }}</p>
    <template v-else-if="briefing">
      <v-expansion-panels v-model="openSections" multiple class="e-accordions ga-bauprojekt-panel__accordions">
        <v-expansion-panel value="window">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.windowLabel') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <GaBuildMetaFields
              v-if="briefing.can_edit"
              :department-id="departmentId"
              autosave
              v-model:start="windowStart"
              v-model:end="windowEnd"
              v-model:status="buildStatus"
              :window-label="t('grossanlass.planung.ressorts.windowLabel')"
              :window-hint="t('grossanlass.planung.ressorts.windowHint')"
              :window-baseline="windowBaseline"
              :status-baseline="buildStatusBaseline"
              hint-class="muted"
              :save-window="saveWindowAutosave"
              :save-status="saveStatusAutosave"
            />
            <template v-else>
              <p v-if="windowText" class="muted">{{ windowText }}</p>
              <p v-if="buildStatusText" class="muted">{{ buildStatusText }}</p>
            </template>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="description">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.descriptionHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ETextarea
              v-if="briefing.can_edit"
              v-model="description"
              :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
              rows="4"
              hide-details="auto"
            />
            <p v-else-if="description.trim()" class="ga-bauprojekt-panel__beschrieb">{{ description }}</p>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.descriptionEmpty') }}</p>
            <EButton
              v-if="briefing.can_edit"
              variant="secondary"
              size="small"
              class="ga-bauprojekt-panel__save-window"
              :loading="savingWindow"
              :disabled="savingWindow"
              @click="saveDescription"
            >
              {{ t('grossanlass.planung.ressorts.descriptionSave') }}
            </EButton>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="place">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.placeHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text eager>
            <p v-if="briefing.place">
              {{ briefing.place.public_code }} · {{ briefing.place.name }}
            </p>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
            <p v-if="briefing.can_edit" class="muted">{{ t('grossanlass.planung.ressorts.mapHint') }}</p>
            <GrossanlassPlacePreviewMap
              ref="placeMapRef"
              :latitude="briefing.place?.latitude ?? null"
              :longitude="briefing.place?.longitude ?? null"
              :label="briefing.place?.name || briefing.group?.name || ''"
              :overlay="placeOverlay"
              :active="placeSectionOpen"
              :editable="briefing.can_edit"
              height="280px"
              @pick="onPlacePick"
            />
            <a
              v-if="publicPlaceUrl"
              class="ga-bauprojekt-panel__link"
              :href="publicPlaceUrl"
              target="_blank"
              rel="noopener"
            >
              {{ t('grossanlass.planung.ressorts.placePublicView') }}
            </a>
            <RouterLink v-if="canSeeStandorte" class="ga-bauprojekt-panel__link" :to="standorteTo">
              {{ t('grossanlass.planung.ressorts.placeOnMap') }}
            </RouterLink>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="tasks">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.tasksHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ul v-if="briefing.tasks.length" class="ga-bauprojekt-panel__list">
              <li v-for="task in briefing.tasks" :key="task.id">
                <span>{{ task.title }}</span>
                <button
                  v-if="briefing.can_edit"
                  type="button"
                  class="action-btn action-btn-danger"
                  :title="t('common.delete')"
                  @click="removeTask(task.id)"
                >
                  <v-icon icon="mdi-close" size="14" />
                </button>
              </li>
            </ul>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.tasksEmpty') }}</p>
            <form v-if="briefing.can_edit" class="ga-bauprojekt-panel__add" @submit.prevent="addTask">
              <div class="ga-bauprojekt-panel__grow">
                <ETextField
                  v-model="taskTitle"
                  :placeholder="t('grossanlass.planung.ressorts.taskPlaceholder')"
                  hide-details="auto"
                />
              </div>
              <EButton variant="primary" size="small" type="submit" :disabled="!String(taskTitle || '').trim()" :loading="savingTask">
                {{ t('grossanlass.planung.ressorts.taskAdd') }}
              </EButton>
            </form>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="material">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.materialHeading') }}
            <span v-if="materialTitleMeta" class="ga-bauprojekt-panel__title-meta">{{ materialTitleMeta }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="muted">{{ t('grossanlass.planung.ressorts.materialHintModes') }}</p>
            <ul v-if="materialRows.length" class="ga-bauprojekt-panel__list">
              <li v-for="line in materialRows" :key="line.id">
                <span>
                  {{ line.quantity }}× {{ line.label }}
                  <span class="ga-bauprojekt-panel__chip" :class="line.mode">{{ line.modeLabel }}</span>
                  <span v-if="line.notes" class="muted"> · {{ line.notes }}</span>
                </span>
                <EButton
                  v-if="briefing.can_edit && line.mode === 'wish'"
                  variant="secondary"
                  size="small"
                  @click="bookEinsatz(line.id)"
                >
                  {{ t('grossanlass.planung.ressorts.einsaetzeBook') }}
                </EButton>
              </li>
            </ul>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.materialEmpty') }}</p>
            <form v-if="briefing.can_edit" class="ga-bauprojekt-panel__add" @submit.prevent="addMaterial">
              <div class="ga-bauprojekt-panel__modes" role="group">
                <button
                  type="button"
                  class="ga-bauprojekt-panel__mode"
                  :class="{ 'is-on': materialMode === 'wish' }"
                  @click="materialMode = 'wish'"
                >
                  {{ t('grossanlass.planung.ressorts.materialModeWish') }}
                </button>
                <button
                  type="button"
                  class="ga-bauprojekt-panel__mode"
                  :class="{ 'is-on': materialMode === 'fix' }"
                  @click="materialMode = 'fix'"
                >
                  {{ t('grossanlass.planung.ressorts.materialModeFix') }}
                </button>
                <button
                  type="button"
                  class="ga-bauprojekt-panel__mode"
                  :class="{ 'is-on': materialMode === 'self' }"
                  @click="materialMode = 'self'"
                >
                  {{ t('grossanlass.planung.ressorts.materialModeSelf') }}
                </button>
              </div>
              <p v-if="materialMode === 'fix' && !hasProjectWindow" class="muted">
                {{ t('grossanlass.planung.ressorts.materialNeedWindow') }}
              </p>
              <p v-else-if="materialMode === 'fix' && !fixCatalogLoading && !stockRows.length" class="muted">
                {{ t('grossanlass.planung.ressorts.materialFixEmpty') }}
              </p>
              <MaterialLookupInput
                v-if="materialMode === 'fix' && hasProjectWindow"
                v-model="fixSearchQuery"
                class="ga-bauprojekt-panel__lookup"
                :fetcher="fixMaterialFetcher"
                :min-chars="1"
                :debounce-ms="220"
                :max-suggestions="25"
                :placeholder="t('grossanlass.planung.ressorts.materialSearchPlaceholder')"
                :loading-text="t('components.materialLookup.loading')"
                :empty-text="t('components.materialLookup.empty')"
                :get-result-key="(item) => item.id"
                :get-result-label="(item) => item.name"
                :get-result-secondary="fixMaterialSecondary"
                :teleport-dropdown="true"
                :dropdown-min-width="320"
                :dropdown-z-index="4000"
                dropdown-max-height="min(360px, 45vh)"
                @select="onFixMaterialSelect"
              />
              <ESelect
                v-if="materialMode === 'self'"
                v-model="organizerGroupId"
                :items="organizerItems"
                :label="t('grossanlass.planung.ressorts.materialOrganizer')"
                hide-details
              />
              <div v-if="materialMode !== 'fix'" class="ga-bauprojekt-panel__add-row">
                <div class="ga-bauprojekt-panel__grow">
                  <ETextField
                    v-model="materialLabel"
                    :placeholder="t('grossanlass.planung.ressorts.materialLabel')"
                    hide-details="auto"
                  />
                </div>
                <div class="ga-bauprojekt-panel__qty">
                  <ETextField
                    v-model="materialQty"
                    type="number"
                    min="1"
                    :placeholder="t('grossanlass.planung.ressorts.materialQty')"
                    hide-details="auto"
                  />
                </div>
              </div>
              <div v-else class="ga-bauprojekt-panel__add-row">
                <div class="ga-bauprojekt-panel__qty">
                  <ETextField
                    v-model="materialQty"
                    type="number"
                    min="1"
                    :max="fixSelected?.available ?? undefined"
                    :placeholder="t('grossanlass.planung.ressorts.materialQty')"
                    hide-details="auto"
                  />
                </div>
              </div>
              <div v-if="materialMode !== 'fix'" class="ga-bauprojekt-panel__grow">
                <ETextarea
                  v-model="materialNotes"
                  :placeholder="t('grossanlass.planung.ressorts.materialNotes')"
                  rows="3"
                  auto-grow
                  hide-details="auto"
                />
              </div>
              <EButton
                variant="primary"
                size="small"
                type="submit"
                :disabled="!canSubmitMaterial"
                :loading="savingMaterial"
              >
                {{ materialSubmitLabel }}
              </EButton>
            </form>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="einsaetze">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.einsaetzeHeading') }}
            <span v-if="einsatzTitleMeta" class="ga-bauprojekt-panel__title-meta">{{ einsatzTitleMeta }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="muted">{{ t('grossanlass.planung.ressorts.einsaetzeHint') }}</p>
            <ul v-if="briefing.einsaetze?.length" class="ga-bauprojekt-panel__list">
              <li v-for="row in briefing.einsaetze" :key="row.id">
                <span>
                  {{ row.qty }}× {{ row.object_name }}
                  · {{ formatEinsatzWindow(row.from, row.to) }}
                </span>
              </li>
            </ul>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.einsaetzeEmpty') }}</p>
            <EButton
              v-if="briefing.can_edit"
              variant="primary"
              size="small"
              :disabled="!briefing.group"
              @click="bookEinsatz()"
            >
              {{ t('grossanlass.planung.ressorts.einsaetzeBookProject') }}
            </EButton>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <div class="ga-bauprojekt-panel__footer">
        <a
          v-if="publicPlaceUrl"
          class="ga-bauprojekt-panel__link"
          :href="publicPlaceUrl"
          target="_blank"
          rel="noopener"
        >
          {{ t('grossanlass.planung.ressorts.placePublicView') }}
        </a>
        <EButton
          v-if="canPrintHelper"
          variant="secondary"
          size="small"
          :disabled="!briefing.group"
          @click="openPrint"
        >
          {{ t('grossanlass.planung.ressorts.printHelper') }}
        </EButton>
        <p v-else class="muted">{{ t('grossanlass.planung.ressorts.printOnlyWhenFixed') }}</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { EButton, ESelect, ETextField, ETextarea } from '@/components/form/base'
import GaBuildMetaFields from '@/components/grossanlass/GaBuildMetaFields.vue'
import GrossanlassPlacePreviewMap from '@/components/grossanlass/GrossanlassPlacePreviewMap.vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { gaCanSeeAnlassOverview, GA_UEBERSICHT_ROUTE_ROLES } from '@/utils/grossanlassAccess'
import {
  formatBauprojektWindow,
  packBauprojektWindow,
  unpackBauprojektWindow,
} from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
} from '@/utils/grossanlassBuildStatus'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import { gaMapOverlayBounds } from '@/utils/grossanlassGaMap'
import { resolveGaPlacePublicUrl } from '@/utils/publicQrUrl'
import {
  createGrossanlassPlace,
  updateGrossanlassPlace,
  type GaMap,
} from '@/api/grossanlassLogistics'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { getGrossanlassCommitments } from '@/api/grossanlassCommitments'
import { createGrossanlassEinsatz, getGrossanlassSubmitBoard } from '@/api/grossanlassUebersicht'
import {
  addGrossanlassBauprojektMaterial,
  createGrossanlassBauprojektTask,
  deleteGrossanlassBauprojektTask,
  getGrossanlassBauprojekt,
  patchGrossanlassBauprojektWindow,
  type GaBauprojektBriefing,
} from '@/api/grossanlassBauprojekt'

const props = defineProps<{
  departmentId: string
  groupId: string
}>()
const emit = defineEmits<{
  'meta-saved': [group: NonNullable<GaBauprojektBriefing['group']>]
}>()

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const briefing = ref<GaBauprojektBriefing | null>(null)
const windowStart = ref('')
const windowEnd = ref('')
const buildStatus = ref('')
const windowBaseline = ref('|')
const buildStatusBaseline = ref('')
const description = ref('')
const buildStatusText = computed(() => {
  if (!briefing.value) return ''
  return t(gaBuildStatusI18nKey(resolveBuildStatus({
    build_status: briefing.value.build_status ?? briefing.value.group?.build_status,
    window_start: briefing.value.window_start,
    window_end: briefing.value.window_end,
  })))
})
const savingWindow = ref(false)
const taskTitle = ref('')
const savingTask = ref(false)
const materialLabel = ref('')
const materialQty = ref('1')
const materialNotes = ref('')
const materialMode = ref<'wish' | 'fix' | 'self'>('wish')
const savingMaterial = ref(false)
const savingPlace = ref(false)
const openSections = ref<string[]>(['material', 'einsaetze'])
const placeMapRef = ref<{ refreshSize: () => void } | null>(null)
const fixMaterialId = ref<string | null>(null)
const fixSearchQuery = ref('')
const fixCatalogLoading = ref(false)
const stockRows = ref<Array<{ id: string; name: string; quantity: number }>>([])
const organizerGroupId = ref('')
const organizerGroups = ref<GrossanlassGroup[]>([])

const canSeeStandorte = computed(() => gaCanSeeAnlassOverview(authStore.currentDepartmentRole))
const anlassStatus = computed(() => {
  const row = authStore.departments.find((d) => d.department_id === props.departmentId)
  return row?.department?.grossanlass_config?.status || 'draft'
})
const canPrintHelper = computed(() => anlassStatus.value !== 'draft')
const windowText = computed(() =>
  formatBauprojektWindow(briefing.value?.window_start, briefing.value?.window_end),
)
const standorteTo = computed(() => `/${props.departmentId}/einstellungen/standorte`)
const publicPlaceUrl = computed(() =>
  resolveGaPlacePublicUrl(briefing.value?.place?.qr_url, briefing.value?.place?.public_code),
)
const placeOverlay = computed(() => {
  const map = briefing.value?.map as GaMap | null | undefined
  const bounds = gaMapOverlayBounds(map)
  if (!map?.image_url || !bounds) return null
  return { url: map.image_url, ...bounds }
})
const placeSectionOpen = computed(() => openSections.value.includes('place'))
const materialRows = computed(() => {
  const wishes = (briefing.value?.material ?? []).map((line) => ({
    id: line.id,
    label: line.label,
    quantity: line.quantity,
    notes: line.notes || '',
    mode: 'wish' as const,
    modeLabel: t('grossanlass.planung.ressorts.materialModeWish'),
  }))
  const direct = (briefing.value?.direct_material ?? []).map((line) => ({
    id: line.id,
    label: line.label,
    quantity: line.quantity,
    notes: line.notes || '',
    mode: 'self' as const,
    modeLabel: t('grossanlass.planung.ressorts.materialModeSelfShort'),
  }))
  return [...wishes, ...direct]
})

const hasProjectWindow = computed(() => Boolean(windowStart.value && windowEnd.value))

const projectFromIso = computed(() => (windowStart.value ? `${windowStart.value}T00:00:00` : ''))
const projectToIso = computed(() => (windowEnd.value ? `${windowEnd.value}T23:59:59` : ''))

function rangesOverlap(aFrom: string, aTo: string, bFrom: string, bTo: string): boolean {
  return aFrom < bTo && bFrom < aTo
}

function availableForStock(row: { id: string; quantity: number }): number {
  const stock = Math.max(0, Number(row.quantity) || 0)
  if (!projectFromIso.value || !projectToIso.value) return stock
  const used = (briefing.value?.einsaetze ?? []).reduce((sum, einsatz) => {
    if (einsatz.object_id !== row.id) return sum
    if (!rangesOverlap(einsatz.from, einsatz.to, projectFromIso.value, projectToIso.value)) return sum
    return sum + Math.max(0, Number(einsatz.qty) || 0)
  }, 0)
  return Math.max(0, stock - used)
}

type FixStockHit = { id: string; name: string; quantity: number; available: number }

function toFixHit(row: { id: string; name: string; quantity: number }): FixStockHit {
  return {
    id: row.id,
    name: row.name,
    quantity: row.quantity,
    available: availableForStock(row),
  }
}

async function fixMaterialFetcher(query: string): Promise<FixStockHit[]> {
  if (!stockRows.value.length) await loadFixCatalog()
  const needle = query.trim().toLowerCase()
  return stockRows.value
    .map(toFixHit)
    .filter((row) => !needle || row.name.toLowerCase().includes(needle))
    .sort((a, b) => a.name.localeCompare(b.name, 'de'))
}

function fixMaterialSecondary(item: FixStockHit): string {
  return item.available > 0
    ? t('grossanlass.planung.ressorts.materialAvailable', { n: item.available })
    : t('grossanlass.planung.ressorts.materialUnavailable')
}

function onFixMaterialSelect(item: FixStockHit) {
  fixMaterialId.value = item.id
  fixSearchQuery.value = item.name
}

watch(fixSearchQuery, (value) => {
  if (!fixMaterialId.value) return
  const selected = stockRows.value.find((row) => row.id === fixMaterialId.value)
  if (selected && value.trim() !== selected.name) {
    fixMaterialId.value = null
  }
})

const fixSelected = computed(() => {
  const row = stockRows.value.find((item) => item.id === fixMaterialId.value)
  if (!row) return null
  return toFixHit(row)
})

const organizerItems = computed(() => {
  const items = organizerGroups.value.map((group) => ({
    title: group.name,
    value: group.id,
  }))
  if (briefing.value?.group && !items.some((row) => row.value === briefing.value?.group?.id)) {
    items.unshift({ title: briefing.value.group.name, value: briefing.value.group.id })
  }
  return items
})

const canSubmitMaterial = computed(() => {
  if (savingMaterial.value) return false
  if (materialMode.value === 'fix') {
    return Boolean(fixSelected.value && fixSelected.value.available > 0 && hasProjectWindow.value)
  }
  if (materialMode.value === 'self') {
    return Boolean(String(materialLabel.value || '').trim() && organizerGroupId.value)
  }
  return Boolean(String(materialLabel.value || '').trim())
})

const materialSubmitLabel = computed(() => {
  if (materialMode.value === 'fix') return t('grossanlass.planung.ressorts.materialAddFix')
  if (materialMode.value === 'self') return t('grossanlass.planung.ressorts.materialAddSelf')
  return t('grossanlass.planung.ressorts.materialAdd')
})

const materialTitleMeta = computed(() => {
  const wishCount = briefing.value?.material?.length ?? 0
  const selfCount = briefing.value?.direct_material?.length ?? 0
  const parts: string[] = []
  if (wishCount) parts.push(t('grossanlass.meinRessort.wishCount', { n: wishCount }))
  if (selfCount) parts.push(t('grossanlass.meinRessort.selfCount', { n: selfCount }))
  return parts.length ? `· ${parts.join(' · ')}` : ''
})

const einsatzTitleMeta = computed(() => {
  const count = briefing.value?.einsaetze?.length ?? 0
  if (!count) return ''
  return `· ${t('grossanlass.meinRessort.einsatzCount', { n: count })}`
})

async function load(opts?: { silent?: boolean }) {
  if (!props.departmentId || !props.groupId) return
  if (!opts?.silent) {
    loading.value = true
    error.value = null
  }
  try {
    briefing.value = await getGrossanlassBauprojekt(props.departmentId, props.groupId)
    briefing.value.einsaetze ??= []
    briefing.value.direct_material ??= []
    applyMetaFromBriefing(briefing.value)
    if (!organizerGroupId.value) {
      organizerGroupId.value = briefing.value.group?.parent_id || briefing.value.group?.id || ''
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    if (!opts?.silent) {
      error.value = err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
    } else {
      toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad'))
    }
  } finally {
    if (!opts?.silent) loading.value = false
  }
}

function dialogScrollTop(): { el: HTMLElement; top: number } | null {
  const el = document.querySelector<HTMLElement>('.v-dialog--active .e-dialog__body')
  if (!el) return null
  return { el, top: el.scrollTop }
}

async function reloadKeepScroll() {
  const scroll = dialogScrollTop()
  await load({ silent: true })
  await nextTick()
  if (scroll) scroll.el.scrollTop = scroll.top
}

async function loadFixCatalog() {
  if (!props.departmentId || fixCatalogLoading.value) return
  fixCatalogLoading.value = true
  try {
    const pool = await getGrossanlassCommitments(props.departmentId).catch(() => [])
    const fromPool = (Array.isArray(pool) ? pool : []).map((row) => ({
      id: row.id,
      name: row.name,
      quantity: Number(row.quantity) || 0,
    }))
    if (fromPool.length) {
      stockRows.value = fromPool
      return
    }
    const board = await getGrossanlassSubmitBoard(props.departmentId)
    stockRows.value = (board.objects ?? []).map((row) => ({
      id: row.id,
      name: row.name,
      quantity: Number(row.qty) || 0,
    }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad'))
    stockRows.value = []
  } finally {
    fixCatalogLoading.value = false
  }
}

async function loadOrganizerGroups() {
  if (!props.departmentId || organizerGroups.value.length) return
  try {
    organizerGroups.value = await getGrossanlassGroups(props.departmentId)
    if (!organizerGroupId.value) {
      organizerGroupId.value = briefing.value?.group?.parent_id
        || briefing.value?.group?.id
        || organizerGroups.value[0]?.id
        || ''
    }
  } catch {
    organizerGroups.value = []
  }
}

function applyMetaFromBriefing(row: GaBauprojektBriefing) {
  windowStart.value = row.window_start || ''
  windowEnd.value = row.window_end || ''
  buildStatus.value = row.build_status || row.group?.build_status || ''
  description.value = row.description || row.group?.description || ''
  windowBaseline.value = packBauprojektWindow(windowStart.value, windowEnd.value)
  buildStatusBaseline.value = buildStatus.value
}

function syncBriefing(next: GaBauprojektBriefing) {
  briefing.value = next
  windowBaseline.value = packBauprojektWindow(next.window_start, next.window_end)
  buildStatusBaseline.value = next.build_status || next.group?.build_status || ''
  if (next.group) emit('meta-saved', next.group)
}

async function patchProjectMeta(extra: { description?: string | null } = {}) {
  const next = await patchGrossanlassBauprojektWindow(props.departmentId, props.groupId, {
    window_start: windowStart.value || null,
    window_end: windowEnd.value || null,
    build_status: buildStatus.value || null,
    ...extra,
  })
  syncBriefing(next)
  return next
}

async function saveWindowAutosave(value: AutoSaveFieldValue) {
  const next = unpackBauprojektWindow(value)
  windowStart.value = next.start
  windowEnd.value = next.end
  await patchProjectMeta()
}

async function saveStatusAutosave(value: AutoSaveFieldValue) {
  buildStatus.value = value == null ? '' : String(value)
  await patchProjectMeta()
}

async function saveDescription() {
  if (!briefing.value?.can_edit || savingWindow.value) return
  savingWindow.value = true
  try {
    await patchProjectMeta({ description: description.value.trim() || null })
    toast.success(t('grossanlass.planung.ressorts.descriptionSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingWindow.value = false
  }
}

async function onPlacePick(lat: number, lng: number) {
  if (!briefing.value?.can_edit || savingPlace.value) return
  savingPlace.value = true
  try {
    if (briefing.value.place) {
      briefing.value.place = await updateGrossanlassPlace(props.departmentId, briefing.value.place.id, {
        latitude: lat,
        longitude: lng,
      })
    } else {
      briefing.value.place = await createGrossanlassPlace(props.departmentId, {
        name: briefing.value.group?.name || t('grossanlass.planung.ressorts.kindBauprojekt'),
        group_id: props.groupId,
        kind: 'bauprojekt',
        latitude: lat,
        longitude: lng,
      })
    }
    toast.success(t('grossanlass.planung.ressorts.placeSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingPlace.value = false
  }
}

async function addTask() {
  const title = String(taskTitle.value || '').trim()
  if (!title || savingTask.value) return
  savingTask.value = true
  try {
    const task = await createGrossanlassBauprojektTask(props.departmentId, props.groupId, title)
    if (briefing.value) briefing.value.tasks = [...briefing.value.tasks, task]
    taskTitle.value = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingTask.value = false
  }
}

async function removeTask(taskId: string) {
  try {
    await deleteGrossanlassBauprojektTask(props.departmentId, props.groupId, taskId)
    if (briefing.value) {
      briefing.value.tasks = briefing.value.tasks.filter((row) => row.id !== taskId)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  }
}

async function addMaterial() {
  if (!canSubmitMaterial.value || savingMaterial.value) return
  savingMaterial.value = true
  try {
    if (materialMode.value === 'fix') {
      const pick = fixSelected.value
      if (!pick || !projectFromIso.value || !projectToIso.value) return
      const qty = Math.min(
        Math.max(1, Number(materialQty.value) || 1),
        pick.available,
      )
      await createGrossanlassEinsatz(props.departmentId, {
        kind: 'einsatz',
        commitment_id: pick.id,
        qty,
        from: projectFromIso.value,
        to: projectToIso.value,
        who: pick.name,
        group_id: props.groupId,
        delivery: 'pickup',
        destination_place_id: briefing.value?.place?.id || null,
      })
      fixMaterialId.value = null
      fixSearchQuery.value = ''
      materialQty.value = '1'
      toast.success(t('grossanlass.planung.ressorts.materialAddedEinsatz'))
    } else {
      const label = String(materialLabel.value || '').trim()
      const organizer = organizerItems.value.find((row) => row.value === organizerGroupId.value)
      const notes = String(materialNotes.value || '').trim()
      const organizerNote = materialMode.value === 'self' && organizer
        ? t('grossanlass.planung.ressorts.materialOrganizerNote', { name: organizer.title })
        : ''
      await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
        label,
        quantity: Math.max(1, Number(materialQty.value) || 1),
        notes: [organizerNote, notes].filter(Boolean).join(' — ') || null,
        mode: materialMode.value === 'self' ? 'direct' : 'wish',
      })
      materialLabel.value = ''
      materialQty.value = '1'
      materialNotes.value = ''
      toast.success(
        materialMode.value === 'self'
          ? t('grossanlass.planung.ressorts.materialAddedSelf')
          : t('grossanlass.planung.ressorts.materialAdded'),
      )
    }
    await reloadKeepScroll()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingMaterial.value = false
  }
}

function formatEinsatzWindow(from: string, to: string): string {
  return formatBauprojektWindow(from.slice(0, 10), to.slice(0, 10))
}

function bookEinsatz(wishId?: string) {
  const query: Record<string, string> = { group: props.groupId }
  const placeId = briefing.value?.place?.id
  if (placeId) query.place = placeId
  if (wishId) query.wish = wishId
  const role = authStore.currentDepartmentRole || ''
  const name = (GA_UEBERSICHT_ROUTE_ROLES as readonly string[]).includes(role)
    ? 'GrossanlassMaterialUebersichtEinsaetze'
    : 'GrossanlassMeinRessort'
  void router.push({ name, params: { departmentId: props.departmentId }, query })
}

function openPrint() {
  if (!canPrintHelper.value) return
  void router.push({
    name: 'GrossanlassHelferauftragPrint',
    params: { departmentId: props.departmentId, groupId: props.groupId },
  })
}

watch(placeSectionOpen, (open) => {
  if (!open) return
  void nextTick(() => {
    placeMapRef.value?.refreshSize()
    setTimeout(() => placeMapRef.value?.refreshSize(), 320)
  })
})

watch(materialMode, (mode) => {
  if (mode === 'fix') void loadFixCatalog()
  if (mode === 'self') void loadOrganizerGroups()
})

watch(() => props.groupId, () => { void load() })
onMounted(() => {
  void load()
  void loadOrganizerGroups()
  void loadFixCatalog()
})
</script>

<style scoped>
.ga-bauprojekt-panel__error { color: #b91c1c; }
.muted { color: #64748b; font-size: 0.88rem; }
.ga-bauprojekt-panel__save-window { margin: 8px 0 8px; }
.ga-bauprojekt-panel__accordions { margin-bottom: 16px; }
.ga-bauprojekt-panel__list { list-style: none; padding: 0; margin: 0 0 8px; }
.ga-bauprojekt-panel__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px solid #eef2f6;
}
.ga-bauprojekt-panel__add {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}
.ga-bauprojekt-panel__add-row {
  display: flex;
  gap: 8px;
  align-items: flex-end;
  width: 100%;
}
.ga-bauprojekt-panel__grow {
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
}
.ga-bauprojekt-panel__grow :deep(.e-form-field),
.ga-bauprojekt-panel__grow :deep(.v-input),
.ga-bauprojekt-panel__qty :deep(.e-form-field),
.ga-bauprojekt-panel__qty :deep(.v-input) {
  width: 100%;
}
.ga-bauprojekt-panel__lookup {
  width: 100%;
}
.ga-bauprojekt-panel__lookup :deep(.material-lookup-input) {
  width: 100%;
}
.ga-bauprojekt-panel__link {
  display: inline-block;
  margin: 10px 12px 0 0;
  font-size: 0.88rem;
}
.ga-bauprojekt-panel__beschrieb { white-space: pre-wrap; margin: 0; }
.ga-bauprojekt-panel__title-meta {
  margin-left: 8px;
  font-weight: 500;
  color: #64748b;
  font-size: 0.82rem;
}
.ga-bauprojekt-panel__modes { display: flex; gap: 4px; width: 100%; flex-wrap: wrap; }
.ga-bauprojekt-panel__mode {
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.82rem;
  cursor: pointer;
}
.ga-bauprojekt-panel__mode.is-on { background: #0f766e; color: #fff; border-color: #0f766e; }
.ga-bauprojekt-panel__chip {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.72rem;
  padding: 1px 6px;
  border-radius: 999px;
  background: #e2e8f0;
}
.ga-bauprojekt-panel__chip.direct { background: #dbeafe; }
.ga-bauprojekt-panel__chip.wish { background: #dcfce7; }
.ga-bauprojekt-panel__chip.self { background: #fef3c7; }
.ga-bauprojekt-panel__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}
.action-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
}
.action-btn-danger { color: #b91c1c; }
</style>
