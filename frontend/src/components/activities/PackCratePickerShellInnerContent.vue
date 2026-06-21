<script setup lang="ts">
import { computed, inject, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCratePickerShellInnerContent' })

const props = defineProps<{
  shellPackItem: ActivityPackItem
  expanded?: boolean
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

const sections = computed((): PackCrateShellPeekSection[] => {
  const fn = ctx.peekSectionsForShellPackItem as ((pi: ActivityPackItem) => PackCrateShellPeekSection[]) | undefined
  return fn ? fn(props.shellPackItem) : []
})

const emptyHint = computed(() => {
  const fn = ctx.crateShellPeekEmptyHint as ((p: ActivityPackItem) => string) | undefined
  return fn ? fn(props.shellPackItem) : ''
})

const totalLines = computed(() => sections.value.reduce((n, sec) => n + sec.lines.length, 0))

const subsectionCollapsed = reactive<Record<string, boolean>>({})

function subKey(subsectionKey: string): string {
  return `${props.shellPackItem.id}:${subsectionKey}`
}

function defaultCollapsed(subsectionKey: string): boolean {
  if (totalLines.value <= 1) return false
  if (subsectionKey === 'fixed') return true
  if (subsectionKey === 'extra') return false
  if (subsectionKey === 'all') {
    return sections.value.some((s) => s.subsectionKey === 'fixed' || s.subsectionKey === 'extra')
  }
  return true
}

function isSubCollapsed(subsectionKey: string): boolean {
  const k = subKey(subsectionKey)
  return k in subsectionCollapsed ? subsectionCollapsed[k] : defaultCollapsed(subsectionKey)
}

function toggleSub(subsectionKey: string) {
  subsectionCollapsed[subKey(subsectionKey)] = !isSubCollapsed(subsectionKey)
}

function applyDefaults() {
  for (const sec of sections.value) {
    subsectionCollapsed[subKey(sec.subsectionKey)] = defaultCollapsed(sec.subsectionKey)
  }
}

watch(
  () => props.expanded,
  (exp) => {
    if (exp) applyDefaults()
  },
)

watch(
  () => sections.value,
  () => {
    if (props.expanded) applyDefaults()
  },
)
</script>

<template>
  <div class="pack-crate-picker-shell-inner">
    <PackCrateShellInlinePanel
      v-if="totalLines <= 1"
      :sections="sections"
      :empty-hint="emptyHint"
      separate-section-rows
      :default-expanded="expanded"
    />
    <template v-else>
      <template v-for="sec in sections" :key="'shell-picker-sec-' + sec.subsectionKey">
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
        <ul
          v-show="!isSubCollapsed(sec.subsectionKey)"
          v-if="sec.lines.length > 0"
          class="pack-combo-crate-inline__list pack-combo-crate-inline__list--nested"
        >
          <li
            v-for="line in sec.lines"
            :key="sec.subsectionKey + '-' + line.id"
            class="pack-container-line pack-crate-picker-line"
          >
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ line.materialName }}</span>
              <span
                v-if="line.serialHint"
                class="pack-combo-crate-inline__serial text-muted"
                :title="t('activities.packList.shellForwardSerialCheckTitle')"
              >
                {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
              </span>
              <span class="pack-container-line-qty text-muted">{{
                t('activities.common.piecesShort', { count: line.quantity })
              }}</span>
            </div>
          </li>
        </ul>
      </template>
      <p v-if="totalLines === 0" class="pack-container-empty text-muted">{{ emptyHint }}</p>
    </template>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card-bundle.css"></style>
<style scoped src="@/styles/views/activities/pack-crate-picker.css"></style>
