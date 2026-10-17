<template>
  <div class="existing-place-picker">
    <template v-if="selectedPlace">
      <div class="existing-place-picker__chosen">
        <span class="existing-place-picker__dot" :style="{ background: selectedColor }" aria-hidden="true" />
        <div class="existing-place-picker__chosen-text">
          <strong>{{ selectedPlace.name }}</strong>
          <span class="existing-place-picker__hint">{{ t('grossanlass.planung.ressorts.existingPlace.inUse') }}</span>
          <span v-if="selectedGroupCount > 0" class="existing-place-picker__hint">
            {{ t('grossanlass.planung.ressorts.existingPlace.alsoUsedBy', { count: selectedGroupCount }) }}
          </span>
        </div>
      </div>
      <EButton variant="secondary" size="small" type="button" @click="emit('select', null)">
        {{ needsArea
          ? t('grossanlass.planung.ressorts.existingPlace.drawNewArea')
          : t('grossanlass.planung.ressorts.existingPlace.placeNewPoint') }}
      </EButton>
    </template>
    <template v-else>
      <EButton
        variant="secondary"
        size="small"
        type="button"
        :disabled="candidates.length === 0"
        @click="open = !open"
      >
        {{ needsArea
          ? t('grossanlass.planung.ressorts.existingPlace.useArea')
          : t('grossanlass.planung.ressorts.existingPlace.usePoint') }}
      </EButton>
      <p v-if="candidates.length === 0" class="existing-place-picker__hint">
        {{ needsArea
          ? t('grossanlass.planung.ressorts.existingPlace.noneArea')
          : t('grossanlass.planung.ressorts.existingPlace.nonePoint') }}
      </p>
      <EAutocomplete
        v-if="open && candidates.length > 0"
        :model-value="null"
        :items="candidates"
        item-title="name"
        item-value="id"
        :label="needsArea
          ? t('grossanlass.planung.ressorts.existingPlace.searchArea')
          : t('grossanlass.planung.ressorts.existingPlace.searchPoint')"
        :no-data-text="t('grossanlass.planung.ressorts.existingPlace.noMatch')"
        hide-details
        clearable
        @update:model-value="onPick"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EAutocomplete, EButton } from '@/components/form/base'
import type { GaPlace } from '@/api/grossanlassLogistics'
import { gaPlaceColor, gaPlaceGroupIds, gaPlaceKind, normalizeGaPolygon } from '@/utils/grossanlassGaMap'

const props = defineProps<{
  places: GaPlace[]
  /** true: Bereich (Polygon) wählen, sonst Punkt. */
  needsArea: boolean
  selectedId: string | null
  /** Bearbeitete Gruppe; ihre eigene Zuordnung zählt nicht als «andere Nutzung». */
  groupId?: string | null
}>()

const emit = defineEmits<{ select: [placeId: string | null] }>()

const { t } = useI18n()
const open = ref(false)

/** Alle passenden Orte, auch bereits genutzte: ein Standort darf von mehreren Gruppen gemeinsam verwendet werden. */
const candidates = computed(() =>
  props.places
    .filter((place) => {
      const kind = gaPlaceKind(place.kind)
      if (props.needsArea) return kind === 'area' && normalizeGaPolygon(place.polygon).length >= 3
      return kind === 'bauprojekt' && place.latitude != null && place.longitude != null
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'de')),
)

const selectedPlace = computed(() => props.places.find((place) => place.id === props.selectedId) ?? null)
const selectedGroupCount = computed(() =>
  gaPlaceGroupIds(selectedPlace.value).filter((gid) => gid !== props.groupId).length,
)
const selectedColor = computed(() =>
  selectedPlace.value ? gaPlaceColor(gaPlaceKind(selectedPlace.value.kind)) : '#64748b',
)

function onPick(value: unknown) {
  if (typeof value !== 'string' || !value) return
  open.value = false
  emit('select', value)
}
</script>

<style scoped>
.existing-place-picker {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 8px;
  min-width: 0;
}

.existing-place-picker :deep(.e-form-field) {
  width: 100%;
}

.existing-place-picker__chosen {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.existing-place-picker__chosen-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow-wrap: anywhere;
}

.existing-place-picker__dot {
  flex: 0 0 auto;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.existing-place-picker__hint {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}
</style>
