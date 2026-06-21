<template>
  <div v-if="inputFields.length > 0" class="wish-dynamic-form">
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
          :menu="bauprojektMenuOpen"
          open-on-focus="false"
          hide-details="auto"
          class="mb-3"
        />
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
        v-else-if="field.system_key === 'period'"
        ref="periodRef"
        :title="fieldLabel(field)"
        :department-id="departmentId"
        :required="field.required"
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

      <ESelect
        v-else-if="field.custom_type === 'select' && !isMultiSelectField(field)"
        v-model="customValues[field.id]"
        :items="selectItems(field)"
        :label="fieldLabel(field)"
        hide-details="auto"
        class="mb-3"
      />

      <div v-else-if="field.custom_type === 'select' && isMultiSelectField(field)" class="wish-select-multi mb-3">
        <p class="wish-select-multi-label">{{ fieldLabel(field) }}</p>
        <label
          v-for="choice in field.options?.choices || []"
          :key="`${field.id}-${choice}`"
          class="wish-select-multi-option"
        >
          <input
            type="checkbox"
            :checked="isMultiSelectChoice(field.id, choice)"
            @change="toggleMultiSelectChoice(field.id, choice, ($event.target as HTMLInputElement).checked)"
          />
          {{ choice }}
        </label>
      </div>

      <GrossanlassWishPeriodField
        v-else-if="field.custom_type === 'date_range'"
        :ref="(el) => setCustomPeriodRef(field.id, el)"
        :title="fieldLabel(field)"
        :department-id="departmentId"
        :required="field.required"
        class="mb-3"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { ESelect, ETextField, ETextarea, EAutocomplete } from '@/components/form/base'
import GrossanlassWishPeriodField from '@/components/grossanlass/GrossanlassWishPeriodField.vue'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
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

const props = defineProps<{
  form: GrossanlassRoundForm | null
  departmentId: string
  groups: GrossanlassGroup[]
  canFullyManage: boolean
  isMemberInRessortBranch: (g: GrossanlassGroup) => boolean
  isLeaderOfGroup: (g: GrossanlassGroup) => boolean
  canCreateChild: (g: GrossanlassGroup) => boolean
}>()

const { t } = useI18n()
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
const periodRef = ref<InstanceType<typeof GrossanlassWishPeriodField> | null>(null)
const customPeriodRefs = reactive<Record<string, InstanceType<typeof GrossanlassWishPeriodField> | null>>({})
const bauprojektSearch = ref('')

const inputFields = computed(() =>
  orderFormFieldsForRound(props.form?.fields || []).filter((f) => f.role === 'input' && f.enabled),
)

const groupModeItems = computed(() => {
  const bauprojektField = inputFields.value.find((f) => f.system_key === 'bauprojekt')
  const items: Array<{ title: string; value: 'existing' | 'new' }> = [
    { title: t('grossanlass.wishes.modeExisting'), value: 'existing' },
  ]
  if (bauprojektField && allowNewBauprojekt(bauprojektField)) {
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
  const allowed = new Set(
    selectableGroupsForBauprojekt.value.filter(isBauprojektGroup).map((g) => g.id),
  )
  return flattenGrossanlassGroupsWithLevel(props.groups).filter((g) => allowed.has(g.id))
})

const ressortTreeGroups = computed(() => {
  const allowed = new Set(selectableGroupsForRessort.value.filter(isRessortNodeGroup).map((g) => g.id))
  return flattenGrossanlassGroupsWithLevel(props.groups).filter((g) => allowed.has(g.id))
})

const bauprojektAutocompleteItems = computed(() => {
  const q = bauprojektSearch.value.trim().toLowerCase()
  if (!q) return []

  let list = bauprojekte.value
  if (hasSystemField('ressort_wahl') && local.ressortGroupId) {
    const branchIds = collectBranchIds(local.ressortGroupId)
    list = list.filter((g) => branchIds.has(g.id))
  }
  list = list.filter((g) => {
    const path = ressortPathForBauprojekt(g, props.groups).toLowerCase()
    return g.name.toLowerCase().includes(q) || path.toLowerCase().includes(q)
  })
  return list.map((g) => ({
    title: g.name,
    subtitle: ressortPathForBauprojekt(g, props.groups),
    value: g.id,
  }))
})

/** Dropdown erst bei aktiver Suche — kein leeres «Keine Daten» beim Fokus. */
const bauprojektMenuOpen = computed(() => {
  const q = bauprojektSearch.value.trim()
  if (q.length === 0) return false
  if (local.groupId) {
    const selected = props.groups.find((g) => g.id === local.groupId)
    if (selected && q === selected.name) return false
  }
  return true
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

const parentSelectItems = computed(() =>
  flattenGrossanlassGroupsFiltered(props.groups, (g) => {
    if (g.node_type === 'bauprojekt') return false
    const field = bauprojektField.value
    if (!selectableGroups.value.some((sg) => sg.id === g.id)) return false
    if (!field || !usesLeaderScope(field) || props.canFullyManage || props.isLeaderOfGroup(g)) {
      return true
    }
    return false
  }).map((g) => ({ title: grossanlassGroupIndentTitle(g), value: g.id })),
)

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

function selectItems(field: GrossanlassRoundFormField) {
  return (field.options?.choices || []).map((c) => ({ title: c, value: c }))
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
      payload.new_bauprojekt = {
        name: local.newBauprojektName.trim(),
        parent_id: local.parentId || local.ressortGroupId || '',
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
  if (hasSystemField('period')) {
    const period = periodRef.value?.getRange()
    payload.valid_from = period?.from ?? ''
    payload.valid_to = period?.to ?? ''
  }
  if (hasSystemField('notes')) {
    payload.notes = local.notes.trim() || null
  }

  const cv: Record<string, unknown> = {}
  for (const field of inputFields.value) {
    if (!field.custom_type) continue
    if (field.custom_type === 'date_range') {
      const range = customPeriodRefs[field.id]?.getRange()
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
  bauprojektSearch.value = isBauprojekt && group ? group.name : ''
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

  if (hasSystemField('period')) {
    periodRef.value?.setRange(wish.valid_from, wish.valid_to)
  }

  for (const field of inputFields.value) {
    if (field.custom_type !== 'date_range') continue
    const raw = cv[field.id] as { from?: string; to?: string } | undefined
    customPeriodRefs[field.id]?.setRange(
      raw?.from ?? wish.valid_from,
      raw?.to ?? wish.valid_to,
    )
  }
}

function resetAfterSubmit() {
  local.label = ''
  local.location = ''
  local.notes = ''
  periodRef.value?.reset()
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

watch(
  () => local.ressortGroupId,
  (ressortId) => {
    if (!ressortId || !local.groupId) return
    const branchIds = collectBranchIds(ressortId)
    if (!branchIds.has(local.groupId)) {
      local.groupId = null
      bauprojektSearch.value = ''
    }
  },
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

defineExpose({ buildPayload, resetAfterSubmit, loadFromWish })
</script>

<style scoped>
.form-intro {
  margin: 0 0 14px;
  color: #4b5563;
  font-size: 0.9rem;
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
</style>
