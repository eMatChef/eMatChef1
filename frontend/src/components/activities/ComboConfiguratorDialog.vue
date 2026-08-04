<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="cancel">
      <div class="modal-dialog combo-config-dialog">
      <div class="ccd-head">
        <h3>{{ t('components.comboConfigurator.title', { name: comboName }) }}</h3>
        <p class="ccd-intro">{{ t('components.comboConfigurator.intro') }}</p>
      </div>

      <div v-if="loading && !availability" class="ccd-loading">
        <div class="spinner spinner-sm"></div>
        <span>{{ t('common.loading') }}</span>
      </div>

      <template v-else-if="availability">
        <!-- Pflicht-Basis fehlt/0 ⇒ ganze Kombo nicht baubar -->
        <p v-if="availability.base.blocked" class="ccd-base-blocked error-text">
          {{ t('components.comboConfigurator.baseBlocked') }}
        </p>

        <!-- Options-Gruppen -->
        <div v-for="group in sortedGroups" :key="group.id" class="ccd-group">
          <div class="ccd-group-head">
            <span class="ccd-group-name">{{ group.name }}</span>
            <span class="ccd-group-rule">{{ groupRuleLabel(group) }}</span>
          </div>
          <p v-if="groupViolation(group)" class="ccd-group-violation">{{ groupViolation(group) }}</p>
          <ul class="ccd-option-list">
            <li v-for="opt in optionsForGroup(group.id)" :key="opt.optionId" class="ccd-option-row">
              <label class="ccd-option-label" :class="{ 'is-disabled': isOptionLocked(opt) }">
                <input
                  v-if="group.selectionType === 'exclusive'"
                  type="radio"
                  :name="`grp-${group.id}`"
                  :checked="isSelected(opt.optionId)"
                  :disabled="isOptionLocked(opt)"
                  @change="selectExclusive(group, opt)"
                />
                <input
                  v-else
                  type="checkbox"
                  :checked="isSelected(opt.optionId)"
                  :disabled="isOptionLocked(opt) || (!isSelected(opt.optionId) && groupAtMax(group))"
                  @change="toggleOption(opt)"
                />
                <span class="ccd-option-name">{{ opt.name }}</span>
                <span class="ccd-option-deltas">{{ deltaLabel(opt.optionId) }}</span>
              </label>
              <span class="ccd-option-state" :class="`ccd-state-${opt.state}`">{{ stateLabel(opt) }}</span>
            </li>
          </ul>
          <button
            v-if="group.selectionType === 'exclusive' && group.minSelect === 0"
            type="button"
            class="ccd-clear-group"
            @click="clearGroup(group)"
          >
            {{ t('components.comboConfigurator.clearChoice') }}
          </button>
        </div>

        <!-- Eigenständige Toggle-Optionen -->
        <div v-if="standaloneToggles.length > 0" class="ccd-group">
          <div class="ccd-group-head">
            <span class="ccd-group-name">{{ t('components.comboConfigurator.extrasTitle') }}</span>
          </div>
          <ul class="ccd-option-list">
            <li v-for="opt in standaloneToggles" :key="opt.optionId" class="ccd-option-row">
              <label class="ccd-option-label" :class="{ 'is-disabled': isOptionLocked(opt) }">
                <input
                  type="checkbox"
                  :checked="isSelected(opt.optionId)"
                  :disabled="isOptionLocked(opt)"
                  @change="toggleOption(opt)"
                />
                <span class="ccd-option-name">{{ opt.name }}</span>
                <span class="ccd-option-deltas">{{ deltaLabel(opt.optionId) }}</span>
              </label>
              <span class="ccd-option-state" :class="`ccd-state-${opt.state}`">{{ stateLabel(opt) }}</span>
            </li>
          </ul>
        </div>

        <!-- Endmenge × Bestellmenge + Verfügbarkeit -->
        <div class="ccd-summary">
          <div class="ccd-qty-row">
            <label>{{ t('components.comboConfigurator.quantityLabel') }}</label>
            <input v-model.number="quantity" type="number" min="1" class="form-input ccd-qty-input" @input="onQuantityChange" />
            <span class="ccd-avail" :class="availClass">
              {{ availabilityLabel }}
            </span>
          </div>

          <div v-if="resolvedStock.length > 0" class="ccd-resolved">
            <span class="ccd-resolved-title">{{ t('components.comboConfigurator.resolvedTitle') }}</span>
            <ul>
              <li v-for="c in resolvedStock" :key="c.materialItemId">
                {{ c.qtyPerCombo }}× {{ c.name }}
                <span class="ccd-resolved-total">→ {{ (c.qtyPerCombo ?? 0) * quantity }}</span>
              </li>
            </ul>
          </div>

          <div v-if="resolvedSelfProvided.length > 0" class="ccd-selfprovided">
            <span class="ccd-resolved-title">{{ t('components.comboConfigurator.selfProvidedTitle') }}</span>
            <ul>
              <li v-for="c in resolvedSelfProvided" :key="c.materialItemId">
                {{ (c.qtyPerCombo ?? 0) * quantity }}× {{ c.name }}
                <span class="text-muted">· {{ t('activities.detail.comboSetSelfProvided') }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pack-Vorgabe + self_provided-Bestätigung -->
        <div v-if="availability" class="ccd-pack-mode">
          <span class="ccd-pack-mode-title">{{ t('components.comboConfigurator.packModeTitle') }}</span>
          <label class="ccd-pack-mode-option">
            <input v-model="packMode" type="radio" value="together" />
            <span>{{ t('components.comboConfigurator.packModeTogether', { name: comboName }) }}</span>
          </label>
          <label class="ccd-pack-mode-option">
            <input v-model="packMode" type="radio" value="loose" />
            <span>{{ t('components.comboConfigurator.packModeLoose') }}</span>
          </label>
          <label v-if="resolvedSelfProvided.length > 0" class="ccd-selfprovided-ack">
            <input v-model="selfProvidedAcknowledged" type="checkbox" />
            <span>{{ t('components.comboConfigurator.selfProvidedAck') }}</span>
          </label>
          <ul v-if="resolvedSelfProvided.length > 0" class="ccd-selfprovided-ack-hint text-muted">
            <li v-for="c in resolvedSelfProvided" :key="'ack-' + c.materialItemId">
              {{ c.qtyPerCombo }}× {{ c.name }}
            </li>
          </ul>
        </div>

        <div v-if="stockImpactIssues.length > 0" class="ccd-stock-impact">
          <p class="ccd-stock-impact-title">{{ t('components.comboConfigurator.stockImpactTitle') }}</p>
          <ul class="ccd-stock-impact-list">
            <li v-for="issue in stockImpactIssues" :key="issue.key">{{ issue.message }}</li>
          </ul>
        </div>
      </template>

      <p v-if="error" class="error-text">{{ error }}</p>

      <div class="modal-actions">
        <button type="button" class="btn-secondary btn-sm" @click="cancel">{{ t('common.cancel') }}</button>
        <button type="button" class="btn-primary btn-sm" :disabled="!canConfirm" @click="confirm">
          {{ t('components.comboConfigurator.confirm', { n: quantity }) }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getConfiguratorAvailability,
  type ConfiguratorAvailability,
  type ConfiguratorAvailabilityGroup,
  type ConfiguratorOptionAvailability,
} from '@/api/materials'

interface Props {
  comboId: string
  comboName: string
  departmentId: string
  activityId?: string | null
  startIso?: string | null
  endIso?: string | null
  initialQuantity?: number
  /** Bestehende Optionswahl (Reconfigure) — sonst Defaults nach erstem Fetch. */
  initialSelectedOptionIds?: string[]
  initialPackMode?: 'together' | 'loose' | null
  initialSelfProvidedAcknowledged?: boolean
  standaloneQuantityByMaterialItemId?: Record<string, number>
}
const props = withDefaults(defineProps<Props>(), {
  activityId: null,
  startIso: null,
  endIso: null,
  initialQuantity: 1,
  initialSelectedOptionIds: () => [],
  initialPackMode: null,
  initialSelfProvidedAcknowledged: false,
  standaloneQuantityByMaterialItemId: () => ({}),
})
const emit = defineEmits<{
  (
    e: 'confirm',
    payload: {
      selectedOptionIds: string[]
      quantity: number
      packMode: 'together' | 'loose'
      selfProvidedAcknowledged: boolean
      resolvedStock: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
      resolvedSelfProvided: Array<{ materialItemId: string; name: string; qtyPerCombo: number }>
    },
  ): void
  (e: 'cancel'): void
}>()

const { t } = useI18n()

const availability = ref<ConfiguratorAvailability | null>(null)
const loading = ref(false)
const error = ref('')
const quantity = ref(Math.max(1, props.initialQuantity ?? 1))
const selected = ref<Set<string>>(
  new Set((props.initialSelectedOptionIds ?? []).filter(Boolean)),
)
const packMode = ref<'together' | 'loose'>(
  props.initialPackMode === 'together' ? 'together' : 'loose',
)
const selfProvidedAcknowledged = ref(Boolean(props.initialSelfProvidedAcknowledged))

const sortedGroups = computed(() => [...(availability.value?.groups ?? [])].sort((a, b) => a.sortOrder - b.sortOrder))

const optionsByGroup = computed(() => {
  const map: Record<string, ConfiguratorOptionAvailability[]> = {}
  for (const o of availability.value?.options ?? []) {
    const key = o.optionGroupId ?? '__standalone__'
    if (!map[key]) map[key] = []
    map[key].push(o)
  }
  return map
})

function optionsForGroup(groupId: string): ConfiguratorOptionAvailability[] {
  return optionsByGroup.value[groupId] ?? []
}
const standaloneToggles = computed(() => optionsByGroup.value['__standalone__'] ?? [])

function isSelected(optionId: string): boolean {
  return selected.value.has(optionId)
}

function isOptionLocked(opt: ConfiguratorOptionAvailability): boolean {
  // Hart gesperrt: 0 frei (blocked) oder nicht im Bestand (missing). self_provided/Abzüge → available.
  return opt.state === 'blocked' || opt.state === 'missing'
}

function groupAtMax(group: ConfiguratorAvailabilityGroup): boolean {
  if (group.maxSelect == null) return false
  return selectedCountInGroup(group.id) >= group.maxSelect
}

function selectedCountInGroup(groupId: string): number {
  return optionsForGroup(groupId).filter((o) => selected.value.has(o.optionId)).length
}

function selectExclusive(group: ConfiguratorAvailabilityGroup, opt: ConfiguratorOptionAvailability) {
  for (const o of optionsForGroup(group.id)) {
    selected.value.delete(o.optionId)
  }
  selected.value.add(opt.optionId)
  refresh()
}

function clearGroup(group: ConfiguratorAvailabilityGroup) {
  for (const o of optionsForGroup(group.id)) {
    selected.value.delete(o.optionId)
  }
  refresh()
}

function toggleOption(opt: ConfiguratorOptionAvailability) {
  if (selected.value.has(opt.optionId)) {
    selected.value.delete(opt.optionId)
  } else {
    selected.value.add(opt.optionId)
  }
  refresh()
}

function groupRuleLabel(group: ConfiguratorAvailabilityGroup): string {
  if (group.selectionType === 'exclusive') {
    return group.minSelect >= 1 ? t('components.comboConfigurator.rulePickOne') : t('components.comboConfigurator.rulePickOneOpt')
  }
  const min = group.minSelect
  const max = group.maxSelect
  if (max != null) return t('components.comboConfigurator.ruleRange', { min, max })
  return t('components.comboConfigurator.ruleMin', { min })
}

function groupViolation(group: ConfiguratorAvailabilityGroup): string | null {
  const count = selectedCountInGroup(group.id)
  if (count < group.minSelect) {
    return t('components.comboConfigurator.violationMin', { min: group.minSelect })
  }
  if (group.maxSelect != null && count > group.maxSelect) {
    return t('components.comboConfigurator.violationMax', { max: group.maxSelect })
  }
  // Exklusive Gruppe: gewählte Option nicht verfügbar → zur Alternative lenken
  if (group.selectionType === 'exclusive') {
    const chosen = optionsForGroup(group.id).find((o) => selected.value.has(o.optionId))
    if (chosen && isOptionLocked(chosen)) {
      return t('components.comboConfigurator.violationPickAlternative')
    }
  }
  return null
}

function stateLabel(opt: ConfiguratorOptionAvailability): string {
  if (opt.state === 'missing') return t('components.comboConfigurator.stateMissing')
  if (opt.state === 'blocked') return t('components.comboConfigurator.stateBlocked')
  if (opt.buildable != null) return t('components.comboConfigurator.stateAvailableN', { n: opt.buildable })
  return t('components.comboConfigurator.stateAvailable')
}

function deltaLabel(optionId: string): string {
  const opt = (availability.value?.options ?? []).find((o) => o.optionId === optionId)
  if (!opt || opt.addedStockComponents.length === 0) return ''
  return opt.addedStockComponents.map((c) => `+${c.qtyDelta} ${c.name}`).join(', ')
}

const resolvedStock = computed(() => availability.value?.selected.components ?? [])

const resolvedSelfProvided = computed(
  () => availability.value?.selected.selfProvided ?? [],
)

const stockImpactIssues = computed(() => {
  const issues: Array<{ key: string; message: string }> = []
  const qty = Math.max(1, quantity.value)
  const standalone = props.standaloneQuantityByMaterialItemId ?? {}
  for (const c of resolvedStock.value) {
    const need = Math.max(0, (c.qtyPerCombo ?? 0) * qty)
    if (need <= 0) continue
    const loose = Math.max(0, standalone[c.materialItemId] ?? 0)
    const avail = Math.max(0, c.availableForPeriod ?? 0)
    if (loose > 0) {
      issues.push({
        key: `${c.materialItemId}-overlap`,
        message: t('components.comboConfigurator.stockImpactOverlap', {
          name: c.name,
          loose,
          need,
        }),
      })
    }
    const totalIfSeparate = loose + need
    if (totalIfSeparate > avail) {
      issues.push({
        key: `${c.materialItemId}-shortage`,
        message: t('components.comboConfigurator.stockImpactShortage', {
          name: c.name,
          total: totalIfSeparate,
          avail,
        }),
      })
    }
  }
  return issues
})

const needsSelfProvidedAck = computed(() => resolvedSelfProvided.value.length > 0)

const buildable = computed(() => availability.value?.selected.buildable ?? 0)
const selectedBlocked = computed(() => availability.value?.selected.blocked ?? true)

const availabilityLabel = computed(() => {
  if (!availability.value) return ''
  const b = buildable.value ?? 0
  return t('components.comboConfigurator.availableN', { n: b })
})
const availClass = computed(() => ((buildable.value ?? 0) >= quantity.value && !selectedBlocked.value ? 'text-green' : 'text-red'))

const groupsValid = computed(() => {
  for (const g of sortedGroups.value) {
    const count = selectedCountInGroup(g.id)
    if (count < g.minSelect) return false
    if (g.maxSelect != null && count > g.maxSelect) return false
    const chosen = optionsForGroup(g.id).find((o) => selected.value.has(o.optionId))
    if (chosen && isOptionLocked(chosen)) return false
  }
  return true
})

const canConfirm = computed(() => {
  if (!availability.value) return false
  if (availability.value.base.blocked) return false
  if (selectedBlocked.value) return false
  if (quantity.value < 1) return false
  if ((buildable.value ?? 0) < quantity.value) return false
  if (needsSelfProvidedAck.value && !selfProvidedAcknowledged.value) return false
  return groupsValid.value
})

let refreshToken = 0
async function refresh() {
  const token = ++refreshToken
  loading.value = true
  error.value = ''
  try {
    const data = await getConfiguratorAvailability(props.comboId, {
      startDate: props.startIso,
      endDate: props.endIso,
      quantity: quantity.value,
      excludeActivityId: props.activityId,
      selectedOptionIds: [...selected.value],
    })
    if (token !== refreshToken) return
    availability.value = data
    // Gesperrte gewählte Optionen aus der Auswahl entfernen (graceful) – nur Toggles/Multi.
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err?.response?.data?.error ?? t('components.comboConfigurator.loadError')
  } finally {
    if (token === refreshToken) loading.value = false
  }
}

let qtyTimer: ReturnType<typeof setTimeout> | null = null
function onQuantityChange() {
  if (quantity.value < 1) quantity.value = 1
  if (qtyTimer) clearTimeout(qtyTimer)
  qtyTimer = setTimeout(() => void refresh(), 250)
}

function confirm() {
  if (!canConfirm.value) return
  const resolvedStock = (availability.value?.selected.components ?? []).map((c) => ({
    materialItemId: c.materialItemId,
    name: c.name,
    qtyPerCombo: c.qtyPerCombo ?? 0,
  }))
  emit('confirm', {
    selectedOptionIds: [...selected.value],
    quantity: quantity.value,
    packMode: packMode.value,
    selfProvidedAcknowledged: needsSelfProvidedAck.value ? selfProvidedAcknowledged.value : false,
    resolvedStock,
    resolvedSelfProvided: (availability.value?.selected.selfProvided ?? []).map((c) => ({
      materialItemId: c.materialItemId,
      name: c.name,
      qtyPerCombo: c.qtyPerCombo ?? 0,
    })),
  })
}
function cancel() {
  emit('cancel')
}

onMounted(async () => {
  await refresh()
  const hasInitial = (props.initialSelectedOptionIds ?? []).length > 0
  if (!hasInitial) {
    for (const o of availability.value?.options ?? []) {
      if (o.defaultSelected && !isOptionLocked(o)) {
        selected.value.add(o.optionId)
      }
    }
  } else {
    // Ungültige / gesperrte Initial-Optionen entfernen, damit Confirm möglich bleibt.
    for (const id of [...selected.value]) {
      const opt = (availability.value?.options ?? []).find((o) => o.optionId === id)
      if (!opt || isOptionLocked(opt)) selected.value.delete(id)
    }
  }
  if (selected.value.size > 0) {
    await refresh()
  }
})
</script>

<style scoped>
.combo-config-dialog { max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto; }
.ccd-head h3 { margin: 0; font-size: 1.05rem; }
.ccd-intro { font-size: 0.82rem; color: var(--text-muted, #6b7280); margin: 0.25rem 0 0.75rem; }
.ccd-loading { display: flex; gap: 0.5rem; align-items: center; padding: 1rem 0; }
.ccd-base-blocked { font-weight: 600; }
.ccd-group { border: 1px solid var(--border-color, #e5e7eb); border-radius: 8px; padding: 0.6rem 0.75rem; margin-bottom: 0.6rem; }
.ccd-group-head { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.ccd-group-name { font-weight: 600; font-size: 0.9rem; }
.ccd-group-rule { font-size: 0.75rem; color: var(--text-muted, #6b7280); }
.ccd-group-violation { font-size: 0.75rem; color: #b45309; margin: 0.25rem 0 0; }
.ccd-option-list { list-style: none; margin: 0.4rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.3rem; }
.ccd-option-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.ccd-option-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; flex: 1; }
.ccd-option-label.is-disabled { opacity: 0.55; cursor: not-allowed; }
.ccd-option-name { font-size: 0.86rem; font-weight: 500; }
.ccd-option-deltas { font-size: 0.75rem; color: var(--text-muted, #6b7280); }
.ccd-option-state { font-size: 0.72rem; padding: 0.05rem 0.45rem; border-radius: 999px; white-space: nowrap; }
.ccd-state-available { background: #dcfce7; color: #166534; }
.ccd-state-blocked { background: #fee2e2; color: #991b1b; }
.ccd-state-missing { background: #f3f4f6; color: #6b7280; }
.ccd-clear-group { margin-top: 0.35rem; background: none; border: none; color: #6b7280; font-size: 0.75rem; cursor: pointer; text-decoration: underline; }
.ccd-summary { margin-top: 0.5rem; border-top: 1px solid var(--border-color, #e5e7eb); padding-top: 0.6rem; }
.ccd-qty-row { display: flex; align-items: center; gap: 0.6rem; }
.ccd-qty-input { width: 90px; }
.ccd-avail { font-weight: 600; font-size: 0.9rem; }
.ccd-resolved, .ccd-selfprovided { margin-top: 0.5rem; font-size: 0.8rem; }
.ccd-pack-mode { margin-top: 0.75rem; padding-top: 0.6rem; border-top: 1px solid var(--border-color, #e5e7eb); }
.ccd-pack-mode-title { display: block; font-weight: 600; font-size: 0.86rem; margin-bottom: 0.4rem; }
.ccd-pack-mode-option { display: flex; align-items: flex-start; gap: 0.5rem; margin: 0.3rem 0; font-size: 0.84rem; cursor: pointer; }
.ccd-selfprovided-ack { display: flex; align-items: flex-start; gap: 0.5rem; margin-top: 0.6rem; font-size: 0.84rem; cursor: pointer; }
.ccd-selfprovided-ack-hint { margin: 0.25rem 0 0 1.4rem; padding: 0; font-size: 0.78rem; }
.ccd-resolved-title { font-weight: 600; }
.ccd-resolved ul, .ccd-selfprovided ul { margin: 0.2rem 0 0; padding-left: 1.1rem; }
.ccd-resolved-total { color: var(--text-muted, #6b7280); }
.ccd-stock-impact {
  margin-top: 0.75rem;
  padding: 0.65rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #fcd34d;
  background: #fffbeb;
}
.ccd-stock-impact-title { margin: 0 0 0.35rem; font-weight: 600; font-size: 0.84rem; color: #92400e; }
.ccd-stock-impact-list { margin: 0; padding-left: 1.1rem; font-size: 0.8rem; color: #78350f; }
.text-green { color: #16a34a; }
.text-red { color: #dc2626; }
</style>
