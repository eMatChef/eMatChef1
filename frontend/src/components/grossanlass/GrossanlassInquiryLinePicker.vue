<template>
  <div class="ask-list">
    <template v-if="locked">
      <p class="ask-list__title">{{ askedTitle }}</p>
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.askedListHint') }}</p>
      <ul v-if="askedLines.length" class="ask-list__items">
        <li v-for="line in askedLines" :key="line.id">
          {{ line.quantity }}× {{ line.label }}
        </li>
      </ul>
      <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.askedListLegacy') }}</p>
    </template>
    <template v-else>
      <p class="ask-list__title">{{ t('grossanlass.beschaffung.anfragen.askListTitle') }}</p>
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.askListHint') }}</p>
      <p v-if="!hasCategory" class="muted">{{ t('grossanlass.beschaffung.anfragen.askNeedCategory') }}</p>
      <EAutocomplete
        v-else
        v-model="pickId"
        :items="dropdownItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.beschaffung.anfragen.askSearchLabel')"
        :hint="dropdownItems.length
          ? t('grossanlass.beschaffung.anfragen.askSearchHint', { count: dropdownItems.length })
          : t('grossanlass.beschaffung.anfragen.askDropdownEmpty')"
        :no-filter="false"
        clearable
        hide-details="auto"
        autocomplete="off"
        @update:model-value="onPick"
      />
      <ul v-if="selectedLines.length" class="ask-list__items">
        <li v-for="line in selectedLines" :key="'sel-' + line.id" class="ask-list__picked">
          <span>{{ wishLabel(line) }}</span>
          <button type="button" class="ask-list__remove" @click="emit('remove', line.id)">
            {{ t('grossanlass.beschaffung.anfragen.askRemove') }}
          </button>
        </li>
      </ul>
      <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.askSelectedEmpty') }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GrossanlassProcurementCategory, GrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import { EAutocomplete } from '@/components/form/base'
import { leafIdsOfProcurementCategorySelection } from '@/utils/grossanlassProcurementCategoryTree'

export type InquiryAskedLine = { id: string; label: string; quantity: number }

const props = defineProps<{
  lines: GrossanlassProcurementLine[]
  categories?: GrossanlassProcurementCategory[]
  categoryIds?: string[]
  selectedIds: string[]
  locked: boolean
  askedVia?: 'phone' | 'mail' | 'mailbox' | null
  askedAt?: string | null
  askedLines?: InquiryAskedLine[]
}>()

const emit = defineEmits<{
  add: [lineId: string]
  remove: [lineId: string]
}>()

const { t, locale } = useI18n()
const pickId = ref<string | null>(null)

const allowedCategoryIds = computed(() => {
  const selected = props.categoryIds ?? []
  if (!selected.length) return new Set<string>()
  const leaves = leafIdsOfProcurementCategorySelection(props.categories ?? [], selected)
  return new Set([...selected, ...leaves])
})

const hasCategory = computed(() => allowedCategoryIds.value.size > 0)

const inCategory = computed(() =>
  props.lines.filter((line) => !!line.category_id && allowedCategoryIds.value.has(line.category_id)),
)

const selectedLines = computed(() =>
  props.selectedIds
    .map((id) => props.lines.find((line) => line.id === id))
    .filter((line): line is GrossanlassProcurementLine => !!line),
)

const dropdownItems = computed(() => {
  const taken = new Set(props.selectedIds)
  return inCategory.value
    .filter((line) => !taken.has(line.id))
    .map((line) => ({ title: wishLabel(line), value: line.id }))
})

function onPick(value: unknown) {
  const id = typeof value === 'string' ? value : ''
  if (!id) return
  emit('add', id)
  pickId.value = null
}

const askedTitle = computed(() => {
  const when = formatWhen(props.askedAt)
  if (props.askedVia === 'phone') {
    return t('grossanlass.beschaffung.anfragen.askedPhone', { when })
  }
  if (props.askedVia === 'mailbox') {
    return t('grossanlass.beschaffung.anfragen.askedMailbox', { when })
  }
  if (props.askedVia === 'mail') {
    return t('grossanlass.beschaffung.anfragen.askedMail', { when })
  }
  return t('grossanlass.beschaffung.anfragen.askedUnknown')
})

function wishLabel(line: GrossanlassProcurementLine): string {
  const category = line.category_name ? ` · ${line.category_name}` : ''
  return `${line.quantity}× ${line.label}${category}`
}

function formatWhen(iso?: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const loc = String(locale.value).startsWith('de') ? 'de-CH' : 'en-GB'
  return date.toLocaleString(loc, { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<style scoped>
.ask-list { margin: 0 0 14px; padding: 10px 12px; border: 1px solid #99f6e4; border-radius: 10px; background: #f0fdfa; }
.ask-list__title { margin: 0 0 4px; font-size: 0.92rem; font-weight: 700; color: #0f766e; }
.review-hint { margin: 0 0 8px; font-size: 0.82rem; color: #64748b; }
.muted { margin: 8px 0 0; color: #64748b; font-size: 0.82rem; }
.ask-list__items, .ask-list__matches { margin: 8px 0 0; padding: 0; list-style: none; }
.ask-list__matches li + li, .ask-list__items li + li { margin-top: 4px; }
.ask-list__add, .ask-list__remove {
  border: 0;
  background: transparent;
  color: #0f766e;
  cursor: pointer;
  font: inherit;
  text-align: left;
}
.ask-list__add { text-decoration: underline; }
.ask-list__picked { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; font-size: 0.88rem; }
.ask-list__remove { font-size: 0.78rem; flex: none; }
</style>
