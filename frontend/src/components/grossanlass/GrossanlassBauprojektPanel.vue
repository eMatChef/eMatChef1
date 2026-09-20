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
            <EDateRangeField
              v-if="briefing.can_edit"
              :department-id="departmentId"
              :label="t('grossanlass.planung.ressorts.windowLabel')"
              v-model:start="windowStart"
              v-model:end="windowEnd"
              allow-past
              show-presets
              preset-mode="fixed-periods"
            />
            <p v-else-if="windowText" class="muted">{{ windowText }}</p>
            <EButton
              v-if="briefing.can_edit"
              variant="secondary"
              size="small"
              class="ga-bauprojekt-panel__save-window"
              :loading="savingWindow"
              :disabled="savingWindow"
              @click="saveWindow"
            >
              {{ t('grossanlass.planung.ressorts.windowSave') }}
            </EButton>
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
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="muted">{{ t('grossanlass.planung.ressorts.materialHintBoth') }}</p>
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
                  :class="{ 'is-on': materialMode === 'direct' }"
                  @click="materialMode = 'direct'"
                >
                  {{ t('grossanlass.planung.ressorts.materialModeFix') }}
                </button>
              </div>
              <div class="ga-bauprojekt-panel__add-row">
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
              <div class="ga-bauprojekt-panel__grow">
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
                :disabled="!String(materialLabel || '').trim()"
                :loading="savingMaterial"
              >
                {{ materialMode === 'direct'
                  ? t('grossanlass.planung.ressorts.materialAddFix')
                  : t('grossanlass.planung.ressorts.materialAdd') }}
              </EButton>
            </form>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="einsaetze">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.einsaetzeHeading') }}
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
import { EButton, EDateRangeField, ETextField, ETextarea } from '@/components/form/base'
import GrossanlassPlacePreviewMap from '@/components/grossanlass/GrossanlassPlacePreviewMap.vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { gaCanSeeAnlassOverview, GA_UEBERSICHT_ROUTE_ROLES } from '@/utils/grossanlassAccess'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import { gaMapOverlayBounds } from '@/utils/grossanlassGaMap'
import { resolveGaPlacePublicUrl } from '@/utils/publicQrUrl'
import {
  createGrossanlassPlace,
  updateGrossanlassPlace,
  type GaMap,
} from '@/api/grossanlassLogistics'
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

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const briefing = ref<GaBauprojektBriefing | null>(null)
const windowStart = ref('')
const windowEnd = ref('')
const description = ref('')
const savingWindow = ref(false)
const taskTitle = ref('')
const savingTask = ref(false)
const materialLabel = ref('')
const materialQty = ref('1')
const materialNotes = ref('')
const materialMode = ref<'wish' | 'direct'>('wish')
const savingMaterial = ref(false)
const savingPlace = ref(false)
const openSections = ref<string[]>(['window', 'place', 'tasks', 'material'])
const placeMapRef = ref<{ refreshSize: () => void } | null>(null)

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
    mode: 'direct' as const,
    modeLabel: t('grossanlass.planung.ressorts.materialModeFix'),
  }))
  return [...wishes, ...direct]
})

async function load() {
  if (!props.departmentId || !props.groupId) return
  loading.value = true
  error.value = null
  try {
    briefing.value = await getGrossanlassBauprojekt(props.departmentId, props.groupId)
    briefing.value.einsaetze ??= []
    briefing.value.direct_material ??= []
    windowStart.value = briefing.value.window_start || ''
    windowEnd.value = briefing.value.window_end || ''
    description.value = briefing.value.description || briefing.value.group?.description || ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
  } finally {
    loading.value = false
  }
}

async function saveProjectMeta(successKey: string) {
  if (!briefing.value?.can_edit || savingWindow.value) return
  savingWindow.value = true
  try {
    briefing.value = await patchGrossanlassBauprojektWindow(props.departmentId, props.groupId, {
      window_start: windowStart.value || null,
      window_end: windowEnd.value || null,
      description: description.value.trim() || null,
    })
    toast.success(t(successKey))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingWindow.value = false
  }
}

async function saveWindow() {
  await saveProjectMeta('grossanlass.planung.ressorts.windowSaved')
}

async function saveDescription() {
  await saveProjectMeta('grossanlass.planung.ressorts.descriptionSaved')
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
  const label = String(materialLabel.value || '').trim()
  if (!label || savingMaterial.value) return
  savingMaterial.value = true
  try {
    await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
      label,
      quantity: Math.max(1, Number(materialQty.value) || 1),
      notes: String(materialNotes.value || '').trim() || null,
      mode: materialMode.value,
    })
    await load()
    materialLabel.value = ''
    materialQty.value = '1'
    materialNotes.value = ''
    toast.success(
      materialMode.value === 'direct'
        ? t('grossanlass.planung.ressorts.materialAddedFix')
        : t('grossanlass.planung.ressorts.materialAdded'),
    )
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

watch(() => props.groupId, () => { void load() })
onMounted(() => { void load() })
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
.ga-bauprojekt-panel__qty { flex: 0 0 110px; max-width: 110px; width: 110px; }
.ga-bauprojekt-panel__link {
  display: inline-block;
  margin: 10px 12px 0 0;
  font-size: 0.88rem;
}
.ga-bauprojekt-panel__beschrieb { white-space: pre-wrap; margin: 0; }
.ga-bauprojekt-panel__modes { display: flex; gap: 4px; width: 100%; }
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
