<script setup lang="ts">
import { IconArrowRight, IconArrowLeft } from '@/components/icons'
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackWarehouseIssueContainerCard' })

const props = withDefaults(
  defineProps<{
    container: ActivityPackContainer
    stageRightLabel: string
    variant?: 'list' | 'shell'
    /** Phys.-Kombi-Zeile — nur bei variant=shell */
    shellPackItem?: ActivityPackItem | null
  }>(),
  { variant: 'list', shellPackItem: null },
)

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown> &
  Record<string, unknown>

const shellPeekSections = computed((): PackCrateShellPeekSection[] => {
  if (props.variant !== 'shell') return []
  const fn = ctx.peekSectionsForShellContainer as ((c: ActivityPackContainer) => PackCrateShellPeekSection[]) | undefined
  return fn ? fn(props.container) : []
})

const shellPeekEmptyHint = computed(() => {
  const pi = props.shellPackItem
  const fn = ctx.crateShellPeekEmptyHint as ((p: ActivityPackItem) => string) | undefined
  if (pi && fn) return fn(pi)
  return t('activities.packList.cratePeekNoShellYet')
})

const innerVisible = computed(
  () => !(ctx.isPackContainerCollapsed as (id: string) => boolean)(props.container.id),
)

const shellCanMoveForward = computed(() => {
  const pi = props.shellPackItem
  if (!pi) return false
  const fn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  return fn ? fn(pi) > 0 : false
})

function moveShellCrateForward() {
  const pi = props.shellPackItem
  if (!pi) return
  const maxFn = ctx.packIssueForwardMax as ((p: ActivityPackItem) => number) | undefined
  const moveFn = ctx.moveToNextStage as ((p: ActivityPackItem, qty?: number) => void | Promise<void>) | undefined
  if (!maxFn || !moveFn) return
  const max = maxFn(pi)
  if (max < 1) return
  void moveFn(pi, max)
}

</script>

<template>
  <div
    :id="variant === 'shell' ? 'pack-container-shell-' + container.id : 'pack-container-issue-' + container.id"
    class="pack-container-card"
    :class="{
      'pack-container-card--target':
        (ctx.activePackTarget as { kind: string; containerId?: string } | null)?.kind === 'container' &&
        (ctx.activePackTarget as { kind: string; containerId?: string }).containerId === container.id,
      'pack-container-card--shell': variant === 'shell',
    }"
  >
    <!-- Phys.-Kombi: eine Zeile wie Granatenkiste, Aufklappen für Inhalt + Zusatz-Buchung -->
    <template v-if="variant === 'shell' && shellPackItem">
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
          <div class="pack-container-header-title-block pack-container-header-title-block--shell">
            <span class="pack-container-name">{{ shellPackItem.materialName }}</span>
            <span
              class="pack-combo-badge"
              :title="t('activities.detail.comboPhysicalTitle')"
              >{{ t('activities.detail.comboPhysicalShort') }}</span
            >
            <span class="pack-container-chip text-muted">{{
              t('activities.common.itemsUnit', {
                count: (ctx.containerItemCount as (id: string) => number)(container.id),
              })
            }}</span>
          </div>
        </div>
        <div
          v-if="ctx.packListEditable && shellCanMoveForward"
          class="pack-container-header-actions"
          @click.stop
        >
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
          v-if="shellPackItem.storageAddressName || shellPackItem.storageSlotName"
          class="pack-shell-storage text-muted"
        >
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
          :reality-banner="
            shellPackItem && (ctx.crateRealityBannerForPackItem as ((p: ActivityPackItem) => string | null) | undefined)
              ? (ctx.crateRealityBannerForPackItem as (p: ActivityPackItem) => string | null)(shellPackItem)
              : null
          "
          :show-template-toggle="
            shellPackItem && (ctx.showCrateTemplateToggle as ((p: ActivityPackItem) => boolean) | undefined)
              ? (ctx.showCrateTemplateToggle as (p: ActivityPackItem) => boolean)(shellPackItem)
              : false
          "
          :use-reality-view="
            shellPackItem && (ctx.useCrateRealityForPackItem as ((id: string) => boolean) | undefined)
              ? (ctx.useCrateRealityForPackItem as (id: string) => boolean)(shellPackItem.id)
              : true
          "
          separate-section-rows
          :default-expanded="innerVisible"
          @toggle-reality-view="
            shellPackItem &&
              (ctx.toggleCrateRealityView as ((p: ActivityPackItem) => void) | undefined) &&
              (ctx.toggleCrateRealityView as (p: ActivityPackItem) => void)(shellPackItem)
          "
        />
      </div>
    </template>

    <!-- Normale Pack-Kiste (KISTEN-Liste) -->
    <template v-else>
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
            <button
              type="button"
              class="pack-container-select-main"
              :aria-pressed="
                (ctx.activePackTarget as { kind: string; containerId?: string } | null)?.kind === 'container' &&
                (ctx.activePackTarget as { kind: string; containerId?: string }).containerId === container.id
              "
              @click="(ctx.toggleActiveContainer as (id: string) => void)(container.id)"
            >
              <span class="pack-container-name">{{ container.label }}</span>
            </button>
            <span class="pack-container-chip text-muted">{{
              t('activities.common.itemsUnit', {
                count: (ctx.containerItemCount as (id: string) => number)(container.id),
              })
            }}</span>
          </div>
        </div>
        <div
          v-if="
            ctx.packListEditable &&
            ((ctx.containerUnissueableUnits as (id: string) => number)(container.id) > 0 ||
              (ctx.containerIssueableUnits as (id: string) => number)(container.id) > 0)
          "
          class="pack-container-header-actions"
          @click.stop
        >
          <button
            v-if="(ctx.containerUnissueableUnits as (id: string) => number)(container.id) > 0"
            type="button"
            class="btn-moveback-arrow btn-move-arrow--container-header"
            :disabled="ctx.containerBulkLoadingId === container.id"
            :title="
              t('activities.packList.unissueTitle', {
                count: (ctx.containerUnissueableUnits as (id: string) => number)(container.id),
              })
            "
            @click="(ctx.unissueContainerToPacked as (c: ActivityPackContainer) => void | Promise<void>)(container)"
          >
            <IconArrowLeft />
          </button>
          <button
            v-if="(ctx.containerIssueableUnits as (id: string) => number)(container.id) > 0"
            type="button"
            class="btn-move-arrow btn-move-arrow--container-header"
            :disabled="ctx.containerBulkLoadingId === container.id"
            :title="
              t('activities.packList.issueRestTitle', {
                stage: stageRightLabel,
                count: (ctx.containerIssueableUnits as (id: string) => number)(container.id),
              })
            "
            @click="(ctx.issueContainerToEvent as (c: ActivityPackContainer) => void | Promise<void>)(container)"
          >
            <IconArrowRight />
          </button>
        </div>
      </div>
      <div
        v-if="ctx.canReportIssues && container.container_material_item_id"
        class="pack-container-kiste-meldung-row"
        @click.stop
      >
        <span class="pack-container-kiste-meldung-label">{{ t('activities.common.crate') }}</span>
        <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(String(container.container_material_item_id))">
          <button
            type="button"
            class="btn-issue-quick btn-issue-consumed"
            @click="
              (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(String(container.container_material_item_id), {
                linkedContainerLabel: container.label,
              })
            "
          >
            {{ t('activities.common.issueConsumed') }}
          </button>
        </template>
        <template v-else>
          <button
            type="button"
            class="btn-issue-quick btn-issue-loss"
            @click="
              (ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(
                String(container.container_material_item_id),
                'loss',
              )
            "
          >
            {{ t('activities.common.issueLoss') }}
          </button>
          <button
            type="button"
            class="btn-issue-quick btn-issue-repair"
            @click="
              (ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(
                String(container.container_material_item_id),
                'repair',
              )
            "
          >
            {{ t('activities.common.issueRepair') }}
          </button>
        </template>
      </div>
      <div v-show="innerVisible" class="pack-container-inner">
        <template
          v-for="sec in (ctx.packContainerItemSections as (c: ActivityPackContainer) => { subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }[])(container)"
          :key="'issue-sec-' + container.id + '-' + sec.subsectionKey"
        >
          <button
            type="button"
            class="pack-container-subsection-toggle"
            :aria-expanded="!(ctx.isPackContainerSubsectionCollapsed as (cid: string, sk: string) => boolean)(container.id, sec.subsectionKey)"
            :aria-label="`${sec.title} — ${t('activities.packList.cratePeekRowToggleAria')}`"
            @click.stop="
              (ctx.togglePackContainerSubsection as (cid: string, sk: string) => void)(container.id, sec.subsectionKey)
            "
          >
            <span class="pack-container-chevron pack-container-chevron--subsection" aria-hidden="true">
              <svg
                v-if="
                  (ctx.isPackContainerSubsectionCollapsed as (cid: string, sk: string) => boolean)(
                    container.id,
                    sec.subsectionKey,
                  )
                "
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
            v-show="!(ctx.isPackContainerSubsectionCollapsed as (cid: string, sk: string) => boolean)(container.id, sec.subsectionKey)"
            class="pack-container-subsection-lines"
          >
          <div
            v-for="ci in sec.lines"
            :key="'issue-' + sec.subsectionKey + '-' + ci.id"
            class="pack-container-line pack-container-line--issue-row pack-container-line--stacked"
          >
            <div
              v-if="ctx.packListEditable && (ctx.containerLineUnissueableMax as (ci: ActivityPackContainerItem) => number)(ci) > 0"
              class="pack-card-actions pack-card-actions-left"
            >
              <button
                type="button"
                class="btn-moveback-arrow"
                :disabled="ctx.containerMutationLoading"
                @click="(ctx.unissueContainerLineToPacked as (cid: string, ci: ActivityPackContainerItem) => void)(container.id, ci)"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M19 12H5" />
                  <polyline points="12 19 5 12 12 12 5" />
                </svg>
              </button>
              <input
                v-model.number="ctx.containerUnissueLineInputs[(ctx.containerIssueLineKey as (a: string, b: string) => string)(container.id, ci.id)]"
                type="number"
                min="1"
                :max="(ctx.containerLineUnissueableMax as (ci: ActivityPackContainerItem) => number)(ci)"
                class="pack-moveback-input"
              />
            </div>
            <div
              v-if="
                ctx.packListEditable &&
                (ctx.activePackTarget as { kind: string } | null)?.kind === 'loose' &&
                !(ctx.isVirtualWarehouseContainerLine as (ci: ActivityPackContainerItem) => boolean)(ci)
              "
              class="pack-card-actions pack-card-actions-left"
            >
              <button
                type="button"
                class="btn-moveback-arrow"
                :disabled="ctx.containerMutationLoading"
                @click="(ctx.pullFromContainer as (cid: string, ci: ActivityPackContainerItem) => void)(container.id, ci)"
              >
                <IconArrowLeft />
              </button>
              <input
                v-model.number="ctx.containerPullQtyInputs[(ctx.containerPullKey as (a: string, b: string) => string)(container.id, ci.id)]"
                type="number"
                min="1"
                :max="ci.quantity_packed"
                class="pack-moveback-input"
              />
            </div>
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
              <span class="pack-container-line-qty text-muted">
                <template v-if="(ctx.containerLineRemainingIssue as (ci: ActivityPackContainerItem) => number)(ci) > 0">
                  {{
                    t('activities.packList.lineNotYetIssued', {
                      rem: (ctx.containerLineRemainingIssue as (ci: ActivityPackContainerItem) => number)(ci),
                      packed: ci.quantity_packed,
                      stage: stageRightLabel,
                    })
                  }}
                </template>
                <template v-else-if="(ctx.containerLinePackRemaining as (ci: ActivityPackContainerItem) => number)(ci) > 0">
                  {{ t('activities.packList.packListNotYetAtStage', { stage: stageRightLabel }) }}
                </template>
                <template v-else>
                  {{
                    t('activities.packList.issuedFraction', {
                      issued: ci.quantity_issued ?? 0,
                      packed: ci.quantity_packed,
                      stage: stageRightLabel,
                    })
                  }}
                </template>
              </span>
            </div>
            <div
              v-if="ctx.packListEditable && (ctx.containerLineIssueableMax as (ci: ActivityPackContainerItem) => number)(ci) > 0"
              class="pack-card-actions"
            >
              <div class="pack-move-inline">
                <input
                  v-model.number="ctx.containerIssueLineInputs[(ctx.containerIssueLineKey as (a: string, b: string) => string)(container.id, ci.id)]"
                  type="number"
                  min="1"
                  :max="(ctx.containerLineIssueableMax as (ci: ActivityPackContainerItem) => number)(ci)"
                  class="pack-move-input"
                  @keyup.enter="(ctx.issueContainerLineToEvent as (cid: string, ci: ActivityPackContainerItem) => void)(container.id, ci)"
                />
                <button
                  type="button"
                  class="btn-move-arrow"
                  :disabled="ctx.containerMutationLoading"
                  @click="(ctx.issueContainerLineToEvent as (cid: string, ci: ActivityPackContainerItem) => void)(container.id, ci)"
                >
                  <IconArrowRight />
                </button>
              </div>
            </div>
            <div
              v-if="
                ctx.canReportIssues &&
                ci.material_item_id &&
                !(ctx.isVirtualWarehouseContainerLine as (ci: ActivityPackContainerItem) => boolean)(ci) &&
                (ci.quantity_issued ?? 0) > 0
              "
              class="pack-container-line-issue-quick"
              @click.stop
            >
              <template v-if="(ctx.isPackMaterialConsumable as (id: string) => boolean)(ci.material_item_id)">
                <button
                  type="button"
                  class="btn-issue-quick btn-issue-consumed"
                  @click="
                    (ctx.emitConsumptionForMaterialId as (id: string, h?: unknown) => void)(ci.material_item_id, {
                      materialName: ci.material_name,
                      linkedContainerLabel: ci.batch_label || ci.serial_number || null,
                    })
                  "
                >
                  {{ t('activities.common.issueConsumed') }}
                </button>
              </template>
              <template v-else>
                <button
                  type="button"
                  class="btn-issue-quick btn-issue-loss"
                  @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(ci.material_item_id, 'loss')"
                >
                  {{ t('activities.common.issueLoss') }}
                </button>
                <button
                  type="button"
                  class="btn-issue-quick btn-issue-repair"
                  @click="(ctx.emitIssueWizardByMaterialId as (id: string, t: 'loss' | 'repair') => void)(ci.material_item_id, 'repair')"
                >
                  {{ t('activities.common.issueRepair') }}
                </button>
              </template>
            </div>
          </div>
          </div>
        </template>
        <p
          v-if="((ctx.containerItemsByContainerId as Record<string, ActivityPackContainerItem[]>)[container.id] ?? []).length === 0"
          class="pack-container-empty text-muted"
        >
          {{ t('activities.packList.nothingAssigned') }}
        </p>
      </div>
    </template>
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

.pack-container-embed-extra-hint {
  margin: 12px 0 4px;
  font-size: 12px;
  line-height: 1.4;
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
