<template>
  <div class="group-map-editor">
    <div class="group-map-editor__fields">
      <div class="group-map-editor__kind" role="group" :aria-label="t('grossanlass.planung.ressorts.placeKind.label')">
        <span class="group-map-editor__kind-label">{{ t('grossanlass.planung.ressorts.placeKind.label') }}</span>
        <v-btn-toggle
          :model-value="placeKind"
          mandatory
          density="comfortable"
          divided
          color="primary"
          :disabled="!!existingPlaceId || sharedCount > 1"
          @update:model-value="onKindChange"
        >
          <v-btn value="bauprojekt" size="small">{{ t('grossanlass.planung.ressorts.placeKind.point') }}</v-btn>
          <v-btn value="area" size="small">{{ t('grossanlass.planung.ressorts.placeKind.area') }}</v-btn>
        </v-btn-toggle>
      </div>
      <ETextField
        v-model="placeName"
        :label="t('grossanlass.planung.ressorts.placeNameLabel')"
        :hint="t('grossanlass.planung.ressorts.placeNameHint')"
        persistent-hint
        hide-details="auto"
        @update:model-value="placeNameTouched = true"
      />
    </div>
    <p v-if="sharedCount > 1" class="group-map-editor__shared" role="status">
      {{ t('grossanlass.planung.ressorts.placeShared', { count: sharedCount }) }}
    </p>
    <p v-if="placeKind === 'bauprojekt'" class="group-map-editor__hint">
      {{ t('grossanlass.planung.ressorts.mapHint') }}
    </p>
    <GrossanlassGroupExtraPoints
      v-if="venueAddressId && groupId"
      :department-id="departmentId"
      :group-id="groupId"
      :main-place-id="editingPlaceId"
      :block="blockRef"
      @changed="onPointsChanged"
    />
    <v-switch
      v-if="ownPlaceIds.length > 0"
      v-model="showOtherPlaces"
      class="group-map-editor__others"
      color="primary"
      density="compact"
      hide-details
      :label="t('grossanlass.planung.ressorts.showOtherPlaces')"
      :title="t('grossanlass.planung.ressorts.showOtherPlacesHint')"
    />
    <ActivityVenueOverviewBlock
      v-if="venueAddressId"
      ref="blockRef"
      :venue-address-id="venueAddressId"
      :department-id="departmentId"
      :ga-department-id="departmentId"
      ga-map-mode="all"
      inline-place-draft
      :draft-place-name="placeName"
      :draft-place-kind="placeKind"
      hide-title
      hover-pin-labels
      picker-mode
      :own-place-ids="ownPlaceIds.length > 0 ? ownPlaceIds : null"
      :show-other-places="showOtherPlaces"
      @save-area="emit('save-area')"
    >
      <template #sidebar-top>
        <GrossanlassExistingPlacePicker
          :places="blockRef?.places ?? []"
          :needs-area="placeKind === 'area'"
          :selected-id="existingPlaceId"
          :group-id="groupId ?? null"
          @select="onSelectExisting"
        />
      </template>
    </ActivityVenueOverviewBlock>
    <p v-else class="group-map-editor__missing">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ETextField } from '@/components/form/base'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import { gaPlaceGroupIds } from '@/utils/grossanlassGaMap'
import { placeNameForSave } from '@/utils/grossanlassOrderForm'
import GrossanlassExistingPlacePicker from '@/components/grossanlass/GrossanlassExistingPlacePicker.vue'
import GrossanlassGroupExtraPoints from '@/components/grossanlass/GrossanlassGroupExtraPoints.vue'

/**
 * Gemeinsame Karten-Bearbeitung für Ressorts, Bereiche und Bauprojekte
 * (/ga/my-unit und /ga/activity-settings/units). Kapselt Standort-Entwurf,
 * Auswahl vorhandener Orte (place_id) und gesperrtes Verschieben (Bleistift).
 */
const props = defineProps<{
  departmentId: string
  venueAddressId: string | null
  /** Gruppenname: nur Vorschlag für den Namen eines neuen Standorts, nie Quelle für Umbenennungen. */
  name: string
  /** Ort der bearbeiteten Gruppe, sofern bereits vorhanden. */
  editingPlaceId: string | null
  /** Bearbeitete Gruppe (für die Zählung gemeinsamer Nutzung); null beim Anlegen. */
  groupId?: string | null
}>()

const emit = defineEmits<{ 'save-area': []; 'places-changed': [] }>()

/** Gewählter freier Ort, der statt eines neuen Punkts/Bereichs verknüpft wird. */
const existingPlaceId = defineModel<string | null>('existingPlaceId', { default: null })

/** 'bauprojekt' = einzelner Punkt, 'area' = Polygon. */
const placeKind = defineModel<'area' | 'bauprojekt'>('placeKind', { default: 'bauprojekt' })
/** Eigener Standortname, unabhängig vom Gruppennamen. */
const placeName = defineModel<string>('placeName', { default: '' })
const placeNameTouched = ref(false)

/** Anzahl Gruppen, die den Standort nach dem Speichern nutzen (inkl. dieser Gruppe). */
const sharedCount = computed(() => {
  const places = blockRef.value?.places ?? []
  const linked = places.find((row) => row.id === existingPlaceId.value)
  if (linked) {
    const others = gaPlaceGroupIds(linked).filter((gid) => gid !== props.groupId)
    return others.length + 1
  }
  const own = places.find((row) => row.id === props.editingPlaceId)
  return own ? Math.max(gaPlaceGroupIds(own).length, 1) : 0
})

const { t } = useI18n()
const blockRef = ref<InstanceType<typeof ActivityVenueOverviewBlock> | null>(null)

/** Fremde Event-Standorte sind standardmässig ausgeblendet. */
const showOtherPlaces = ref(false)

/** Eigene Standorte: Hauptstandort (bzw. verknüpfter Ort) und der Gruppe zugeordnete Zusatzpunkte. */
const ownPlaceIds = computed((): string[] => {
  if (!props.groupId) return []
  const mainId = existingPlaceId.value || props.editingPlaceId
  const ids = new Set<string>()
  if (mainId) ids.add(mainId)
  for (const place of blockRef.value?.places ?? []) {
    if (gaPlaceGroupIds(place).includes(props.groupId)) ids.add(place.id)
  }
  return [...ids]
})

function fitToOwnPlaces() {
  if (ownPlaceIds.value.length > 0) blockRef.value?.fitToPlaces(ownPlaceIds.value)
}

// Ein-/Ausblenden fremder Standorte lässt die Karte neu einpassen; danach wieder auf die eigenen zentrieren.
watch(showOtherPlaces, async () => {
  await nextTick()
  await nextTick()
  fitToOwnPlaces()
})

/** Karte laden und den Standort der Gruppe markieren bzw. einen Entwurf starten. */
async function syncPlacement(reloadFields = true) {
  await nextTick()
  await blockRef.value?.reloadGa?.()
  await nextTick()
  if (reloadFields) loadPlaceFields()
  blockRef.value?.refreshMaps?.()
  if (existingPlaceId.value) {
    blockRef.value?.previewExistingPlace(existingPlaceId.value)
    return
  }
  if (props.editingPlaceId) {
    blockRef.value?.beginEditPlace(props.editingPlaceId)
    fitToOwnPlaces()
    return
  }
  blockRef.value?.beginInlineDraft(placeName.value, placeKind.value)
}

/** Name des bearbeiteten bzw. verknüpften Standorts anzeigen, sonst den Gruppennamen vorschlagen. */
function loadPlaceFields() {
  const place = (blockRef.value?.places ?? []).find((row) => row.id === (existingPlaceId.value || props.editingPlaceId))
  if (place) {
    placeName.value = place.name
    placeKind.value = place.kind === 'area' ? 'area' : 'bauprojekt'
    placeNameTouched.value = true
    return
  }
  placeNameTouched.value = false
  placeName.value = props.name
}

/** Punkte wurden hinzugefügt, verschoben oder entkoppelt: Hauptstandort wieder markieren, Eltern informieren. */
async function onPointsChanged() {
  await syncPlacement(false)
  emit('places-changed')
}

async function onKindChange(kind: 'area' | 'bauprojekt' | undefined) {
  if (!kind || kind === placeKind.value) return
  placeKind.value = kind
  if (!existingPlaceId.value) await syncPlacement(false)
}

// Gruppenname ist nur ein Vorschlag, solange kein Standort existiert und der Name nicht angepasst wurde.
watch(
  () => props.name,
  (name) => {
    if (placeNameTouched.value || existingPlaceId.value || props.editingPlaceId) return
    placeName.value = name
  },
)

/** Felder für Speichern; Name nur senden, wenn er vom bestehenden Standortnamen abweicht. */
function getPlacePayload() {
  const linkedId = existingPlaceId.value
  const current = (blockRef.value?.places ?? []).find((row) => row.id === (linkedId || props.editingPlaceId))
  const expectsExisting = !!(linkedId || props.editingPlaceId)
  const kind = placeKind.value === 'area' ? 'area' : 'point'
  return {
    place_id: linkedId || undefined,
    place_kind: kind,
    place_name: placeNameForSave(placeName.value, current, expectsExisting),
    polygon: !linkedId && kind === 'area' ? blockRef.value?.getInlineDraftPolygon() ?? [] : undefined,
  } as const
}

async function onSelectExisting(placeId: string | null) {
  existingPlaceId.value = placeId
  if (placeId) {
    loadPlaceFields()
    blockRef.value?.clearInlineDraft()
    blockRef.value?.previewExistingPlace(placeId)
    return
  }
  blockRef.value?.previewExistingPlace(null)
  await syncPlacement()
}

defineExpose({
  syncPlacement,
  reloadGa: () => blockRef.value?.reloadGa?.(),
  finishInlineDraw: (placeId?: string | null) => blockRef.value?.finishInlineDraw(placeId),
  clearInlineDraft: () => blockRef.value?.clearInlineDraft(),
  getInlineDraftCoords: () => blockRef.value?.getInlineDraftCoords(),
  getInlineDraftPolygon: () => blockRef.value?.getInlineDraftPolygon(),
  getPlacePayload,
})
</script>

<style scoped>
.group-map-editor {
  margin-top: 12px;
  --ev-map-height: 520px;
  min-width: 0;
}

.group-map-editor__fields {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px 16px;
  margin-bottom: 12px;
}

.group-map-editor__fields > :deep(.e-form-field) {
  flex: 1 1 260px;
  min-width: 0;
}

.group-map-editor__kind {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.group-map-editor__kind-label {
  font-size: 0.8rem;
  color: #64748b;
}

.group-map-editor__others {
  margin: 0 0 4px;
}

.group-map-editor__shared {
  margin: 0 0 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 0.85rem;
}

.group-map-editor__hint,
.group-map-editor__missing {
  margin: 0 0 8px;
  color: #64748b;
  font-size: 0.85rem;
}

.group-map-editor__missing {
  padding: 12px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
}

@media (max-width: 767px) {
  .group-map-editor {
    --ev-map-height: 360px;
  }
}
</style>
