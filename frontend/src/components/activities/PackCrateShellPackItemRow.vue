<script setup lang="ts">
import { IconArrowRight } from '@/components/icons'
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCrateShellPackItemRow' })

const props = defineProps<{
  shellPackItem: ActivityPackItem
  stageRightLabel: string
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown> &
  Record<string, unknown>

const collapseKey = computed(() => `shell-pack-${props.shellPackItem.id}`)

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(collapseKey.value),
)

const shellPeekSections = computed((): PackCrateShellPeekSection[] => {
  const fn = ctx.peekSectionsForShellPackItem as ((pi: ActivityPackItem) => PackCrateShellPeekSection[]) | undefined
  return fn ? fn(props.shellPackItem) : []
})

const shellPeekEmptyHint = computed(() => {
  const fn = ctx.crateShellPeekEmptyHint as ((pi: ActivityPackItem) => string) | undefined
  return fn ? fn(props.shellPackItem) : ''
})

const shellCanMoveForward = computed(() => {
  const fn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  return fn ? fn(props.shellPackItem) > 0 : false
})

function moveShellCrateForward() {
  const maxFn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  const moveFn = ctx.moveToNextStage as ((p: ActivityPackItem, qty?: number) => void | Promise<void>) | undefined
  if (!maxFn || !moveFn) return
  const max = maxFn(props.shellPackItem)
  if (max < 1) return
  void moveFn(props.shellPackItem, max)
}
</script>

<template>
  <div
    :id="'pack-shell-row-' + shellPackItem.id"
    class="pack-container-card pack-container-card--shell"
  >
    <div class="pack-container-header-row">
      <button
        type="button"
        class="pack-container-chevron-btn"
        :aria-expanded="innerVisible"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click.stop="(ctx.togglePackContainerCollapsed as (id: string) => void)(collapseKey)"
      >
        <span class="pack-container-chevron" aria-hidden="true">{{ innerVisible ? '▼' : '▶' }}</span>
      </button>
      <div class="pack-container-header-main">
        <div class="pack-container-header-title-block pack-container-header-title-block--shell">
          <span class="pack-container-name">{{ shellPackItem.materialName }}</span>
          <span class="pack-combo-badge" :title="t('activities.detail.comboPhysicalTitle')">{{
            t('activities.detail.comboPhysicalShort')
          }}</span>
        </div>
      </div>
      <div v-if="ctx.packListEditable && shellCanMoveForward" class="pack-container-header-actions" @click.stop>
        <button
          type="button"
          class="btn-move-arrow btn-move-arrow--container-header"
          :disabled="ctx.movingId === shellPackItem.id"
          :title="
            t('activities.packList.shellMoveWholeCrateTitle', {
              stage: stageRightLabel,
            })
          "
          @click="moveShellCrateForward"
        >
          <IconArrowRight />
        </button>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner pack-container-inner--shell">
      <div
        v-if="shellPackItem.storageAddressName || shellPackItem.storageSlotName || shellPackItem.linkedContainerLabel"
        class="pack-shell-storage text-muted"
      >
        <div v-if="shellPackItem.linkedContainerLabel">
          {{ t('activities.packList.kisteLabel', { label: shellPackItem.linkedContainerLabel }) }}
        </div>
        <div v-if="shellPackItem.storageAddressName">
          {{ t('activities.packList.storageLabel', { name: shellPackItem.storageAddressName }) }}
        </div>
        <div v-if="shellPackItem.storageSlotName">
          {{ t('activities.packList.slotLabel', { name: shellPackItem.storageSlotName }) }}
        </div>
      </div>
      <PackCrateShellInlinePanel
        :sections="shellPeekSections"
        :empty-hint="shellPeekEmptyHint"
        separate-section-rows
        :default-expanded="innerVisible"
      />
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
<style scoped>
.pack-container-card--shell {
  margin-bottom: 0;
}

.pack-container-header-title-block--shell {
  padding: 10px 8px 10px 4px;
}

.pack-container-inner--shell {
  padding-top: 8px;
}

.pack-shell-storage {
  font-size: 12px;
  line-height: 1.45;
  margin: 0 0 8px;
}

.pack-combo-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #ede9fe;
  color: #5b21b6;
  flex-shrink: 0;
}
</style>
