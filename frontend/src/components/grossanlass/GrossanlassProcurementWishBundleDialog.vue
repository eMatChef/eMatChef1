<template>
  <EDialog
    v-model="open"
    :max-width="720"
    scrollable
    :title="dialogTitle"
  >
    <p v-if="wishes.length > 1" class="wish-bundle__hint">
      {{ t('grossanlass.beschaffung.zusagen.viewWishesHint', { count: wishes.length }) }}
    </p>
    <p v-else class="wish-bundle__hint">
      {{ t('grossanlass.beschaffung.zusagen.viewWishHint') }}
    </p>

    <v-expansion-panels
      v-model="openedWishId"
      class="e-accordions"
    >
      <v-expansion-panel
        v-for="wish in wishes"
        :key="wish.id"
        :value="wish.id"
      >
        <v-expansion-panel-title>
          <div class="wish-acc-head">
            <div class="wish-acc-head__title">
              <strong>{{ t('grossanlass.beschaffung.zusagen.qtyLabel', { count: wish.quantity, name: wish.label }) }}</strong>
              <span v-if="stageLabel(wish)" class="panel-head__count">{{ stageLabel(wish) }}</span>
            </div>
            <p class="wish-acc-head__meta">
              {{ headerMeta(wish) }}
            </p>
          </div>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <GrossanlassProcurementWishReadonlyPanel
            v-if="open && openedWishId === wish.id && departmentId"
            :department-id="departmentId"
            :wish="wish"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <template #actions>
      <EButton variant="primary" size="small" @click="open = false">
        {{ t('grossanlass.beschaffung.zusagen.viewWishesClose') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import GrossanlassProcurementWishReadonlyPanel from '@/components/grossanlass/GrossanlassProcurementWishReadonlyPanel.vue'
import type { GrossanlassProcurementPoolWish } from '@/api/grossanlassProcurement'
import type { DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { resolveWishNeedPeriod } from '@/utils/grossanlassWishPeriod'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = defineProps<{
  departmentId: string
  wishes: GrossanlassProcurementPoolWish[]
  lineLabel?: string
  lineQuantity?: number
  calendarPeriods: DepartmentCalendarPeriod[]
}>()

const open = defineModel<boolean>({ required: true })
const { t, locale } = useI18n()
const openedWishId = ref<string | undefined>(undefined)

const dialogTitle = computed(() => {
  if (props.wishes.length > 1) {
    return t('grossanlass.beschaffung.zusagen.viewWishesTitle', {
      count: props.wishes.length,
      label: props.lineLabel || props.wishes[0]?.label || '',
      quantity: props.lineQuantity ?? props.wishes.reduce((sum, wish) => sum + wish.quantity, 0),
    })
  }
  return t('grossanlass.beschaffung.zusagen.wishAnswerTitle')
})

watch(open, (visible) => {
  if (visible) openedWishId.value = undefined
})

function stageLabel(wish: GrossanlassProcurementPoolWish): string {
  const stage = String(wish.last_stage || '').toLowerCase()
  if (stage === 'fein') return t('grossanlass.planung.wishForms.stageFein')
  if (stage === 'grob') return t('grossanlass.planung.wishForms.stageGrob')
  return ''
}

function periodText(wish: GrossanlassProcurementPoolWish): string {
  const need = resolveWishNeedPeriod(wish, props.calendarPeriods)
  if (!need?.from || !need?.to) {
    return t('grossanlass.materials.detailWishNeedUnset')
  }
  return `${formatGaIsoLabel(need.from, locale.value)} – ${formatGaIsoLabel(need.to, locale.value)}`
}

function headerMeta(wish: GrossanlassProcurementPoolWish): string {
  return [wish.location, periodText(wish), wish.group_name].filter(Boolean).join(' · ')
}
</script>

<style scoped>
.wish-bundle__hint {
  margin: 0 0 12px;
  font-size: 0.82rem;
  color: #475569;
}
.wish-acc-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  text-align: left;
}
.wish-acc-head__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}
.wish-acc-head__meta {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 500;
  color: #64748b;
  line-height: 1.35;
}
</style>
