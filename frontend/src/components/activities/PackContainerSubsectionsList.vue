<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { PackContainerItemSection } from '@/components/activities/packShellCrateHelpers'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerSubsectionsList' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const { t } = useI18n()
const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const sections = computed((): PackContainerItemSection[] => {
  const fn = ctx.packContainerItemSections as ((c: ActivityPackContainer) => PackContainerItemSection[]) | undefined
  return fn ? fn(props.container) : []
})

const hasAnyLine = computed(() => sections.value.some((s) => s.lines.length > 0))
</script>

<template>
  <template v-for="sec in sections" :key="'sec-' + container.id + '-' + sec.subsectionKey">
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
      <slot
        v-for="ci in sec.lines"
        :key="sec.subsectionKey + '-' + ci.id"
        name="line"
        :ci="ci"
        :sec="sec"
      />
    </div>
  </template>
  <slot v-if="!hasAnyLine" name="empty" />
</template>

<style src="@/styles/views/activities/pack-container-card.css"></style>
