<template>
  <section class="extra-points" :aria-label="t('grossanlass.planung.ressorts.extraPoints.title')">
    <header class="extra-points__head">
      <h4>{{ t('grossanlass.planung.ressorts.extraPoints.title') }}</h4>
      <div class="extra-points__actions">
        <EButton variant="secondary" size="small" type="button" :disabled="!!draft || busy" @click="startAdd">
          {{ t('grossanlass.planung.ressorts.extraPoints.add') }}
        </EButton>
        <EButton variant="secondary" size="small" type="button" :disabled="candidates.length === 0 || !!draft || busy" @click="pickerOpen = !pickerOpen">
          {{ t('grossanlass.planung.ressorts.extraPoints.useExisting') }}
        </EButton>
      </div>
    </header>
    <p class="extra-points__hint">{{ t('grossanlass.planung.ressorts.extraPoints.hint') }}</p>
    <EAutocomplete
      v-if="pickerOpen && candidates.length > 0"
      :model-value="null"
      :items="candidates"
      item-title="name"
      item-value="id"
      :label="t('grossanlass.planung.ressorts.extraPoints.search')"
      :no-data-text="t('grossanlass.planung.ressorts.existingPlace.noMatch')"
      hide-details
      clearable
      @update:model-value="onPick"
    />

    <div v-if="draft" class="extra-points__draft" role="group">
      <ETextField v-model="draft.name" :label="t('grossanlass.planung.ressorts.placeNameLabel')" hide-details="auto" />
      <p class="extra-points__hint">
        {{ draft.mode === 'add'
          ? t('grossanlass.planung.ressorts.extraPoints.clickMap')
          : t('grossanlass.planung.ressorts.extraPoints.clickMapMove') }}
      </p>
      <div class="extra-points__actions">
        <EButton size="small" type="button" :disabled="busy" @click="saveDraft">{{ t('common.save') }}</EButton>
        <EButton variant="secondary" size="small" type="button" :disabled="busy" @click="cancelDraft">{{ t('common.cancel') }}</EButton>
      </div>
    </div>

    <p v-if="error" class="extra-points__error" role="alert">{{ error }}</p>
    <p v-if="points.length === 0 && !draft" class="extra-points__hint">{{ t('grossanlass.planung.ressorts.extraPoints.none') }}</p>
    <ul v-else class="extra-points__list">
      <li v-for="point in points" :key="point.id" class="extra-points__row" data-testid="extra-point-row">
        <div class="extra-points__name">
          <ETextField
            :model-value="names[point.id] ?? point.name"
            :label="t('grossanlass.planung.ressorts.placeNameLabel')"
            :readonly="editingNameId !== point.id"
            :disabled="busy"
            hide-details="auto"
            @update:model-value="names[point.id] = String($event ?? '')"
            @keydown.enter.prevent="renameIfChanged(point)"
            @keydown.esc.prevent="cancelRename(point)"
            @blur="renameIfChanged(point)"
          />
          <v-btn
            v-if="editingNameId !== point.id"
            icon
            variant="text"
            size="small"
            type="button"
            :disabled="!!draft || busy"
            :title="t('grossanlass.planung.ressorts.extraPoints.rename')"
            :aria-label="t('grossanlass.planung.ressorts.extraPoints.rename')"
            data-testid="extra-point-rename"
            @click="startRename(point)"
          >
            <v-icon icon="mdi-pencil-outline" size="18" />
          </v-btn>
        </div>
        <p v-if="editingNameId === point.id && usedElsewhere(point) > 0" class="extra-points__hint" role="status">
          {{ t('grossanlass.planung.ressorts.extraPoints.renameShared', { count: usedElsewhere(point) + 1 }) }}
        </p>
        <span class="extra-points__meta">
          {{ formatCoord(point.latitude) }}, {{ formatCoord(point.longitude) }}
          · {{ t('grossanlass.planung.ressorts.extraPoints.code') }} {{ point.public_code }}
          <template v-if="usedElsewhere(point) > 0">
            · {{ t('grossanlass.planung.ressorts.existingPlace.alsoUsedBy', { count: usedElsewhere(point) }) }}
          </template>
        </span>
        <div class="extra-points__actions">
          <EButton variant="secondary" size="small" type="button" :disabled="!!draft || busy" @click="startMove(point)">
            {{ t('grossanlass.planung.ressorts.extraPoints.move') }}
          </EButton>
          <EButton variant="secondary" size="small" type="button" :disabled="!!draft || busy" @click="detach(point)">
            {{ t('grossanlass.planung.ressorts.extraPoints.detach') }}
          </EButton>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EAutocomplete, EButton, ETextField } from '@/components/form/base'
import type { GaPlace } from '@/api/grossanlassLogistics'
import {
  addGrossanlassGroupPlace,
  removeGrossanlassGroupPlace,
  updateGrossanlassGroupPlace,
} from '@/api/grossanlassGroups'
import { gaPlaceGroupIds, gaPlaceKind } from '@/utils/grossanlassGaMap'
import { apiErrorMessage } from '@/utils/apiErrorMessage'

/**
 * Weitere Standortpunkte einer Gruppe neben ihrem Hauptstandort (Polygon oder Punkt).
 * Punkte sind eigene Standorte: Hinzufügen, Verschieben, Umbenennen und Entkoppeln ändern das Polygon nie
 * und löschen nie einen Standort. Die Position wird über den Entwurfs-Pin der gemeinsamen Karte gesetzt.
 */
type MapBlock = {
  places?: GaPlace[]
  reloadGa?: () => Promise<void>
  beginInlineDraft: (name?: string, kind?: 'bauprojekt') => void
  clearInlineDraft: () => void
  getInlineDraftCoords: () => { latitude: number | null; longitude: number | null }
}

const props = defineProps<{
  departmentId: string
  groupId: string
  /** Hauptstandort der Gruppe: erscheint nicht in dieser Liste. */
  mainPlaceId: string | null
  block: MapBlock | null
}>()
const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const busy = ref(false)
const error = ref('')
const pickerOpen = ref(false)
const editingNameId = ref<string | null>(null)
const names = reactive<Record<string, string>>({})
const draft = ref<{ mode: 'add' | 'move'; placeId?: string; name: string } | null>(null)

const allPlaces = computed(() => props.block?.places ?? [])
const isPoint = (place: GaPlace) => gaPlaceKind(place.kind) !== 'area'
const points = computed(() =>
  allPlaces.value.filter(
    (place) => isPoint(place) && place.id !== props.mainPlaceId && gaPlaceGroupIds(place).includes(props.groupId),
  ),
)
/** Freie oder anderweitig genutzte Punkte, die dieser Gruppe noch nicht zugeordnet sind. */
const candidates = computed(() =>
  allPlaces.value.filter(
    (place) => isPoint(place) && place.id !== props.mainPlaceId && !gaPlaceGroupIds(place).includes(props.groupId),
  ),
)

const usedElsewhere = (place: GaPlace) => gaPlaceGroupIds(place).filter((id) => id !== props.groupId).length
const formatCoord = (value: number | null | undefined) => (value == null ? '–' : value.toFixed(5))

async function run(action: () => Promise<unknown>) {
  busy.value = true
  error.value = ''
  try {
    await action()
    await props.block?.reloadGa?.()
    emit('changed')
  } catch (e) {
    error.value = apiErrorMessage(e, t('grossanlass.planung.ressorts.extraPoints.failed'))
  } finally {
    busy.value = false
  }
}

function startAdd() {
  draft.value = { mode: 'add', name: '' }
  props.block?.beginInlineDraft('', 'bauprojekt')
}

function startMove(point: GaPlace) {
  draft.value = { mode: 'move', placeId: point.id, name: names[point.id] ?? point.name }
  props.block?.beginInlineDraft(point.name, 'bauprojekt')
}

function cancelDraft() {
  props.block?.clearInlineDraft()
  draft.value = null
  emit('changed')
}

async function saveDraft() {
  const current = draft.value
  if (!current) return
  const { latitude, longitude } = props.block?.getInlineDraftCoords() ?? { latitude: null, longitude: null }
  const name = current.name.trim()
  if (current.mode === 'add' && (!name || latitude == null || longitude == null)) {
    error.value = t('grossanlass.planung.ressorts.extraPoints.needNameAndPosition')
    return
  }
  await run(async () => {
    if (current.mode === 'add') {
      await addGrossanlassGroupPlace(props.departmentId, props.groupId, { name, latitude, longitude })
    } else if (current.placeId) {
      await updateGrossanlassGroupPlace(props.departmentId, props.groupId, current.placeId, {
        ...(name ? { name } : {}),
        ...(latitude != null && longitude != null ? { latitude, longitude } : {}),
      })
    }
    props.block?.clearInlineDraft()
    draft.value = null
  })
}

async function onPick(placeId: string | null) {
  if (!placeId) return
  pickerOpen.value = false
  await run(() => addGrossanlassGroupPlace(props.departmentId, props.groupId, { place_id: placeId }))
}

function startRename(point: GaPlace) {
  editingNameId.value = point.id
  names[point.id] = point.name
  void nextTick(() => document.querySelector<HTMLInputElement>('[data-testid="extra-point-row"] input:not([readonly])')?.focus())
}

function cancelRename(point: GaPlace) {
  delete names[point.id]
  editingNameId.value = null
}

async function renameIfChanged(point: GaPlace) {
  if (editingNameId.value !== point.id) return
  editingNameId.value = null
  const name = (names[point.id] ?? point.name).trim()
  if (!name || name === point.name) {
    delete names[point.id]
    return
  }
  await run(() => updateGrossanlassGroupPlace(props.departmentId, props.groupId, point.id, { name }))
  delete names[point.id]
}

async function detach(point: GaPlace) {
  await run(() => removeGrossanlassGroupPlace(props.departmentId, props.groupId, point.id))
}
</script>

<style scoped>
.extra-points__name {
  display: flex;
  align-items: flex-start;
  gap: 4px;
}

.extra-points__name > :deep(.e-form-field) {
  flex: 1 1 auto;
  min-width: 0;
}

.extra-points {
  margin: 12px 0;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}
.extra-points__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.extra-points__head h4 {
  margin: 0;
  font-size: 0.95rem;
}
.extra-points__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.extra-points__hint {
  margin: 6px 0;
  color: #64748b;
  font-size: 0.85rem;
}
.extra-points__error {
  margin: 6px 0;
  color: #b91c1c;
  font-size: 0.85rem;
}
.extra-points__list {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.extra-points__row,
.extra-points__draft {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.extra-points__meta {
  color: #64748b;
  font-size: 0.8rem;
}
</style>
