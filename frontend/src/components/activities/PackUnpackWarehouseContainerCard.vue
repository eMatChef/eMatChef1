<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMaterialMeta from '@/components/activities/PackMaterialMeta.vue'
import PackContainerSubsectionsList from '@/components/activities/PackContainerSubsectionsList.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackUnpackWarehouseContainerCard' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const returnedLines = computed((): ActivityPackContainerItem[] => {
  const fn = ctx.containerReturnedInnerLines as ((id: string) => ActivityPackContainerItem[]) | undefined
  return fn?.(props.container.id) ?? []
})

const returnedUnits = computed(() => {
  const fn = ctx.containerReturnedContentUnits as ((id: string) => number) | undefined
  return fn?.(props.container.id) ?? 0
})

const showReturnedShell = computed(() => {
  const fn = ctx.containerShowsReturnedShell as ((id: string) => boolean) | undefined
  return fn?.(props.container.id) ?? false
})

const shellPackItem = computed((): ActivityPackItem | null => {
  const fn = ctx.shellPackItemForContainer as ((id: string) => ActivityPackItem | undefined) | undefined
  return fn?.(props.container.id) ?? null
})

function packItemForLine(ci: ActivityPackContainerItem): ActivityPackItem | undefined {
  const fn = ctx.packItemForMaterialItemId as ((id: string) => ActivityPackItem | undefined) | undefined
  return fn?.(ci.material_item_id)
}

function lineReturnedQty(ci: ActivityPackContainerItem): number {
  return ci.quantity_returned ?? 0
}
</script>

<template>
  <div
    :id="'pack-container-unpack-' + container.id"
    class="pack-container-card pack-container-card--unpack-warehouse"
    :class="{ 'pack-container-card--filled': returnedUnits > 0 }"
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
        <div class="pack-container-header-title-block">
          <span class="pack-container-name">{{ container.label }}</span>
          <span class="pack-container-chip text-muted">{{
            t('activities.packList.unpackCrateReturnedUnits', { n: returnedUnits })
          }}</span>
        </div>
      </div>
    </div>
    <div v-show="innerVisible" class="pack-container-inner">
      <p class="pack-containers-at-event-hint text-muted pack-unpack-crate-hint">
        {{ t('activities.packList.hintUnpackCrateCheck') }}
      </p>
      <PackContainerSubsectionsList :container="container">
        <template #line="{ ci }">
          <div
            v-if="lineReturnedQty(ci) > 0"
            class="pack-container-line pack-container-line--stacked pack-container-line--unpack"
          >
            <div class="pack-container-line-main pack-container-line-main--unpack">
              <div v-if="packItemForLine(ci)" class="pack-unpack-line-meta">
                <PackMaterialMeta
                  :item="packItemForLine(ci)!"
                  show-storage-location
                  :show-linked-kiste="false"
                />
              </div>
              <template v-else>
                <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
              </template>
              <span class="pack-container-line-qty text-muted">
                {{ t('activities.packList.lineReturnedForUnpack', { n: lineReturnedQty(ci) }) }}
              </span>
            </div>
          </div>
        </template>
      </PackContainerSubsectionsList>
      <div
        v-if="showReturnedShell && shellPackItem"
        class="pack-container-line pack-container-line--shell pack-container-line--stacked pack-container-line--unpack"
      >
        <div class="pack-container-line-main pack-container-line-main--unpack">
          <PackMaterialMeta :item="shellPackItem" show-storage-location :show-linked-kiste="false" />
          <span class="pack-container-line-qty text-muted">
            {{ t('activities.packList.unpackCrateShellReturned') }}
          </span>
        </div>
      </div>
      <p v-if="returnedLines.length === 0 && !showReturnedShell" class="pack-container-empty text-muted">
        {{ t('activities.packList.unpackCrateNoReturnedLines') }}
      </p>
    </div>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
