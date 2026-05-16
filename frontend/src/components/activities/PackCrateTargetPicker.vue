<script setup lang="ts">
import { computed, inject, reactive, unref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import PackCratePickerInnerContent from '@/components/activities/PackCratePickerInnerContent.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackCrateTargetPicker' })

type ActivePackTarget = { kind: 'loose' } | { kind: 'container'; containerId: string } | null

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, unknown>

function injectRef<T>(raw: unknown): T {
  return unref(raw as Ref<T> | T)
}

const packListEditable = computed(() => Boolean(injectRef(ctx.packListEditable)))
const packContainers = computed(() => {
  const list = injectRef<ActivityPackContainer[] | undefined>(ctx.packContainers)
  return Array.isArray(list) ? list : []
})
const activePackTarget = computed(() => injectRef<ActivePackTarget>(ctx.activePackTarget))

const expandedByCrateId = reactive<Record<string, boolean>>({})

const sortedCrates = computed(() =>
  [...packContainers.value].sort((a, b) => a.label.localeCompare(b.label, 'de')),
)

function itemCount(containerId: string): number {
  return (ctx.containerItemCount as (id: string) => number)(containerId)
}

function isCrateSelected(id: string): boolean {
  const tgt = activePackTarget.value
  return tgt?.kind === 'container' && tgt.containerId === id
}

function isLooseSelected(): boolean {
  return activePackTarget.value?.kind === 'loose'
}

function isCrateExpanded(id: string): boolean {
  return expandedByCrateId[id] === true
}

function toggleCrateExpanded(id: string) {
  expandedByCrateId[id] = !isCrateExpanded(id)
}

function selectLoose() {
  if (!packListEditable.value) return
  ;(ctx.selectActiveLoose as () => void)()
}

function selectCrate(id: string) {
  if (!packListEditable.value) return
  ;(ctx.selectActiveContainer as (containerId: string) => void)(id)
}
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

      <div
        v-for="c in sortedCrates"
        :key="c.id"
        class="pack-container-card pack-crate-picker-card"
        :class="{
          'pack-container-card--target': isCrateSelected(c.id),
          'pack-container-card--selectable': packListEditable,
        }"
      >
        <div class="pack-container-header-row">
          <button
            type="button"
            class="pack-container-chevron-btn"
            :aria-expanded="isCrateExpanded(c.id)"
            :aria-label="t('activities.packList.cratePickerExpandAria', { label: c.label })"
            @click.stop="toggleCrateExpanded(c.id)"
          >
            <span class="pack-container-chevron" aria-hidden="true">{{
              isCrateExpanded(c.id) ? '▼' : '▶'
            }}</span>
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
              <span class="pack-container-name">{{ c.label }}</span>
            </button>
            <div class="pack-container-header-meta">
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
    <p v-if="sortedCrates.length === 0" class="pack-crate-picker-empty text-muted">
      {{ t('activities.packList.hintNoCratesPicker') }}
    </p>
  </div>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style src="@/styles/views/activities/pack-container-card.css"></style>
<style scoped src="@/styles/views/activities/pack-crate-picker.css"></style>
