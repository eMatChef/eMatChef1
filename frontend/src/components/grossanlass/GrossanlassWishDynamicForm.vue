<template>
  <div v-if="inputFields.length > 0" class="wish-dynamic-form" :data-calendar-count="calendarPeriods.length">
    <p v-if="form?.intro_text" class="form-intro">{{ form.intro_text }}</p>

    <template v-for="field in inputFields" :key="field.id">
      <template v-if="field.system_key === 'bauprojekt'">
        <ESelect
          v-if="groupModeItems.length > 1"
          v-model="local.groupMode"
          :items="groupModeItems"
          :label="fieldLabel(field)"
          :hint="field.help_text || undefined"
          hide-details="auto"
          class="mb-3"
        />
        <EAutocomplete
          v-if="local.groupMode === 'existing'"
          v-model="local.groupId"
          v-model:search="bauprojektSearch"
          :items="bauprojektAutocompleteItems"
          item-title="title"
          item-value="value"
          item-subtitle="subtitle"
          :label="t('grossanlass.wishes.searchBauprojekt')"
          :placeholder="t('grossanlass.wishes.searchBauprojektPlaceholder')"
          :hint="field.help_text || undefined"
          :no-filter="false"
          :custom-filter="filterBauprojektItem"
          :open-on-focus="true"
          hide-details="auto"
          spellcheck="false"
          class="mb-3"
          @update:model-value="onBauprojektSelected"
        >
          <template #no-data>
            <div class="bauprojekt-empty">{{ t('grossanlass.wishes.noBauprojektHits') }}</div>
          </template>
        </EAutocomplete>
        <template v-if="local.groupMode === 'new' && allowNewBauprojekt(field)">
          <ESelect
            v-model="local.parentId"
            :items="parentSelectItems"
            :label="t('grossanlass.wishes.parentRessort')"
            hide-details="auto"
            class="mb-3"
          />
          <ETextField
            v-model="local.newBauprojektName"
            :label="t('grossanlass.wishes.newBauprojektName')"
            hide-details="auto"
            class="mb-3"
          />
        </template>
      </template>

      <template v-else-if="field.system_key === 'ressort_wahl'">
        <ESelect
          v-model="local.ressortGroupId"
          :items="ressortSelectItems(field)"
          :label="fieldLabel(field)"
          :hint="field.help_text || undefined"
          hide-details="auto"
          class="mb-3"
        />
      </template>

      <ESelect
        v-else-if="field.system_key === 'wish_kind'"
        v-model="local.wishKind"
        :items="wishKindItems"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-else-if="field.system_key === 'label'"
        v-model="local.label"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-else-if="field.system_key === 'quantity'"
        v-model="local.quantity"
        type="number"
        min="1"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-else-if="field.system_key === 'location'"
        v-model="local.location"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <GrossanlassWishPeriodField
        v-else-if="field.system_key === 'period' && !hasPhaseSelectField"
        ref="periodRef"
        :title="fieldLabel(field)"
        :department-id="departmentId"
        :required="field.required"
        :allow-past="true"
        class="mb-3"
      />

      <ETextarea
        v-else-if="field.system_key === 'notes'"
        v-model="local.notes"
        :label="fieldLabel(field)"
        hide-details="auto"
        rows="2"
        class="mb-3"
      />

      <ETextarea
        v-else-if="field.custom_type === 'text' && field.config?.multiline"
        v-model="customValues[field.id]"
        :label="fieldLabel(field)"
        hide-details="auto"
        rows="2"
        class="mb-3"
      />

      <ETextField
        v-else-if="field.custom_type === 'text'"
        v-model="customValues[field.id]"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-else-if="field.custom_type === 'number'"
        v-model="customValues[field.id]"
        type="number"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <div
        v-else-if="field.custom_type === 'select'"
        class="mb-3"
        :class="{ 'wish-when-block': isWishPhaseSelectField(field) }"
      >
        <ESelect
          v-if="!isMultiSelectField(field)"
          v-model="customValues[field.id]"
          :items="selectItems(field)"
          :label="fieldLabel(field)"
          hide-details="auto"
          @update:model-value="onPhaseSingleSelect(field)"
        />
        <div v-else class="wish-select-multi" :key="'when-' + phaseChoiceRangeStamp">
          <p class="wish-select-multi-label">{{ fieldLabel(field) }}</p>
          <label
            v-for="row in phaseSelectRows(field)"
            :key="`${field.id}-${row.choice}`"
            class="wish-select-multi-option"
            :class="{ 'wish-when-option': isWishPhaseSelectField(field) }"
          >
            <input
              type="checkbox"
              :checked="isMultiSelectChoice(field.id, row.choice)"
              @change="toggleMultiSelectChoice(field.id, row.choice, ($event.target as HTMLInputElement).checked)"
            />
            <span class="wish-when-option-text">
              <span class="wish-when-option-title">{{ row.choice }}</span>
              <span
                v-if="isWishPhaseSelectField(field)"
                class="wish-when-option-range"
                :class="{ 'wish-when-option-range--missing': !row.range }"
              >
                {{ row.range || t('grossanlass.wishes.phaseFixedDatesMissing') }}
              </span>
            </span>
          </label>
        </div>
        <div v-if="isWishPhaseSelectField(field)" class="wish-when-need">
          <p v-if="!customNeedPeriod && phasePeriodSummary" class="wish-when-summary">
            {{ phasePeriodSummary }}
          </p>
          <label class="wish-when-custom">
            <input v-model="customNeedPeriod" type="checkbox" />
            {{ t('grossanlass.wishes.customNeedPeriod') }}
          </label>
          <p class="wish-when-hint">{{ t('grossanlass.wishes.customNeedPeriodHint') }}</p>
          <GrossanlassWishPeriodField
            v-if="customNeedPeriod"
            ref="periodRef"
            :title="t('grossanlass.wishes.periodLabel')"
            :department-id="departmentId"
            :allow-past="true"
            class="wish-when-picker"
          />
        </div>
      </div>

      <GrossanlassWishPeriodField
        v-else-if="field.custom_type === 'date_range' && !hasPhaseSelectField"
        :ref="(el) => setCustomPeriodRef(field.id, el)"
        :title="fieldLabel(field)"
        :department-id="departmentId"
        :required="field.required"
        :allow-past="true"
        class="mb-3"
      />
    </template>

    <GrossanlassWishPeriodField
      v-if="showFallbackPeriod"
      ref="periodRef"
      :title="t('grossanlass.wishes.periodLabel')"
      :department-id="departmentId"
      :allow-past="true"
      class="mb-3"
    />
    <p v-if="showFallbackPeriod" class="period-fallback-hint">
      {{ t('grossanlass.wishes.periodFallbackHint') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { ESelect, ETextField, ETextarea, EAutocomplete } from '@/components/form/base'
import GrossanlassWishPeriodField from '@/components/grossanlass/GrossanlassWishPeriodField.vue'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { listDepartmentCalendarPeriods, type DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import {
  orderFormFieldsForRound,
  type GrossanlassRoundForm,
  type GrossanlassRoundFormField,
} from '@/api/grossanlassRoundForm'
import type { CreateGrossanlassWishPayload, GrossanlassWishKind, GrossanlassWishLine } from '@/api/grossanlassWishes'
import {
  flattenGrossanlassGroupsFiltered,
  flattenGrossanlassGroupsWithLevel,
  grossanlassGroupIndentTitle,
  isBauprojektGroup,
  isRessortNodeGroup,
  ressortPathForBauprojekt,
} from '@/utils/grossanlassGroupHierarchy'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  calendarRangeForPhaseChoice,
  isWishPhaseSelectField,
  mapWishPhaseChoiceToCalendarLabel,
  unionCalendarPeriods,
  wishPeriodLooksUnreliable,
  wishPeriodMatchesRange,
} from '@/utils/grossanlassWishPeriod'

const props = defineProps<{
  form: GrossanlassRoundForm | null
  departmentId: string
  groups: GrossanlassGroup[]
  canFullyManage: boolean
  isMemberInRessortBranch: (g: GrossanlassGroup) => boolean
  isLeaderOfGroup: (g: GrossanlassGroup) => boolean
  canCreateChild: (g: GrossanlassGroup) => boolean
}>()

const { t, locale } = useI18n()
const authStore = useAuthStore()

const local = reactive({
  groupMode: 'existing' as 'existing' | 'new',
  groupId: null as string | null,
  ressortGroupId: null as string | null,
  parentId: null as string | null,
  newBauprojektName: '',
  wishKind: 'material' as GrossanlassWishKind,
  label: '',
  quantity: '1',
  location: '',
  notes: '',
})

const customValues = reactive<Record<string, string>>({})
const customMultiValues = reactive<Record<string, string[]>>({})
const periodRef = ref<InstanceType<typeof GrossanlassWishPeriodField> | InstanceType<typeof GrossanlassWishPeriodField>[] | null>(null)
const customPeriodRefs = reactive<Record<string, InstanceType<typeof GrossanlassWishPeriodField> | null>>({})
const bauprojektSearch = ref('')
const calendarPeriods = ref<DepartmentCalendarPeriod[]>([])
const suppressPhaseSync = ref(false)
const customNeedPeriod = ref(false)

const inputFields = computed(() =>
  orderFormFieldsForRound(props.form?.fields || []).filter((f) => f.role === 'input' && f.enabled),
)

const showFallbackPeriod = computed(() =>
  !hasPhaseSelectField.value
  && !inputFields.value.some((f) => f.system_key === 'period')
  && !inputFields.value.some((f) => f.custom_type === 'date_range'),
)

const hasPhaseSelectField = computed(() => inputFields.value.some((f) => isWishPhaseSelectField(f)))

const phasePeriodRange = computed(() => {
  const labels = selectedPhaseLabels()
  if (labels.length === 0) return null
  return unionCalendarPeriods(calendarPeriods.value, labels)
})

const phasePeriodSummary = computed(() => {
  if (selectedPhaseLabels().length < 2) return ''
  const range = phasePeriodRange.value
  if (!range) return ''
  return t('grossanlass.wishes.phasePeriodSummary', {
    from: formatGaIsoLabel(range.from, locale.value),
    to: formatGaIsoLabel(range.to, locale.value),
  })
})

const phaseChoiceRangeLabels = computed(() => {
  const labels: Record<string, string> = {}
  for (const field of inputFields.value) {
    if (!isWishPhaseSelectField(field)) continue
    for (const choice of field.options?.choices || []) {
      const range = calendarRangeForPhaseChoice(choice, calendarPeriods.value)
      labels[choice] = range
        ? `${formatGaIsoLabel(range.from, locale.value)} – ${formatGaIsoLabel(range.to, locale.value)}`
        : ''
    }
  }
  return labels
})

const phaseChoiceRangeStamp = computed(() => Object.values(phaseChoiceRangeLabels.value).join('|'))

const groupModeItems = computed(() => {
  const bauprojektField = inputFields.value.find((f) => f.system_key === 'bauprojekt')
  const items: Array<{ title: string; value: 'existing' | 'new' }> = [
    { title: t('grossanlass.wishes.modeExisting'), value: 'existing' },
  ]
  if (bauprojektField && allowNewBauprojekt(bauprojektField) && canUserCreateNewBauprojekt()) {
    items.push({ title: t('grossanlass.wishes.modeNewBauprojekt'), value: 'new' })
  }
  return items
})

function findRootRessortId(group: GrossanlassGroup): string {
  let current: GrossanlassGroup | undefined = group
  const seen = new Set<string>()
  while (current?.parent_id) {
    if (seen.has(current.id)) break
    seen.add(current.id)
    current = props.groups.find((g) => g.id === current!.parent_id)
  }
  return current?.id ?? group.id
}

function isSelectableInLeaderScope(group: GrossanlassGroup): boolean {
  if (props.canFullyManage) return true
  const rootId = findRootRessortId(group)
  const root = props.groups.find((g) => g.id === rootId)
  if (root && props.isLeaderOfGroup(root)) {
    return props.isMemberInRessortBranch(group)
  }
  return props.isLeaderOfGroup(group)
}

function usesLeaderScope(field: GrossanlassRoundFormField): boolean {
  return field.config?.leader_scope === true
}

function collectBranchIds(rootId: string): Set<string> {
  const ids = new Set<string>()
  const queue = [rootId]
  while (queue.length > 0) {
    const id = queue.shift()!
    if (ids.has(id)) continue
    ids.add(id)
    for (const g of props.groups) {
      if (g.parent_id === id) queue.push(g.id)
    }
  }
  return ids
}

function isDescendantOf(group: GrossanlassGroup, rootId: string): boolean {
  return collectBranchIds(rootId).has(group.id)
}

function isGroupVisibleForRessortWahl(field: GrossanlassRoundFormField, group: GrossanlassGroup): boolean {
  if (props.canFullyManage) return true
  if (usesLeaderScope(field)) {
    return isSelectableInLeaderScope(group)
  }
  if (props.isMemberInRessortBranch(group)) return true
  for (const root of props.groups.filter((g) => g.node_type === 'ressort' || (g.parent_id === null && g.kind === 'ressort'))) {
    if (props.canCreateChild(root) && isDescendantOf(group, root.id)) {
      return true
    }
  }
  return false
}

function isGroupVisibleForBauprojekt(field: GrossanlassRoundFormField, group: GrossanlassGroup): boolean {
  if (props.canFullyManage) return true
  if (usesLeaderScope(field)) {
    return isSelectableInLeaderScope(group)
  }
  return props.isMemberInRessortBranch(group)
}

const bauprojektField = computed(() => inputFields.value.find((f) => f.system_key === 'bauprojekt') || null)
const ressortWahlField = computed(() => inputFields.value.find((f) => f.system_key === 'ressort_wahl') || null)

const selectableGroupsForBauprojekt = computed(() => {
  const field = bauprojektField.value
  if (!field) {
    return props.groups.filter((g) => props.canFullyManage || props.isMemberInRessortBranch(g))
  }
  return props.groups.filter((g) => isGroupVisibleForBauprojekt(field, g))
})

const selectableGroupsForRessort = computed(() => {
  const field = ressortWahlField.value
  if (!field) return []
  return props.groups.filter((g) => isGroupVisibleForRessortWahl(field, g))
})

const selectableGroups = computed(() => selectableGroupsForBauprojekt.value)

const bauprojekte = computed(() => {
  const all = flattenGrossanlassGroupsWithLevel(props.groups).filter(isBauprojektGroup)
  const allowed = new Set(
    selectableGroupsForBauprojekt.value.filter(isBauprojektGroup).map((g) => g.id),
  )
  const visible = all.filter((g) => allowed.has(g.id))
  return visible.length > 0 ? visible : all
})

function findBauprojekt(id: string | null | undefined): GrossanlassGroup | undefined {
  if (!id) return undefined
  return bauprojekte.value.find((g) => g.id === id)
    || props.groups.find((g) => g.id === id && isBauprojektGroup(g))
}

function syncBauprojektSearchFromSelection() {
  const selected = findBauprojekt(local.groupId)
  if (selected) bauprojektSearch.value = selected.name
}

function onBauprojektSelected(value: unknown) {
  const id = typeof value === 'string' ? value : null
  local.groupId = id
  syncBauprojektSearchFromSelection()
}

function toBauprojektItem(g: GrossanlassGroup) {
  return {
    title: g.name,
    subtitle: ressortPathForBauprojekt(g, props.groups),
    value: g.id,
  }
}

const bauprojektAutocompleteItems = computed(() => {
  const selected = findBauprojekt(local.groupId)
  let list: GrossanlassGroup[] = [...bauprojekte.value]
  if (selected && !list.some((g) => g.id === selected.id)) {
    list = [selected, ...list]
  }

  const branchIds = hasSystemField('ressort_wahl') && local.ressortGroupId
    ? collectBranchIds(local.ressortGroupId)
    : null
  const rank = (g: GrossanlassGroup) => {
    if (selected && g.id === selected.id) return 0
    if (branchIds?.has(g.id)) return 1
    return 2
  }
  list.sort((a, b) => rank(a) - rank(b) || a.name.localeCompare(b.name, 'de'))
  return list.map(toBauprojektItem)
})

function filterBauprojektItem(
  value: string,
  query: string,
  item?: { raw?: { title?: string; subtitle?: string }; title?: string; subtitle?: string },
): boolean {
  const q = query.trim().toLowerCase()
  if (!q) return true
  const title = String(item?.raw?.title ?? item?.title ?? value ?? '').toLowerCase()
  const subtitle = String(item?.raw?.subtitle ?? item?.subtitle ?? '').toLowerCase()
  return title.includes(q) || subtitle.includes(q)
}

const ressortTreeGroups = computed(() => {
  const allowed = new Set(selectableGroupsForRessort.value.filter(isRessortNodeGroup).map((g) => g.id))
  return flattenGrossanlassGroupsWithLevel(props.groups).filter((g) => allowed.has(g.id))
})

function ressortSelectItems(_field: GrossanlassRoundFormField) {
  return ressortTreeGroups.value.map((g) => ({
    title: grossanlassGroupIndentTitle(g),
    value: g.id,
  }))
}

function findDefaultRessortGroupId(): string | null {
  if (!ressortWahlField.value) return null
  const selectable = ressortTreeGroups.value
  if (selectable.length === 0) return null

  const userId = authStore.userId
  if (!userId) return null

  const directMembershipGroups = selectable.filter((g) =>
    g.members.some((m) => m.user_id === userId),
  )

  if (directMembershipGroups.length > 0) {
    const primaryGroup = directMembershipGroups.find((g) =>
      g.members.some((m) => m.user_id === userId && m.is_primary),
    )
    if (primaryGroup) return primaryGroup.id

    const sorted = [...directMembershipGroups].sort(
      (a, b) => b._level - a._level || a.name.localeCompare(b.name),
    )
    return sorted[0]?.id ?? null
  }

  const rootRessorts = selectable.filter(
    (g) => g.node_type === 'ressort' || (g.parent_id === null && g.kind === 'ressort'),
  )
  for (const root of rootRessorts) {
    if (props.isMemberInRessortBranch(root)) return root.id
  }

  return null
}

function findRessortAncestorId(groupId: string): string | null {
  const group = props.groups.find((g) => g.id === groupId)
  if (!group) return groupId
  if (isRessortNodeGroup(group)) return group.id
  if (group.parent_id) return findRessortAncestorId(group.parent_id)
  return group.id
}

function applyDefaultRessortSelection(force = false) {
  if (!hasSystemField('ressort_wahl')) return
  if (!force && local.ressortGroupId) return
  const defaultId = findDefaultRessortGroupId()
  if (defaultId) {
    local.ressortGroupId = defaultId
  }
}

const parentSelectItems = computed(() => {
  const branchFilter = hasSystemField('ressort_wahl') && local.ressortGroupId
    ? collectBranchIds(local.ressortGroupId)
    : null

  return flattenGrossanlassGroupsFiltered(props.groups, (g) => {
    if (g.node_type === 'bauprojekt') return false
    if (branchFilter && !branchFilter.has(g.id)) return false
    // Neues Bauprojekt: alle Ressorts, unter denen der User Kinder anlegen darf (§4.2)
    return props.canFullyManage || props.canCreateChild(g)
  }).map((g) => ({ title: grossanlassGroupIndentTitle(g), value: g.id }))
})

const wishKindItems = computed(() => [
  { title: t('grossanlass.wishes.kindMaterial'), value: 'material' },
  { title: t('grossanlass.wishes.kindFahrzeug'), value: 'fahrzeug' },
  { title: t('grossanlass.wishes.kindBeides'), value: 'beides' },
])

function fieldLabel(field: GrossanlassRoundFormField): string {
  return field.required ? `${field.label} *` : field.label
}

function allowNewBauprojekt(field: GrossanlassRoundFormField): boolean {
  return field.config?.allow_new_bauprojekt !== false
}

function canUserCreateNewBauprojekt(): boolean {
  if (props.canFullyManage) return true
  return props.groups.some(
    (g) => g.node_type !== 'bauprojekt' && props.canCreateChild(g),
  )
}

function selectItems(field: GrossanlassRoundFormField) {
  return (field.options?.choices || []).map((c) => ({
    title: isWishPhaseSelectField(field) ? phaseChoiceTitle(c) : c,
    value: c,
  }))
}

function phaseSelectRows(field: GrossanlassRoundFormField): Array<{ choice: string; range: string }> {
  return (field.options?.choices || []).map((choice) => ({
    choice,
    range: phaseChoiceRangeLabels.value[choice] || '',
  }))
}

function phaseChoiceTitle(choice: string): string {
  const range = phaseChoiceRangeLabels.value[choice]
  return range ? `${choice} · ${range}` : choice
}

function onPhaseSingleSelect(field: GrossanlassRoundFormField) {
  if (isWishPhaseSelectField(field)) customNeedPeriod.value = false
}

function isMultiSelectField(field: GrossanlassRoundFormField): boolean {
  return field.options?.multiple === true
}

function isMultiSelectChoice(fieldId: string, choice: string): boolean {
  return (customMultiValues[fieldId] || []).includes(choice)
}

function toggleMultiSelectChoice(fieldId: string, choice: string, checked: boolean) {
  const current = customMultiValues[fieldId] || []
  customMultiValues[fieldId] = checked
    ? current.includes(choice)
      ? current
      : [...current, choice]
    : current.filter((c) => c !== choice)
  if (checked) customNeedPeriod.value = false
}

function periodField(): InstanceType<typeof GrossanlassWishPeriodField> | null {
  const el = periodRef.value
  if (!el) return null
  return Array.isArray(el) ? (el[0] ?? null) : el
}

function setCustomPeriodRef(fieldId: string, el: unknown) {
  customPeriodRefs[fieldId] = el as InstanceType<typeof GrossanlassWishPeriodField> | null
}

function hasSystemField(key: string): boolean {
  return inputFields.value.some((f) => f.system_key === key)
}

function buildPayload(): CreateGrossanlassWishPayload {
  const payload: CreateGrossanlassWishPayload = { custom_values: {} }

  if (hasSystemField('ressort_wahl') && local.ressortGroupId) {
    payload.ressort_group_id = local.ressortGroupId
  }

  if (hasSystemField('bauprojekt')) {
    if (local.groupMode === 'new') {
      const parentId = hasSystemField('ressort_wahl') && local.ressortGroupId
        ? local.ressortGroupId
        : (local.parentId || local.ressortGroupId || '')
      payload.new_bauprojekt = {
        name: local.newBauprojektName.trim(),
        parent_id: parentId,
      }
    } else if (local.groupId) {
      payload.group_id = local.groupId
    }
  } else if (hasSystemField('ressort_wahl') && local.ressortGroupId) {
    payload.group_id = local.ressortGroupId
  }

  if (hasSystemField('wish_kind')) {
    payload.wish_kind = local.wishKind
  }
  if (hasSystemField('label')) {
    payload.label = local.label.trim()
  }
  if (hasSystemField('quantity')) {
    payload.quantity = parseInt(local.quantity, 10) || 0
  }
  if (hasSystemField('location')) {
    payload.location = local.location.trim()
  }
  const period = currentNeedRange()
  if (period) {
    payload.valid_from = period.from
    payload.valid_to = period.to
  }
  if (hasSystemField('notes')) {
    payload.notes = local.notes.trim() || null
  }

  const cv: Record<string, unknown> = {}
  for (const field of inputFields.value) {
    if (!field.custom_type) continue
    if (field.custom_type === 'date_range') {
      const range = hasPhaseSelectField.value
        ? period
        : customPeriodRefs[field.id]?.getRange()
      if (range) cv[field.id] = range
    } else if (field.custom_type === 'number') {
      const n = customValues[field.id]
      cv[field.id] = n === '' || n === undefined ? null : Number(n)
    } else if (field.custom_type === 'select' && isMultiSelectField(field)) {
      cv[field.id] = [...(customMultiValues[field.id] || [])]
    } else {
      cv[field.id] = customValues[field.id] || null
    }
  }
  payload.custom_values = cv

  return payload
}

async function loadFromWish(wish: GrossanlassWishLine) {
  const group = props.groups.find((g) => g.id === wish.group_id)
  const isBauprojekt = group ? isBauprojektGroup(group) : false

  local.groupMode = 'existing'
  local.groupId = isBauprojekt ? wish.group_id : null
  local.ressortGroupId = isBauprojekt && group?.parent_id
    ? findRessortAncestorId(group.parent_id)
    : wish.group_id
  local.parentId = null
  local.newBauprojektName = ''
  syncBauprojektSearchFromSelection()
  local.wishKind = wish.wish_kind
  local.label = wish.label
  local.quantity = String(wish.quantity)
  local.location = wish.location
  local.notes = wish.notes || ''

  const cv = wish.custom_values || {}

  for (const field of inputFields.value) {
    if (field.custom_type === 'select' && isMultiSelectField(field)) {
      const raw = cv[field.id]
      customMultiValues[field.id] = Array.isArray(raw) ? [...raw.map(String)] : []
    } else if (field.custom_type === 'number') {
      const raw = cv[field.id]
      customValues[field.id] = raw === null || raw === undefined ? '' : String(raw)
    } else if (field.custom_type === 'text' || field.custom_type === 'select') {
      const raw = cv[field.id]
      customValues[field.id] = raw === null || raw === undefined ? '' : String(raw)
    }
  }

  await nextTick()
  syncBauprojektSearchFromSelection()

  suppressPhaseSync.value = true
  const storedRange = dateRangeFromCustomValues(cv)
  const storedFrom = wish.valid_from || storedRange?.from || null
  const storedTo = wish.valid_to || storedRange?.to || null
  const phaseRange = phasePeriodRange.value
  const looksLikeSubmit = wishPeriodLooksUnreliable(
    storedFrom,
    storedTo,
    wish.created_at,
    calendarPeriods.value,
  )
  const matchesPhases = wishPeriodMatchesRange(storedFrom, storedTo, phaseRange)
  const usePhaseDefault = looksLikeSubmit || matchesPhases || !phaseRange

  customNeedPeriod.value = hasPhaseSelectField.value
    && Boolean(storedFrom && storedTo)
    && !looksLikeSubmit
    && Boolean(phaseRange)
    && !matchesPhases

  await nextTick()
  if (hasPhaseSelectField.value) {
    if (customNeedPeriod.value) {
      periodField()?.setRange(storedFrom, storedTo)
    }
  } else if (hasSystemField('period') || showFallbackPeriod.value) {
    if (usePhaseDefault) {
      applyPhasePeriodToPicker()
    } else {
      periodField()?.setRange(wish.valid_from, wish.valid_to)
    }
  }

  if (!hasPhaseSelectField.value) {
    for (const field of inputFields.value) {
      if (field.custom_type !== 'date_range') continue
      const raw = cv[field.id] as { from?: string; to?: string } | undefined
      customPeriodRefs[field.id]?.setRange(
        raw?.from ?? (usePhaseDefault ? undefined : wish.valid_from),
        raw?.to ?? (usePhaseDefault ? undefined : wish.valid_to),
      )
    }
  }
  await nextTick()
  suppressPhaseSync.value = false
}

function resetAfterSubmit() {
  local.label = ''
  local.location = ''
  local.notes = ''
  customNeedPeriod.value = false
  periodField()?.reset()
  if (local.groupMode === 'new') {
    local.newBauprojektName = ''
  }
  if (!hasSystemField('bauprojekt')) {
    local.groupId = null
  }
  bauprojektSearch.value = ''
  applyDefaultRessortSelection(true)
  for (const field of inputFields.value) {
    if (field.custom_type === 'date_range') {
      customPeriodRefs[field.id]?.reset()
    } else if (field.custom_type === 'select' && isMultiSelectField(field)) {
      customMultiValues[field.id] = []
    } else if (field.custom_type) {
      customValues[field.id] = ''
    }
  }
}

watch(bauprojektSearch, (q) => {
  const selected = findBauprojekt(local.groupId)
  if (selected && q === selected.id) {
    bauprojektSearch.value = selected.name
  }
})

watch(
  () => local.ressortGroupId,
  (ressortId) => {
    if (!ressortId || !local.groupId) return
    const branchIds = collectBranchIds(ressortId)
    if (!branchIds.has(local.groupId)) {
      local.groupId = null
      bauprojektSearch.value = ''
    }
    if (local.groupMode === 'new') {
      local.parentId = ressortId
    }
  },
)

watch(
  () => groupModeItems.value,
  (items) => {
    if (local.groupMode === 'new' && !items.some((i) => i.value === 'new')) {
      local.groupMode = 'existing'
    }
  },
  { immediate: true },
)

watch(
  () => [props.form, props.groups] as const,
  () => {
    const form = props.form
    for (const field of form?.fields || []) {
      if (field.custom_type === 'select' && isMultiSelectField(field)) {
        customMultiValues[field.id] = customMultiValues[field.id] ?? []
      } else if (field.custom_type && field.custom_type !== 'date_range') {
        customValues[field.id] = customValues[field.id] ?? ''
      }
    }
    const baField = form?.fields.find((f) => f.system_key === 'bauprojekt')
    if (baField && !allowNewBauprojekt(baField) && local.groupMode === 'new') {
      local.groupMode = 'existing'
    }
    applyDefaultRessortSelection()
  },
  { immediate: true, deep: true },
)

watch(
  () => props.departmentId,
  (id) => {
    if (!id) {
      calendarPeriods.value = []
      return
    }
    void listDepartmentCalendarPeriods(id).then((rows) => {
      calendarPeriods.value = rows
    }).catch(() => {
      calendarPeriods.value = []
    })
  },
  { immediate: true },
)

watch(
  () => [JSON.stringify(customMultiValues), calendarPeriods.value] as const,
  () => {
    if (suppressPhaseSync.value || customNeedPeriod.value) return
    applyPhasePeriodToPicker()
  },
)

watch(customNeedPeriod, async (on) => {
  if (!on || suppressPhaseSync.value) return
  await nextTick()
  await nextTick()
  applyPhasePeriodToPicker()
})

function selectedPhaseLabels(): Array<'aufbau' | 'grossanlass' | 'abbau'> {
  const labels: Array<'aufbau' | 'grossanlass' | 'abbau'> = []
  const seen = new Set<string>()
  for (const field of inputFields.value) {
    if (!isWishPhaseSelectField(field)) continue
    if (isMultiSelectField(field)) {
      for (const choice of customMultiValues[field.id] || []) {
        const label = mapWishPhaseChoiceToCalendarLabel(choice)
        if (label && !seen.has(label)) {
          seen.add(label)
          labels.push(label)
        }
      }
    } else {
      const label = mapWishPhaseChoiceToCalendarLabel(customValues[field.id] || '')
      if (label && !seen.has(label)) {
        seen.add(label)
        labels.push(label)
      }
    }
  }
  return labels
}

function dateRangeFromCustomValues(cv: Record<string, unknown>): { from?: string; to?: string } | undefined {
  const field = inputFields.value.find((f) => f.custom_type === 'date_range')
  if (!field) return undefined
  const raw = cv[field.id]
  if (raw && typeof raw === 'object' && raw !== null && 'from' in raw) {
    return raw as { from?: string; to?: string }
  }
  return undefined
}

function currentNeedRange(): { from: string; to: string } | null {
  if (customNeedPeriod.value) {
    return periodField()?.getRange() ?? null
  }
  if (hasPhaseSelectField.value) {
    return phasePeriodRange.value
      ?? unionCalendarPeriods(calendarPeriods.value, ['grossanlass'])
  }
  return periodField()?.getRange() ?? null
}

function applyPhasePeriodToPicker() {
  const labels = selectedPhaseLabels()
  const range = labels.length > 0
    ? unionCalendarPeriods(calendarPeriods.value, labels)
    : unionCalendarPeriods(calendarPeriods.value, ['grossanlass'])
  if (!range) return
  periodField()?.setRange(range.from, range.to)
}

defineExpose({ buildPayload, resetAfterSubmit, loadFromWish })
</script>

<style scoped>
.form-intro {
  margin: 0 0 14px;
  color: #4b5563;
  font-size: 0.9rem;
}

.period-fallback-hint {
  margin: -8px 0 14px;
  color: #64748b;
  font-size: 0.82rem;
}

.bauprojekt-empty {
  padding: 12px 16px;
  font-size: 0.85rem;
  color: #64748b;
}

.wish-select-multi-label {
  margin: 0 0 8px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #374151;
}

.wish-select-multi-option {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 0.9rem;
  color: #374151;
  cursor: pointer;
}

.wish-when-option {
  align-items: flex-start;
  margin-bottom: 10px;
}

.wish-when-option input {
  margin-top: 3px;
  flex: 0 0 auto;
}

.wish-when-option-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.wish-when-option-title {
  font-weight: 600;
}

.wish-when-option-range {
  font-size: 0.82rem;
  color: #4b5563;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.wish-when-option-range--missing {
  color: #b45309;
}

.wish-when-block {
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
}

.wish-when-need {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #e5e7eb;
}

.wish-when-hint {
  margin: 0 0 8px;
  color: #64748b;
  font-size: 0.82rem;
  line-height: 1.4;
}

.wish-when-summary {
  margin: 0 0 10px;
  color: #1f2937;
  font-size: 0.88rem;
  font-weight: 600;
}

.wish-when-custom {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 6px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
}

.wish-when-picker {
  margin-top: 10px;
}

</style>
