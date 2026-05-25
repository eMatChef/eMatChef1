<script setup lang="ts">
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'

defineProps<{
  materialHeading: string
  typeLabel: string
  quantity: number
  description?: string
  reviewStatus: 'ok' | 'problem' | null
  okTitle: string
  okAriaLabel: string
  problemTitle: string
  problemAriaLabel: string
  /** Kompakte Zeile innerhalb einer Kistencheck-Position (kein verschachteltes li) */
  embedded?: boolean
}>()

defineEmits<{
  'set-review': [status: 'ok' | 'problem']
}>()
</script>

<template>
  <component
    :is="embedded ? 'div' : 'li'"
    class="pack-repack-issue-row"
    :class="
      embedded
        ? {
            'pack-shell-forward-line-issue-row': true,
            'pack-shell-forward-line-issue-row--problem': reviewStatus === 'problem',
          }
        : {
            'pack-shell-forward-li': true,
            'pack-repack-issue-li': true,
            'pack-shell-forward-li--ok': reviewStatus === 'ok',
            'pack-shell-forward-li--problem': reviewStatus === 'problem',
          }
    "
  >
    <div
      class="pack-shell-forward-li-row pack-repack-issue-li-row"
      :class="{ 'pack-shell-forward-line-issue-row__inner': embedded }"
    >
      <div
        class="pack-repack-issue-inline"
        :class="{ 'pack-repack-issue-inline--shell-embed': embedded }"
      >
        <span v-if="!embedded" class="pack-shell-forward-li-name">{{ materialHeading }}</span>
        <span class="pack-repack-issue-type">{{ typeLabel }}</span>
        <span class="text-muted" :class="{ 'repack-issue-qty': !embedded }">{{ quantity }}×</span>
        <template v-if="!embedded && (description || '').trim()">
          <span class="pack-repack-issue-sep text-muted" aria-hidden="true">·</span>
          <span class="pack-repack-issue-desc-inline text-muted" :title="description!.trim()">{{
            description!.trim()
          }}</span>
        </template>
      </div>
      <PackShellCheckToggle
        :ok-active="reviewStatus === 'ok'"
        :problem-active="reviewStatus === 'problem'"
        :ok-title="okTitle"
        :ok-aria-label="okAriaLabel"
        :problem-title="problemTitle"
        :problem-aria-label="problemAriaLabel"
        @ok="$emit('set-review', 'ok')"
        @problem="$emit('set-review', 'problem')"
      />
    </div>
  </component>
</template>
