<template>
  <EDialog
    v-model="open"
    :title="dialogTitle"
    :max-width="DIALOG_WIDTH"
    :width="DIALOG_WIDTH"
    :retain-focus="false"
    :before-close="confirmClose"
    scrollable
  >
    <v-alert v-if="group?.archived" type="warning" variant="tonal" density="compact" class="mb-2" data-testid="order-archived-banner">
      <strong>{{ t('grossanlass.aufgaben.archive.banner') }}</strong>
      <span v-if="group.archive_reason"> — {{ group.archive_reason }}</span>
    </v-alert>

    <ESubTabs v-model="tab" class="order-dialog__tabs" :items="subTabs" :aria-label="dialogTitle" />

    <div v-if="canEdit && basicsMounted" v-show="tab === 'basics'" class="order-dialog__pane">
      <div class="order-dialog__types" role="radiogroup" :aria-label="t('grossanlass.auftraege.create.type')">
        <button
          v-for="type in TYPES"
          :key="type"
          type="button"
          role="radio"
          :aria-checked="form.type === type"
          class="order-type"
          :class="{ 'order-type--on': form.type === type }"
          @click="form.type = type"
        >
          <v-icon :icon="TYPE_ICON[type]" size="26" />
          <span>
            <strong>{{ t(`grossanlass.auftraege.type.${type}`) }}</strong>
            <small>{{ t(`grossanlass.auftraege.create.typeText.${type}`) }}</small>
          </span>
        </button>
      </div>
      <ETextField v-model="form.name" :label="t('grossanlass.auftraege.f.title')" hide-details="auto" />
      <GrossanlassParentTreeSelect
        v-model="parentModel"
        :groups="groups"
        :selectable-ids="selectableParentIds"
        :exclude-id="group?.id ?? null"
        :label="t('grossanlass.planung.ressorts.parentLabel')"
      />
      <GaBuildMetaFields
        :department-id="departmentId"
        :autosave="!!group"
        v-model:start="form.window_start"
        v-model:end="form.window_end"
        v-model:status="form.build_status"
        :window-label="t('grossanlass.planung.ressorts.windowLabel')"
        :window-baseline="windowBaseline"
        :status-baseline="statusBaseline"
        :node-type="nodeType"
        :procurement-progress="group?.procurement_progress"
        :allow-manual="canSetStatus"
        :save-window="saveWindowAutosave"
        :save-status="saveStatusAutosave"
      />
      <p v-if="statusSuggestion" class="order-dialog__suggest" data-testid="order-status-suggestion">
        <v-icon icon="mdi-lightbulb-outline" size="16" />
        <span>
          {{ t('grossanlass.planung.ressorts.statusSuggest.text', {
            status: t(gaBuildStatusI18nKey(statusSuggestion.status)),
            reason: t(`grossanlass.planung.ressorts.statusSuggest.reason.${statusSuggestion.reason}`),
          }) }}
        </span>
        <EButton size="small" variant="secondary" @click="applyStatusSuggestion">
          {{ t('grossanlass.planung.ressorts.statusSuggest.apply') }}
        </EButton>
      </p>
      <ETextarea
        v-model="form.description"
        :label="t('grossanlass.planung.ressorts.descriptionHeading')"
        :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
        rows="4"
        hide-details="auto"
      />
      <ESwitch
        v-if="form.type === 'order'"
        v-model="form.include_on_map"
        :label="t('grossanlass.planung.auftraege.placeToggle')"
        hide-details="auto"
      />
      <GrossanlassGroupMapEditor
        v-if="hasPlace"
        ref="mapRef"
        v-model:existing-place-id="existingPlaceId"
        v-model:place-kind="placeKind"
        v-model:place-name="placeName"
        :department-id="departmentId"
        :venue-address-id="venueAddressId"
        :name="form.name"
        :editing-place-id="group?.place?.id ?? null"
        :group-id="group?.id ?? null"
        @places-changed="emit('changed')"
      />
    </div>

    <div v-if="tasksMounted && group" v-show="tab === 'tasks'" class="order-dialog__pane">
      <GrossanlassBauprojektPanel
        ref="panelRef"
        :department-id="departmentId"
        :group-id="group.id"
        scope="tasks-material"
        :live-window="{ start: form.window_start, end: form.window_end }"
        @meta-saved="onMetaSaved"
      />
      <GaOrderAcceptance
        v-if="isGrossanlassLeafNodeType(group.node_type)"
        :department-id="departmentId"
        :group-id="group.id"
        :groups="groups"
        :refresh-key="tab"
        @changed="emit('changed')"
      />
      <GaGroupDependencies
        v-if="isGrossanlassLeafNodeType(group.node_type)"
        :department-id="departmentId"
        :group-id="group.id"
        :groups="groups"
        :can-edit="canEdit"
        @changed="emit('changed')"
      />
    </div>

    <template #actions>
      <EButton v-if="group && showShare && canShareGroup(group)" variant="secondary" size="small" @click="emit('share', group)">
        {{ t('grossanlass.planung.ressorts.shareWith') }}
      </EButton>
      <EButton v-if="group && isOrderNode && canDeleteGroup(group) && !group.archived" variant="secondary" size="small" data-testid="order-archive" @click="archiveOpen = true">
        {{ t('grossanlass.aufgaben.archive.action') }}
      </EButton>
      <EButton v-if="group && group.archived && canDeleteGroup(group)" variant="secondary" size="small" :loading="archiving" data-testid="order-restore" @click="restore">
        {{ t('grossanlass.aufgaben.archive.restore') }}
      </EButton>
      <EButton v-if="group && canDeleteGroup(group) && !group.has_history && !group.archived" variant="danger" size="small" :loading="deleting" @click="remove">
        {{ t('common.delete') }}
      </EButton>
      <EButton variant="secondary" size="small" @click="requestClose">
        {{ canEdit ? t('common.cancel') : t('settings.groups.close') }}
      </EButton>
      <EButton v-if="canEdit" variant="primary" size="small" :disabled="errors.length > 0 || saving" :loading="saving" @click="save">
        {{ t('common.save') }}
      </EButton>
    </template>

    <EDialog v-model="archiveOpen" :title="t('grossanlass.aufgaben.archive.title')" max-width="520" :retain-focus="false">
      <p>{{ t('grossanlass.aufgaben.archive.explain') }}</p>
      <p v-if="group?.has_history" class="order-dialog__suggest">{{ t('grossanlass.aufgaben.archive.hasHistory') }}</p>
      <ETextField v-model="archiveReason" :label="t('grossanlass.aufgaben.archive.reason')" hide-details="auto" data-testid="order-archive-reason" />
      <p v-if="archiveError" class="order-dialog__suggest" role="alert">{{ archiveError }}</p>
      <template #actions>
        <EButton variant="secondary" @click="archiveOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!archiveReason.trim() || archiving" :loading="archiving" data-testid="order-archive-confirm" @click="archive">
          {{ t('grossanlass.aufgaben.archive.confirm') }}
        </EButton>
      </template>
    </EDialog>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESubTabs, ESwitch, ETextField, ETextarea } from '@/components/form/base'
import GaBuildMetaFields from '@/components/grossanlass/GaBuildMetaFields.vue'
import GaOrderAcceptance from '@/components/grossanlass/GaOrderAcceptance.vue'
import GaGroupDependencies from '@/components/grossanlass/GaGroupDependencies.vue'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import GrossanlassGroupMapEditor from '@/components/grossanlass/GrossanlassGroupMapEditor.vue'
import GrossanlassParentTreeSelect from '@/components/grossanlass/GrossanlassParentTreeSelect.vue'
import {
  createGrossanlassGroup,
  archiveGrossanlassGroup,
  deleteGrossanlassGroup,
  restoreGrossanlassGroup,
  getGrossanlassOrderCards,
  updateGrossanlassGroup,
  type GrossanlassGroup,
  type GrossanlassOrderCard,
} from '@/api/grossanlassGroups'
import { updateGrossanlassPlace } from '@/api/grossanlassLogistics'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import { formatWallClock, useBusinessClockStore } from '@/stores/businessClock'
import { closeAllExclusiveOverlays } from '@/composables/useExclusiveOverlay'
import { gaBuildStatusI18nKey, isGaBuildStatus, resolveBuildStatus, suggestBuildStatus } from '@/utils/grossanlassBuildStatus'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { gaCanSetBuildStatus } from '@/utils/grossanlassAccess'
import { packBauprojektWindow, unpackBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import { isGrossanlassLeafNodeType } from '@/utils/grossanlassGroupNode'
import {
  emptyOrderForm,
  orderFormFromGroup,
  orderHasPlace,
  orderSavePayload,
  validateOrderForm,
  type GaOrderForm,
} from '@/utils/grossanlassOrderForm'
import { TYPE_ICON, type GaOrderType } from '@/views/grossanlass/auftraege/gaAuftraegeModel'

/**
 * Zentraler Dialog für Auftrag und Bauauftrag (Anlegen und Bearbeiten).
 * «Mein Ressort» und «Planung» binden ihn identisch ein und unterscheiden sich nur in Navigation,
 * Filter und Berechtigungen; diese kommen aus `useGrossanlassRessortScope` und der Rolle.
 * Der Dialog hält keine eigene Datenhaltung: nach jedem Speichern meldet er `changed`/`saved`,
 * die Seite lädt ihre Gruppen neu.
 */
const props = withDefaults(defineProps<{
  modelValue: boolean
  departmentId: string
  /** Alle Gruppen der Struktur (Hierarchie, Rechte). */
  groups: GrossanlassGroup[]
  /** Zu bearbeitende Gruppe; `null` = neu anlegen. */
  initialGroup?: GrossanlassGroup | null
  initialType?: GaOrderType
  initialParentId?: string
  initialName?: string
  initialTab?: 'basics' | 'tasks'
  /** «Teilen» nur anbieten, wenn die Seite den Teilen-Dialog hat. */
  showShare?: boolean
}>(), { initialGroup: null, initialType: 'order', initialParentId: '', initialName: '', initialTab: 'basics', showShare: false })

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  /** Gruppe angelegt oder gespeichert. */
  saved: [group: GrossanlassGroup, created: boolean]
  /** Teilspeicherung (Autosave, Standorte, Aufgaben/Material): Seite soll soft nachladen. */
  changed: []
  deleted: [id: string]
  share: [group: GrossanlassGroup]
}>()

const DIALOG_WIDTH = 1400
const TYPES: GaOrderType[] = ['order', 'build']

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const authStore = useAuthStore()
const { canEditGroup, canDeleteGroup, canShareGroup, canCreateChild } = useGrossanlassRessortScope(
  toRef(props, 'groups'),
)

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

/** Angelegte Gruppe wechselt im offenen Dialog in den Bearbeitungsmodus (Aufgaben & Material folgen). */
const current = ref<GrossanlassGroup | null>(null)
const group = computed(() => current.value)
const form = ref<GaOrderForm>(emptyOrderForm())
const tab = ref<'basics' | 'tasks'>('basics')
const subTabs = computed(() => [
  ...(canEdit.value ? [{ value: 'basics', label: t('grossanlass.planung.ressorts.tabBasics'), icon: 'mdi-pencil-outline', testid: 'order-tab-basics' }] : []),
  { value: 'tasks', label: t('grossanlass.planung.ressorts.tabTasks'), icon: 'mdi-clipboard-list-outline', disabled: !group.value, testid: 'order-tab-tasks' },
])
const basicsMounted = ref(false)
const tasksMounted = ref(false)
const saving = ref(false)
const deleting = ref(false)
const archiveOpen = ref(false)
const archiveReason = ref('')
const archiving = ref(false)
const archiveError = ref('')
const baseline = ref('')
const windowBaseline = ref('|')
const statusBaseline = ref('')
const venueAddressId = ref<string | null>(null)
const mapRef = ref<InstanceType<typeof GrossanlassGroupMapEditor> | null>(null)
const panelRef = ref<{ confirmClose: () => Promise<boolean> } | null>(null)
const existingPlaceId = ref<string | null>(null)
const placeKind = ref<'area' | 'bauprojekt'>('bauprojekt')
const placeName = ref('')

const canEdit = computed(() => !group.value || (canEditGroup(group.value) && !group.value.archived))
/** Archivieren gibt es nur für Bauaufträge (Bauprojekt/Auftrag), nicht für Strukturgruppen. */
const isOrderNode = computed(() => isGrossanlassLeafNodeType(group.value?.node_type))
const errors = computed(() => validateOrderForm(form.value))
const hasPlace = computed(() => orderHasPlace(form.value))
const nodeType = computed(() => (form.value.type === 'build' ? 'bauprojekt' : 'auftrag'))
const canSetStatus = computed(() => {
  const target = group.value ?? props.groups.find((row) => row.id === form.value.parent_id) ?? null
  return gaCanSetBuildStatus(authStore.currentDepartmentRole, authStore.userId, target, props.groups)
})
const dialogTitle = computed(() =>
  group.value
    ? t(`grossanlass.planung.auftraege.title.${form.value.type}`, { name: group.value.name })
    : t('grossanlass.auftraege.create.title'),
)

const selectableParentIds = computed(() => {
  const ids = props.groups
    .filter((row) => !isGrossanlassLeafNodeType(row.node_type) && canCreateChild(row))
    .map((row) => row.id)
  const parentId = group.value?.parent_id
  if (parentId && !ids.includes(parentId)) ids.push(parentId)
  return ids
})
const parentModel = computed<string | null>({
  get: () => form.value.parent_id || null,
  set: (value) => { form.value.parent_id = value ?? '' },
})

/** Autosave-Felder (Zeitfenster, Status) sind beim Bearbeiten bereits gespeichert und zählen nicht als ungespeichert. */
function snapshot(): string {
  const { window_start, window_end, build_status, ...rest } = form.value
  const autosaved = group.value ? {} : { window_start, window_end, build_status }
  return JSON.stringify([rest, autosaved, placeName.value, existingPlaceId.value, placeKind.value])
}
const basicsDirty = computed(() => {
  if (!canEdit.value || !basicsMounted.value) return false
  return snapshot() !== baseline.value && (!!group.value || !!form.value.name.trim())
})

async function confirmClose(): Promise<boolean> {
  if (panelRef.value && !(await panelRef.value.confirmClose())) return false
  if (!basicsDirty.value) return true
  return confirm.confirm({
    title: t('grossanlass.planung.ressorts.unsavedCloseTitle'),
    message: t('grossanlass.planung.ressorts.unsavedCloseMessage'),
    confirmText: t('settings.groups.close'),
    cancelText: t('layout.confirm.back'),
    variant: 'warning',
  })
}
async function requestClose() {
  if (await confirmClose()) open.value = false
}

async function loadVenueAddress() {
  try {
    venueAddressId.value = (await getGrossanlassPlanung(props.departmentId)).config.venue_address_id || null
  } catch {
    venueAddressId.value = null
  }
}

async function markBaseline() {
  await nextTick()
  baseline.value = snapshot()
}

function resetPlaceState() {
  existingPlaceId.value = null
  placeKind.value = 'bauprojekt'
  placeName.value = form.value.name
}

async function init() {
  current.value = props.initialGroup
  const source = props.initialGroup
  form.value = source
    ? orderFormFromGroup(source)
    : { ...emptyOrderForm(props.initialType, props.initialParentId || selectableParentIds.value[0] || ''), name: props.initialName }
  windowBaseline.value = source ? packBauprojektWindow(source.window_start, source.window_end) : '|'
  statusBaseline.value = source?.build_status || ''
  resetPlaceState()
  const wantsTasks = !!source && (props.initialTab === 'tasks' || !canEdit.value)
  tab.value = wantsTasks ? 'tasks' : 'basics'
  // Aufgaben, Helferbedarf, Material und Standorte laden im Hintergrund parallel zu den Grunddaten;
  // der Reiterwechsel schaltet nur noch um (v-show), ohne neue Anfragen.
  tasksMounted.value = !!source
  // Die Karte wird erst im sichtbaren Reiter gemountet (Leaflet misst die Grösse beim Start).
  basicsMounted.value = tab.value === 'basics'
  void loadVenueAddress().then(syncPlacement)
  void loadCard()
  await markBaseline()
}

async function syncPlacement() {
  if (!open.value || !venueAddressId.value || !hasPlace.value) return
  await nextTick()
  await mapRef.value?.syncPlacement()
}

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) void init()
  else {
    mapRef.value?.clearInlineDraft()
    basicsMounted.value = false
    tasksMounted.value = false
  }
}, { immediate: true })

watch(tab, async (value) => {
  // Offene Dropdowns/Kalender gehören zum verlassenen Reiter; Formularwerte liegen im Dialog und bleiben erhalten.
  closeAllExclusiveOverlays()
  if (value === 'tasks') {
    tasksMounted.value = true
    return
  }
  if (basicsMounted.value || !canEdit.value) return
  basicsMounted.value = true
  await nextTick()
  await syncPlacement()
  await markBaseline()
})

// Typ-/Standortwechsel setzt den Ortszustand zurück, ohne vorhandene Orte zu überschreiben.
watch(hasPlace, async (on) => {
  if (!on) {
    mapRef.value?.clearInlineDraft()
    return
  }
  if (!group.value?.place) {
    resetPlaceState()
    await syncPlacement()
  }
})

const clock = useBusinessClockStore()
const businessToday = computed(() => formatWallClock(clock.displayNow).slice(0, 10))
const card = ref<GrossanlassOrderCard | null>(null)
async function loadCard() {
  card.value = null
  const id = props.initialGroup?.id
  if (!id) return
  const cards = await getGrossanlassOrderCards(props.departmentId).catch(() => [])
  if (props.initialGroup?.id === id) card.value = cards.find((row) => row.group_id === id) ?? null
}
const statusSuggestion = computed(() => {
  if (!group.value || !canSetStatus.value) return null
  const current = isGaBuildStatus(form.value.build_status)
    ? form.value.build_status
    : resolveBuildStatus({
      node_type: nodeType.value,
      window_start: form.value.window_start,
      window_end: form.value.window_end,
      procurement_progress: group.value.procurement_progress,
    }, businessToday.value)
  return suggestBuildStatus({
    nodeType: nodeType.value,
    current,
    windowStart: form.value.window_start,
    windowEnd: form.value.window_end,
    procurementProgress: group.value.procurement_progress,
    card: card.value,
    today: businessToday.value,
  })
})
async function applyStatusSuggestion() {
  if (!statusSuggestion.value) return
  const status = statusSuggestion.value.status
  try {
    await saveStatusAutosave(status)
  } catch {
    toast.error(t('grossanlass.planung.ressorts.errorSave'))
  }
}

async function patchAutosaved(extra: Partial<GaOrderForm>) {
  if (!group.value) return
  Object.assign(form.value, extra)
  const saved = await updateGrossanlassGroup(props.departmentId, group.value.id, {
    window_start: form.value.window_start || null,
    window_end: form.value.window_end || null,
    build_status: form.value.build_status || null,
  })
  current.value = { ...group.value, ...saved }
  windowBaseline.value = packBauprojektWindow(saved.window_start, saved.window_end)
  statusBaseline.value = saved.build_status || ''
  emit('changed')
}
async function saveWindowAutosave(value: AutoSaveFieldValue) {
  const next = unpackBauprojektWindow(value)
  await patchAutosaved({ window_start: next.start, window_end: next.end })
}
async function saveStatusAutosave(value: AutoSaveFieldValue) {
  await patchAutosaved({ build_status: value == null ? '' : String(value) })
}

function onMetaSaved(meta: { id: string; window_start?: string | null; window_end?: string | null; build_status?: string | null }) {
  if (current.value?.id === meta.id) current.value = { ...current.value, ...meta }
  emit('changed')
}

async function save() {
  if (errors.value.length || saving.value) return
  saving.value = true
  const editing = !!group.value
  try {
    const placeFields = hasPlace.value ? mapRef.value?.getPlacePayload() : undefined
    const payload = { ...orderSavePayload(form.value, { canSetStatus: canSetStatus.value, editing }), ...placeFields }
    const saved = group.value
      ? await updateGrossanlassGroup(props.departmentId, group.value.id, payload)
      : await createGrossanlassGroup(props.departmentId, payload)
    if (placeFields?.place_kind === 'point' && !placeFields.place_id && saved.place?.id) {
      const coords = mapRef.value?.getInlineDraftCoords()
      if (coords?.latitude != null && coords?.longitude != null) {
        await updateGrossanlassPlace(props.departmentId, saved.place.id, {
          latitude: coords.latitude,
          longitude: coords.longitude,
        })
      }
    }
    toast.success(editing ? t('grossanlass.meinRessort.ressortUpdated') : t('grossanlass.auftraege.created'))
    emit('saved', saved, !editing)
    if (editing) {
      open.value = false
      return
    }
    // Neu angelegt: im selben Dialog weiter mit «Aufgaben & Material».
    current.value = saved
    existingPlaceId.value = null
    windowBaseline.value = packBauprojektWindow(saved.window_start, saved.window_end)
    statusBaseline.value = saved.build_status || ''
    tab.value = 'tasks'
    await markBaseline()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    saving.value = false
  }
}

async function remove() {
  const target = group.value
  if (!target || deleting.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.deleteBauprojektTitle'),
    message: t('grossanlass.planung.ressorts.deleteBauprojektMessage', { name: target.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  deleting.value = true
  try {
    await deleteGrossanlassGroup(props.departmentId, target.id)
    toast.success(t('grossanlass.meinRessort.bauprojektDeleted'))
    emit('deleted', target.id)
    open.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorDelete'))
  } finally {
    deleting.value = false
  }
}

function archiveMessage(e: unknown): string {
  return (e as { response?: { data?: { error?: string } } }).response?.data?.error || t('grossanlass.aufgaben.actionError')
}

/** Bauauftrag mit Begründung archivieren: Aufgaben, Abnahmen und Historie bleiben erhalten. */
async function archive() {
  const target = group.value
  const reason = archiveReason.value.trim()
  if (!target || !reason || archiving.value) return
  archiving.value = true
  archiveError.value = ''
  try {
    await archiveGrossanlassGroup(props.departmentId, target.id, reason)
    toast.success(t('grossanlass.aufgaben.archive.archived'))
    archiveOpen.value = false
    archiveReason.value = ''
    emit('changed')
    open.value = false
  } catch (e) {
    archiveError.value = archiveMessage(e)
  } finally {
    archiving.value = false
  }
}

async function restore() {
  const target = group.value
  if (!target || archiving.value) return
  archiving.value = true
  try {
    await restoreGrossanlassGroup(props.departmentId, target.id)
    toast.success(t('grossanlass.aufgaben.archive.restored'))
    emit('changed')
    open.value = false
  } catch (e) {
    toast.error(archiveMessage(e))
  } finally {
    archiving.value = false
  }
}
</script>

<style scoped>
.order-dialog__tabs {
  margin-bottom: 8px;
}
.order-dialog__suggest {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.order-dialog__pane {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.order-dialog__types {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.order-type {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.order-type small {
  display: block;
  color: #64748b;
}
.order-type--on {
  border: 2px solid #059669;
  background: #ecfdf5;
}
@media (max-width: 560px) {
  .order-dialog__types {
    grid-template-columns: 1fr;
  }
}
</style>
