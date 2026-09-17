<template>
  <div class="enough-field">
    <ECheckbox
      :model-value="value.enough"
      :label="t('grossanlass.wishes.enoughOnHand')"
      :hint="t('grossanlass.wishes.enoughOnHandHint')"
      persistent-hint
      hide-details="auto"
      @update:model-value="onToggle"
    />

    <div v-if="value.enough" class="enough-field__source">
      <p class="enough-field__q">{{ t('grossanlass.wishes.enoughOnHandSource') }}</p>
      <label class="enough-field__opt">
        <input
          type="radio"
          :name="radioName"
          value="stock"
          :checked="value.source === 'stock'"
          @change="setSource('stock')"
        />
        <span>{{ t('grossanlass.wishes.enoughOnHandStock') }}</span>
      </label>
      <label class="enough-field__opt">
        <input
          type="radio"
          :name="radioName"
          value="commitment"
          :checked="value.source === 'commitment'"
          @change="setSource('commitment')"
        />
        <span>{{ t('grossanlass.wishes.enoughOnHandCommitment') }}</span>
      </label>

      <ETextField
        v-if="value.source === 'stock'"
        :model-value="value.detail === 'Eigenbestand' ? '' : value.detail"
        :label="t('grossanlass.wishes.enoughOnHandStockDetail')"
        :placeholder="t('grossanlass.wishes.enoughOnHandStockPlaceholder')"
        hide-details="auto"
        class="enough-field__input"
        @update:model-value="onStockDetail"
      />
      <ESelect
        v-else-if="value.source === 'commitment'"
        :model-value="value.refId"
        :items="commitmentItems"
        item-title="title"
        item-value="id"
        :label="t('grossanlass.wishes.enoughOnHandPickZusage')"
        hide-details="auto"
        class="enough-field__input"
        @update:model-value="onCommitmentId"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { ECheckbox, ESelect, ETextField } from '@/components/form/base'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import {
  emptyEnoughOnHand,
  type EnoughOnHandSource,
  type EnoughOnHandValue,
} from '@/utils/grossanlassEnoughOnHand'

const props = defineProps<{
  commitments: GrossanlassCommitment[]
  defaultCommitment?: { id: string; source: string; name: string } | null
}>()

const value = defineModel<EnoughOnHandValue>({ required: true })
const { t } = useI18n()
const radioName = useId()

const commitmentItems = computed(() =>
  props.commitments.map((row) => ({
    id: row.id,
    title: commitmentLabel(row),
  })),
)

function commitmentLabel(row: { source: string; name: string } | null | undefined): string {
  if (!row) return ''
  const source = row.source.trim()
  const name = row.name.trim()
  if (source && name) return `${source} · ${name}`
  return source || name
}

function onToggle(checked: boolean | null) {
  if (!checked) {
    value.value = emptyEnoughOnHand()
    return
  }
  if (props.defaultCommitment) {
    value.value = {
      enough: true,
      source: 'commitment',
      refId: props.defaultCommitment.id,
      detail: commitmentLabel(props.defaultCommitment),
    }
    return
  }
  value.value = { ...value.value, enough: true }
}

function setSource(source: EnoughOnHandSource) {
  if (source === 'stock') {
    value.value = {
      enough: true,
      source: 'stock',
      detail: value.value.source === 'stock' ? value.value.detail : '',
      refId: null,
    }
    return
  }
  const fallback = props.defaultCommitment
  const keep = value.value.source === 'commitment' ? value.value.refId : null
  const refId = keep || fallback?.id || null
  const picked = props.commitments.find((row) => row.id === refId) || fallback
  value.value = {
    enough: true,
    source: 'commitment',
    refId,
    detail: commitmentLabel(picked),
  }
}

function onStockDetail(raw: string | number | null) {
  value.value = {
    ...value.value,
    enough: true,
    source: 'stock',
    detail: String(raw ?? '').trim(),
    refId: null,
  }
}

function onCommitmentId(id: unknown) {
  const refId = typeof id === 'string' && id !== '' ? id : null
  const row = props.commitments.find((item) => item.id === refId)
  value.value = {
    enough: true,
    source: 'commitment',
    refId,
    detail: commitmentLabel(row),
  }
}
</script>

<style scoped>
.enough-field {
  display: grid;
  gap: 4px;
}
.enough-field__source {
  margin: 0 0 4px 2px;
  padding: 8px 10px 10px;
  border-left: 3px solid #93c5fd;
  display: grid;
  gap: 6px;
}
.enough-field__q {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.enough-field__opt {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
  color: #0f172a;
  cursor: pointer;
}
.enough-field__input {
  margin-top: 4px;
}
</style>
