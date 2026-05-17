<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import { IconArrowLeft } from '@/components/icons'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import {
  injectPackCtxBool,
  PACK_WAREHOUSE_ISSUE_INJECT_KEY,
} from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackConfirmedPackedContainerCard' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const activePackTarget = computed(
  () => ctx.activePackTarget as unknown as { kind: string; containerId?: string } | null,
)

const isTarget = computed(
  () =>
    activePackTarget.value?.kind === 'container' &&
    activePackTarget.value.containerId === props.container.id,
)

const packListEditable = computed(() => injectPackCtxBool(ctx, 'packListEditable'))
const containerMutationLoading = computed(() => injectPackCtxBool(ctx, 'containerMutationLoading'))

function isNonActionable(ci: ActivityPackContainerItem): boolean {
  return (ctx.isVirtualWarehouseContainerLine as (row: ActivityPackContainerItem) => boolean)(ci)
}

function selectCrate() {
  if (!packListEditable.value) return
  ;(ctx.toggleActiveContainer as (id: string) => void)(props.container.id)
}

function onCardClick(event: MouseEvent) {
  if (!packListEditable.value) return
  const el = event.target as HTMLElement
  if (el.closest('button, input, a, select, textarea, label')) return
  selectCrate()
}
</script>

<template>
  <div
    :id="'pack-container-' + container.id"
    class="pack-container-card"
    :class="{
      'pack-container-card--target': isTarget,
      'pack-container-card--selectable': packListEditable,
    }"
    @click="onCardClick"
  >
    <div class="pack-container-header-row">
      <button
        type="button"
        class="pack-container-chevron-btn"
        :aria-expanded="innerVisible"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click.stop="(ctx.togglePackContainerCollapsed as (id: string) => void)(container.id)"
      >
        <span class="pack-container-chevron" aria-hidden="true">{{ innerVisible ? '▼' : '▶' }}</span>
      </button>
      <div class="pack-container-header-main">
        <button
          type="button"
          class="pack-container-select-main"
          :aria-pressed="isTarget"
          :title="t('activities.packList.targetCrateSelectTitle')"
          @click.stop="selectCrate"
        >
          <span class="pack-container-name">{{ container.label }}</span>
          <span v-if="isTarget" class="pack-container-target-badge">{{
            t('activities.packList.crateTargetBadge')
          }}</span>
        </button>
        <div class="pack-container-header-meta">
          <span class="pack-container-chip text-muted">{{
            t('activities.common.itemsUnit', {
              count: (ctx.containerItemCount as (id: string) => number)(container.id),
            })
          }}</span>
        </div>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner">
      <PackContainerSubsectionsList :container="container">
        <template #line="{ ci }">
          <div class="pack-container-line">
            <div v-if="ctx.packListEditable && !isNonActionable(ci)" class="pack-card-actions pack-card-actions-left">
              <button
                type="button"
                class="btn-moveback-arrow"
                :disabled="containerMutationLoading"
                :title="t('activities.packList.pullFromContainerTitle')"
                @click="(ctx.pullFromContainer as (cid: string, row: ActivityPackContainerItem) => void)(container.id, ci)"
              >
                <IconArrowLeft />
              </button>
              <input
                v-model.number="ctx.containerPullQtyInputs[(ctx.containerPullKey as (a: string, b: string) => string)(container.id, ci.id)]"
                type="number"
                min="1"
                :max="ci.quantity_packed"
                class="pack-moveback-input"
                @keyup.enter="(ctx.pullFromContainer as (cid: string, row: ActivityPackContainerItem) => void)(container.id, ci)"
              />
            </div>
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
              <span class="pack-container-line-qty">{{
                t('activities.packList.qtyInContainerLine', { n: ci.quantity_packed })
              }}</span>
            </div>
          </div>
        </template>
        <template #empty>
          <p class="pack-container-empty text-muted">
            {{ t('activities.packList.nothingAssigned') }}
          </p>
        </template>
      </PackContainerSubsectionsList>
      <button
        v-if="ctx.packListEditable"
        type="button"
        class="pack-container-delete"
        :disabled="containerMutationLoading"
        @click="(ctx.confirmDeleteContainer as (c: ActivityPackContainer) => void | Promise<void>)(container)"
      >
        {{ t('activities.packList.deleteContainer') }}
      </button>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
