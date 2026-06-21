<script setup lang="ts">
import { computed, inject, reactive, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackCratePickerInnerContent from '@/components/activities/PackCratePickerInnerContent.vue'
import PackCratePickerShellInnerContent from '@/components/activities/PackCratePickerShellInnerContent.vue'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import { packShellContainerForPackItem } from '@/components/activities/packShellCrateHelpers'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCrateTargetPicker' })

type ActivePackTarget =
  | { kind: 'loose' }
  | { kind: 'container'; containerId: string }
  | { kind: 'combo'; packItemId: string }
  | null

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

function injectRef<T>(raw: unknown): T {
  return unref(raw as Ref<T> | T)
}

const packListEditable = computed(() => Boolean(injectRef(ctx.packListEditable)))
const canManageMaterials = computed(() => Boolean(injectRef(ctx.canManageMaterials)))
const packContainers = computed(() => {
  const list = injectRef<ActivityPackContainer[] | undefined>(ctx.packContainers)
  return Array.isArray(list) ? list : []
})
const stageRightCrateShellItems = computed(() => {
  const list = injectRef<ActivityPackItem[] | undefined>(ctx.stageRightCrateShellItems)
  return Array.isArray(list) ? list : []
})
const activePackTarget = computed(() => injectRef<ActivePackTarget>(ctx.activePackTarget))

const expandedByCrateId = reactive<Record<string, boolean>>({})
const expandedByShellId = reactive<Record<string, boolean>>({})

function isCrateAtEvent(containerId: string): boolean {
  const fn = ctx.containerHasIssuedAtEvent as ((id: string) => boolean) | undefined
  return fn?.(containerId) ?? false
}

const sortedCrates = computed(() =>
  [...packContainers.value].sort((a, b) => {
    const aAt = isCrateAtEvent(a.id) ? 0 : 1
    const bAt = isCrateAtEvent(b.id) ? 0 : 1
    if (aAt !== bAt) return aAt - bAt
    return crateDisplayLabel(a).localeCompare(crateDisplayLabel(b), 'de')
  }),
)

/** Phys.-Kombi rechts «Gepackt», noch ohne Pack-Behälter-Zeile */
const shellOnlyPackItems = computed(() =>
  stageRightCrateShellItems.value.filter(
    (pi) => packShellContainerForPackItem(pi, packContainers.value) == null,
  ),
)

const shellPackItemForContainer = computed(
  () => ctx.shellPackItemForContainer as ((containerId: string) => ActivityPackItem | undefined) | undefined,
)

function crateDisplayLabel(c: ActivityPackContainer): string {
  const fn = shellPackItemForContainer.value
  const sh = fn ? fn(c.id) : undefined
  if (sh?.materialName?.trim()) return sh.materialName.trim()
  return c.label
}

function isShellLinkedContainer(c: ActivityPackContainer): boolean {
  const fn = shellPackItemForContainer.value
  const sh = fn ? fn(c.id) : undefined
  return sh != null && isPhysicalComboPackItem(sh)
}

function itemCount(containerId: string): number {
  return (ctx.containerItemCount as (id: string) => number)(containerId)
}

function shellItemCount(pi: ActivityPackItem): number {
  const fn = ctx.peekSectionsForShellPackItem as ((p: ActivityPackItem) => { lines: unknown[] }[]) | undefined
  if (!fn) return 0
  return fn(pi).reduce((n, sec) => n + sec.lines.length, 0)
}

function isCrateSelected(id: string): boolean {
  const tgt = activePackTarget.value
  return tgt?.kind === 'container' && tgt.containerId === id
}

function isComboSelected(packItemId: string): boolean {
  const tgt = activePackTarget.value
  return tgt?.kind === 'combo' && tgt.packItemId === packItemId
}

function isLooseSelected(): boolean {
  return activePackTarget.value?.kind === 'loose'
}

function isCrateExpanded(id: string): boolean {
  return expandedByCrateId[id] === true
}

function isShellExpanded(packItemId: string): boolean {
  return expandedByShellId[packItemId] === true
}

function toggleCrateExpanded(id: string) {
  expandedByCrateId[id] = !isCrateExpanded(id)
}

function toggleShellExpanded(packItemId: string) {
  expandedByShellId[packItemId] = !isShellExpanded(packItemId)
}

function selectLoose() {
  if (!packListEditable.value) return
  ;(ctx.toggleActiveLoose as () => void)()
}

function selectCrate(id: string) {
  if (!packListEditable.value) return
  ;(ctx.toggleActiveContainer as (containerId: string) => void)(id)
}

function selectCombo(packItemId: string) {
  if (!packListEditable.value) return
  ;(ctx.toggleActiveCombo as (packItemId: string) => void)(packItemId)
}

const pickerHasEntries = computed(
  () => sortedCrates.value.length > 0 || shellOnlyPackItems.value.length > 0,
)
</script>

<template>
  <div class="pack-crate-picker-block">
    <div class="pack-crate-picker-head">
      <h3 class="pack-crate-picker-title">{{ t('activities.packList.sectionKisten') }}</h3>
      <p v-if="packListEditable" class="pack-crate-picker-hint text-muted">
        {{ t('activities.packList.selectCrateHint') }}
      </p>
    </div>
    <div class="pack-crate-picker-list" role="listbox" :aria-label="t('activities.packList.ariaCratePicker')">
      <button
        v-if="packListEditable"
        type="button"
        role="option"
        class="pack-target-loose pack-crate-picker-loose"
        :class="{ 'pack-target-loose--active': isLooseSelected() }"
        :aria-selected="isLooseSelected()"
        :title="t('activities.packList.targetLooseTitle')"
        @click="selectLoose"
      >
        {{ t('activities.packList.sectionLoose') }}
      </button>

      <!-- Phys.-Kombi ohne Behälter-Zeile (z. B. Kochkiste frisch gepackt) -->
      <div
        v-for="pi in shellOnlyPackItems"
        :key="'shell-picker-' + pi.id"
        class="pack-container-card pack-crate-picker-card pack-container-card--shell"
        :class="{
          'pack-container-card--target': isComboSelected(pi.id),
          'pack-container-card--selectable': packListEditable,
        }"
      >
        <div class="pack-container-header-row">
          <button
            type="button"
            class="pack-container-chevron-btn"
            :aria-expanded="isShellExpanded(pi.id)"
            :aria-label="t('activities.packList.cratePickerExpandAria', { label: pi.materialName })"
            @click.stop="toggleShellExpanded(pi.id)"
          >
            <span
              class="pack-container-chevron"
              :class="{ 'pack-container-chevron--open': isShellExpanded(pi.id) }"
              aria-hidden="true"
            >▶</span>
          </button>
          <div class="pack-container-header-main">
            <button
              type="button"
              role="option"
              class="pack-container-select-main"
              :aria-selected="isComboSelected(pi.id)"
              :title="t('activities.packList.targetCrateSelectTitle')"
              :disabled="!packListEditable"
              @click.stop="selectCombo(pi.id)"
            >
              <span class="pack-container-name">{{ pi.materialName }}</span>
              <span
                class="pack-combo-badge"
                :title="t('activities.detail.comboPhysicalTitle')"
                >{{ t('activities.detail.comboPhysicalShort') }}</span
              >
            </button>
            <div class="pack-container-header-meta">
              <span class="pack-container-chip text-muted">{{
                t('activities.common.itemsUnit', { count: shellItemCount(pi) })
              }}</span>
            </div>
          </div>
        </div>
        <div v-show="isShellExpanded(pi.id)" class="pack-container-inner pack-crate-picker-inner">
          <PackCratePickerShellInnerContent :shell-pack-item="pi" :expanded="isShellExpanded(pi.id)" />
        </div>
      </div>

      <div
        v-for="c in sortedCrates"
        :key="c.id"
        class="pack-container-card pack-crate-picker-card"
        :class="{
          'pack-container-card--target': isCrateSelected(c.id),
          'pack-container-card--selectable': packListEditable,
          'pack-container-card--shell': isShellLinkedContainer(c),
        }"
      >
        <div class="pack-container-header-row">
          <button
            type="button"
            class="pack-container-chevron-btn"
            :aria-expanded="isCrateExpanded(c.id)"
            :aria-label="t('activities.packList.cratePickerExpandAria', { label: crateDisplayLabel(c) })"
            @click.stop="toggleCrateExpanded(c.id)"
          >
            <span
              class="pack-container-chevron"
              :class="{ 'pack-container-chevron--open': isCrateExpanded(c.id) }"
              aria-hidden="true"
            >▶</span>
          </button>
          <div class="pack-container-header-main">
            <button
              type="button"
              role="option"
              class="pack-container-select-main"
              :aria-selected="isCrateSelected(c.id)"
              :title="t('activities.packList.targetCrateSelectTitle')"
              :disabled="!packListEditable"
              @click.stop="selectCrate(c.id)"
            >
              <span class="pack-container-name">{{ crateDisplayLabel(c) }}</span>
              <span
                v-if="isShellLinkedContainer(c)"
                class="pack-combo-badge"
                :title="t('activities.detail.comboPhysicalTitle')"
                >{{ t('activities.detail.comboPhysicalShort') }}</span
              >
            </button>
            <div class="pack-container-header-meta">
              <span
                v-if="isCrateAtEvent(c.id)"
                class="pack-container-chip pack-container-chip--at-event"
                >{{ t('activities.packList.crateAtEventBadge') }}</span
              >
              <span class="pack-container-chip text-muted">{{
                t('activities.common.itemsUnit', { count: itemCount(c.id) })
              }}</span>
            </div>
          </div>
        </div>
        <div v-show="isCrateExpanded(c.id)" class="pack-container-inner pack-crate-picker-inner">
          <PackCratePickerInnerContent :container="c" :expanded="isCrateExpanded(c.id)" />
        </div>
      </div>
    </div>
    <p
      v-if="!pickerHasEntries && canManageMaterials"
      class="pack-crate-picker-empty text-muted"
    >
      {{ t('activities.packList.hintNoCratesPicker') }}
    </p>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card-bundle.css"></style>
<style scoped src="@/styles/views/activities/pack-crate-picker.css"></style>
