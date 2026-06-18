<script setup lang="ts">
import { computed, inject } from 'vue'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { PackContainerItemSection } from '@/components/activities/packShellCrateHelpers'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'

defineOptions({ name: 'PackContainerSubsectionsList' })

const props = defineProps<{
  container: ActivityPackContainer
}>()

const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY) as Record<string, (...args: unknown[]) => unknown>

const sections = computed((): PackContainerItemSection[] => {
  const fn = ctx.packContainerItemSections as ((c: ActivityPackContainer) => PackContainerItemSection[]) | undefined
  return fn ? fn(props.container) : []
})

const hasAnyLine = computed(() => sections.value.some((s) => s.lines.length > 0))
</script>

<template>
  <template v-for="sec in sections" :key="'sec-' + container.id + '-' + sec.subsectionKey">
    <div class="pack-container-subsection-lines">
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
