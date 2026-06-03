<script setup lang="ts">
import { computed, inject, reactive, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { PackContainerItemSection } from '@/components/activities/packShellCrateHelpers'
import {
  injectPackCtxBool,
  PACK_WAREHOUSE_ISSUE_INJECT_KEY,
} from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCratePickerInnerContent' })

const props = defineProps<{
  container: ActivityPackContainer
  expanded?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const packListEditable = computed(() => injectPackCtxBool(ctx, 'packListEditable'))
const containerMutationLoading = computed(() => injectPackCtxBool(ctx, 'containerMutationLoading'))

const sections = computed((): PackContainerItemSection[] => {
  const fn = ctx.packContainerItemSections as ((c: ActivityPackContainer) => PackContainerItemSection[]) | undefined
  return fn ? fn(props.container) : []
})

const totalLines = computed(() =>
  sections.value.reduce((n, sec) => n + sec.lines.length, 0),
)

const flatLines = computed((): ActivityPackContainerItem[] =>
  sections.value.flatMap((sec) => sec.lines),
)

/** Nur im Picker: eigener Aufklapp-Status (Fix zu, Zusatz offen). */
const subsectionCollapsed = reactive<Record<string, boolean>>({})

function subKey(subsectionKey: string): string {
  return `${props.container.id}:${subsectionKey}`
}

function defaultCollapsed(subsectionKey: string): boolean {
  if (totalLines.value <= 1) return false
  if (subsectionKey === 'fixed') return true
  if (subsectionKey === 'extra') return false
  if (subsectionKey === 'all') {
    const hasFixExtra = sections.value.some(
      (s) => s.subsectionKey === 'fixed' || s.subsectionKey === 'extra',
    )
    return hasFixExtra
  }
  return true
}

function isSubCollapsed(subsectionKey: string): boolean {
  const k = subKey(subsectionKey)
  return k in subsectionCollapsed ? subsectionCollapsed[k] : defaultCollapsed(subsectionKey)
}

function toggleSub(subsectionKey: string) {
  const k = subKey(subsectionKey)
  subsectionCollapsed[k] = !isSubCollapsed(subsectionKey)
}

function applyPickerDefaults() {
  for (const sec of sections.value) {
    subsectionCollapsed[subKey(sec.subsectionKey)] = defaultCollapsed(sec.subsectionKey)
  }
}

function pullQtyInputsRef(): Ref<Record<string, number>> {
  return ctx.containerPullQtyInputs as Ref<Record<string, number>>
}

function allPickerLines(): ActivityPackContainerItem[] {
  return sections.value.flatMap((sec) => sec.lines)
}

function ensurePullQtyDefaults(): void {
  const ref = pullQtyInputsRef()
  const next = { ...ref.value }
  let changed = false
  for (const ci of allPickerLines()) {
    if (isNonActionable(ci)) continue
    const k = pullKey(ci)
    const packed = Math.max(0, Math.floor(ci.quantity_packed ?? 0) || 0)
    if (packed < 1) continue
    if (next[k] == null || !Number.isFinite(Number(next[k])) || Number(next[k]) < 1) {
      next[k] = packed
      changed = true
    }
  }
  if (changed) ref.value = next
}

watch(
  () => props.expanded,
  (exp) => {
    if (exp) {
      applyPickerDefaults()
      ensurePullQtyDefaults()
    }
  },
)

watch(
  () => allPickerLines().map((ci) => `${ci.id}:${ci.quantity_packed}`).join('|'),
  () => {
    if (props.expanded) ensurePullQtyDefaults()
  },
)

function isNonActionable(ci: ActivityPackContainerItem): boolean {
  return (ctx.isVirtualWarehouseContainerLine as (row: ActivityPackContainerItem) => boolean)(ci)
}

function pullKey(ci: ActivityPackContainerItem): string {
  return (ctx.containerPullKey as (containerId: string, itemId: string) => string)(
    props.container.id,
    ci.id,
  )
}

function pullFromContainer(ci: ActivityPackContainerItem): void {
  ;(ctx.pullFromContainer as (containerId: string, row: ActivityPackContainerItem) => void)(
    props.container.id,
    ci,
  )
}

function pullQtyFor(ci: ActivityPackContainerItem): number {
  const packed = Math.max(1, Math.floor(ci.quantity_packed ?? 0) || 0)
  const k = pullKey(ci)
  const stored = pullQtyInputsRef().value[k]
  if (stored != null && Number.isFinite(Number(stored)) && Number(stored) >= 1) {
    return Math.min(Math.floor(Number(stored)), packed)
  }
  return packed
}

function setPullQty(ci: ActivityPackContainerItem, raw: string | number): void {
  const packed = Math.max(1, Math.floor(ci.quantity_packed ?? 0) || 0)
  let qty = Math.floor(Number(raw)) || 0
  if (qty < 1) qty = 1
  qty = Math.min(qty, packed)
  const ref = pullQtyInputsRef()
  ref.value = { ...ref.value, [pullKey(ci)]: qty }
}
</script>

<template>
  <div class="pack-crate-picker-inner-content">
    <template v-if="totalLines <= 1">
      <div v-if="flatLines.length > 0" class="pack-crate-picker-flat-lines">
        <div
          v-for="ci in flatLines"
          :key="ci.id"
          class="pack-container-line pack-crate-picker-line pack-container-line--stacked"
        >
          <div
            v-if="packListEditable && !isNonActionable(ci) && (ci.quantity_packed ?? 0) > 0"
            class="pack-card-actions pack-card-actions-left"
            @click.stop
          >
            <button
              type="button"
              class="btn-moveback-arrow"
              :disabled="containerMutationLoading"
              :title="t('activities.packList.pullFromContainerTitle')"
              @click="pullFromContainer(ci)"
            >
              <v-icon icon="mdi-arrow-left" size="12" />
            </button>
            <input
              :value="pullQtyFor(ci)"
              type="number"
              min="1"
              :max="ci.quantity_packed"
              class="pack-moveback-input"
              @input="setPullQty(ci, ($event.target as HTMLInputElement).value)"
              @keyup.enter="pullFromContainer(ci)"
            />
          </div>
          <div class="pack-container-line-main">
            <span class="pack-container-line-name">{{
              ci.material_name || t('common.material')
            }}</span>
            <span class="pack-container-line-qty text-muted">{{
              t('activities.packList.qtyInContainerLine', { n: ci.quantity_packed ?? 0 })
            }}</span>
          </div>
        </div>
      </div>
      <p v-else class="pack-container-empty text-muted">
        {{ t('activities.packList.nothingAssigned') }}
      </p>
    </template>

    <template v-else>
      <template v-for="sec in sections" :key="'picker-sec-' + sec.subsectionKey">
        <button
          type="button"
          class="pack-container-subsection-toggle"
          :aria-expanded="!isSubCollapsed(sec.subsectionKey)"
          :aria-label="`${sec.title} — ${t('activities.packList.cratePeekRowToggleAria')}`"
          @click.stop="toggleSub(sec.subsectionKey)"
        >
          <span class="pack-container-chevron pack-container-chevron--subsection" aria-hidden="true">
            <svg
              v-if="isSubCollapsed(sec.subsectionKey)"
              class="pack-container-subsection-chevron-svg"
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
            <svg
              v-else
              class="pack-container-subsection-chevron-svg"
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </span>
          <span class="pack-container-subsection-toggle-label">{{ sec.title }}</span>
          <span
            v-if="sec.lines.length > 0"
            class="pack-container-chip pack-container-chip--subsection text-muted"
            >{{ t('activities.common.itemsUnit', { count: sec.lines.length }) }}</span
          >
        </button>
        <div
          v-show="!isSubCollapsed(sec.subsectionKey)"
          class="pack-container-subsection-lines"
        >
          <p
            v-if="sec.lines.length === 0 && sec.subsectionKey === 'extra'"
            class="pack-container-empty text-muted"
          >
            {{ t('activities.packList.cratePickerExtraEmpty') }}
          </p>
          <div
            v-for="ci in sec.lines"
            :key="sec.subsectionKey + '-' + ci.id"
            class="pack-container-line pack-crate-picker-line pack-container-line--stacked"
          >
            <div
              v-if="packListEditable && !isNonActionable(ci) && (ci.quantity_packed ?? 0) > 0"
              class="pack-card-actions pack-card-actions-left"
              @click.stop
            >
              <button
                type="button"
                class="btn-moveback-arrow"
                :disabled="containerMutationLoading"
                :title="t('activities.packList.pullFromContainerTitle')"
                @click="pullFromContainer(ci)"
              >
                <v-icon icon="mdi-arrow-left" size="12" />
              </button>
              <input
                :value="pullQtyFor(ci)"
                type="number"
                min="1"
                :max="ci.quantity_packed"
                class="pack-moveback-input"
                @input="setPullQty(ci, ($event.target as HTMLInputElement).value)"
                @keyup.enter="pullFromContainer(ci)"
              />
            </div>
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{
                ci.material_name || t('common.material')
              }}</span>
              <span class="pack-container-line-qty text-muted">{{
                t('activities.packList.qtyInContainerLine', { n: ci.quantity_packed ?? 0 })
              }}</span>
            </div>
          </div>
        </div>
      </template>
      <p v-if="totalLines === 0" class="pack-container-empty text-muted">
        {{ t('activities.packList.nothingAssigned') }}
      </p>
    </template>

    <div v-if="packListEditable" class="pack-crate-picker-inner-footer">
      <button
        type="button"
        class="pack-container-delete"
        :disabled="containerMutationLoading"
        @click.stop="(ctx.confirmDeleteContainer as (container: ActivityPackContainer) => void)(container)"
      >
        {{ t('activities.packList.deleteContainer') }}
      </button>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
<style scoped src="@/styles/views/activities/pack-crate-picker.css"></style>
