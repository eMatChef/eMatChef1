<script setup lang="ts">
import { computed, inject, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { PackContainerItemSection } from '@/components/activities/packShellCrateHelpers'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCratePickerInnerContent' })

const props = defineProps<{
  container: ActivityPackContainer
  expanded?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const packListEditable = computed(() => Boolean(ctx.packListEditable))

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

watch(
  () => props.expanded,
  (exp) => {
    if (exp) applyPickerDefaults()
  },
)
</script>

<template>
  <div class="pack-crate-picker-inner-content">
    <template v-if="totalLines <= 1">
      <div v-if="flatLines.length > 0" class="pack-crate-picker-flat-lines">
        <div
          v-for="ci in flatLines"
          :key="ci.id"
          class="pack-container-line pack-crate-picker-line"
        >
          <div class="pack-container-line-main">
            <span class="pack-container-line-name">{{
              ci.material_name || t('activities.common.material')
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
          <div
            v-for="ci in sec.lines"
            :key="sec.subsectionKey + '-' + ci.id"
            class="pack-container-line pack-crate-picker-line"
          >
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{
                ci.material_name || t('activities.common.material')
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
        :disabled="Boolean(ctx.containerMutationLoading)"
        @click.stop="(ctx.confirmDeleteContainer as (container: ActivityPackContainer) => void)(container)"
      >
        {{ t('activities.packList.deleteContainer') }}
      </button>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card.css"></style>
<style scoped src="@/styles/views/activities/pack-crate-picker.css"></style>
