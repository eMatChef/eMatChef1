<template>
  <section class="section-card">
    <h2 class="section-title">{{ t('grossanlass.materials.detailTabWishes') }}</h2>
    <p class="panel-intro">{{ t('grossanlass.materials.detailWishesIntro') }}</p>

    <p v-if="partnerLabel" class="panel-partner">{{ partnerLabel }}</p>

    <EEmptyState
      v-if="wishes.length === 0"
      variant="default"
      icon="mdi-lightbulb-outline"
      :title="t('grossanlass.materials.detailWishesEmptyTitle')"
      :description="t('grossanlass.materials.detailWishesEmpty')"
    />

    <ul v-else class="wish-list">
      <li v-for="wish in wishes" :key="wish.id" class="wish-card" :class="{ 'wish-card--wide': deltaOf(wish) === 'wide' }">
        <div class="wish-card__head">
          <strong>{{ wish.label }}</strong>
          <span class="wish-badge">{{ stageLabel(wish.lastStage) }}</span>
          <span v-if="deltaOf(wish) !== 'none'" class="wish-badge" :class="'wish-badge--' + deltaOf(wish)">
            {{ t(`grossanlass.planung.feinPartner.delta.${deltaOf(wish)}`) }}
          </span>
          <span v-if="enoughByWish[wish.id]?.enough" class="wish-badge wish-badge--enough">
            {{ enoughBadge(wish.id) }}
          </span>
        </div>
        <p class="wish-meta">
          {{ t('grossanlass.materials.detailWishSubmitted', {
            when: formatIso(wish.createdAt || ''),
            who: wish.who || wish.ressort,
          }) }}
        </p>
        <p class="wish-meta">{{ wish.ressort }} · {{ t('grossanlass.materialUebersicht.qty', { n: wish.qty }) }}</p>
        <p class="wish-meta" :class="{ 'wish-meta--need': true, 'wish-meta--unset': needUnset(wish) }">
          {{ needUnset(wish)
            ? t('grossanlass.materials.detailWishNeedUnset')
            : t('grossanlass.materials.detailWishNeed', {
              from: formatIso(wish.fromIso),
              to: formatIso(wish.toIso),
            })
          }}
        </p>
        <p v-if="deltaOf(wish) !== 'none'" class="wish-advice">
          {{ t(`grossanlass.planung.feinPartner.advice.${deltaOf(wish)}`) }}
        </p>
        <GrossanlassWishEnoughOnHandField
          v-if="enoughByWish[wish.id]"
          v-model="enoughByWish[wish.id]"
          :commitments="commitments"
          :default-commitment="articleCommitment"
        />

        <ActivityDateTimeFields
          v-if="editors[wish.id]"
          v-model:range="editors[wish.id].range"
          v-model:time-from="editors[wish.id].timeFrom"
          v-model:time-to="editors[wish.id].timeTo"
          date-mode="range"
          :department-id="departmentId"
          :show-presets="true"
          :show-markers="true"
          :allow-past="true"
          preset-mode="fixed-periods"
          :label-from="t('grossanlass.materialUebersicht.fieldFromTime')"
          :label-to="t('grossanlass.materialUebersicht.fieldToTime')"
        />

        <div class="wish-card__actions">
          <EButton
            variant="primary"
            size="small"
            :disabled="savingId === wish.id"
            @click="saveWish(wish)"
          >
            {{ t('grossanlass.materials.detailWishSave') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="$emit('book', wish.id)">
            {{ t('grossanlass.materialUebersicht.bookFromWish') }}
          </EButton>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { ActivityDateTimeFields } from '@/components/activities/wizard'
import { updateGrossanlassWish } from '@/api/grossanlassWishes'
import { getGrossanlassCommitments, updateGrossanlassCommitment, type GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { useToast } from '@/composables/useToast'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import { localDateToIsoDateString } from '@/utils/activityDateIso'
import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import GrossanlassWishEnoughOnHandField from '@/components/grossanlass/GrossanlassWishEnoughOnHandField.vue'
import {
  enoughOnHandBadgeFromValue,
  enoughOnHandFromTemplate,
  enoughOnHandToPayload,
  emptyEnoughOnHand,
  validateEnoughOnHand,
  type EnoughOnHandValue,
} from '@/utils/grossanlassEnoughOnHand'
import {
  combineIso,
  feinDeltaKind,
  formatGaIsoLabel,
  type GaZusageArticle,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  parseLocalDate,
  type GaPreviewWishTemplate,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { wishPeriodLooksLikeSubmitTime } from '@/utils/grossanlassWishPeriod'

const props = defineProps<{
  departmentId: string
  article: GaZusageArticle
  wishes: GaPreviewWishTemplate[]
}>()

const emit = defineEmits<{
  book: [wishId: string]
  saved: []
}>()

const { t, locale } = useI18n()
const toast = useToast()
const savingId = ref('')

type Editor = {
  range: [Date, Date] | null
  timeFrom: Date | null
  timeTo: Date | null
}

const editors = reactive<Record<string, Editor>>({})
const enoughByWish = reactive<Record<string, EnoughOnHandValue>>({})
const commitments = ref<GrossanlassCommitment[]>([])

const articleCommitment = computed(() => ({
  id: props.article.id,
  source: props.article.source,
  name: props.article.name,
}))

const partnerLabel = computed(() => {
  if (!props.article.presentFromIso || !props.article.presentToIso) return ''
  return t('grossanlass.planung.feinPartner.partnerWindow', {
    partner: props.article.source,
    from: formatIso(props.article.presentFromIso),
    to: formatIso(props.article.presentToIso),
  })
})

watch(
  () => props.wishes.map((wish) => [
    wish.id,
    wish.fromIso,
    wish.toIso,
    wish.enoughOnHand ? 1 : 0,
    wish.enoughOnHandSource || '',
    wish.enoughOnHandRefId || '',
  ].join(':')).join('|'),
  () => {
    for (const wish of props.wishes) {
      editors[wish.id] = toEditorFromWish(wish)
      enoughByWish[wish.id] = enoughOnHandFromTemplate(wish)
    }
  },
  { immediate: true },
)

watch(
  () => props.departmentId,
  () => { void loadCommitments() },
  { immediate: true },
)

async function loadCommitments() {
  if (!props.departmentId) {
    commitments.value = []
    return
  }
  try {
    commitments.value = await getGrossanlassCommitments(props.departmentId)
  } catch {
    commitments.value = []
  }
}

function enoughBadge(wishId: string): string {
  return enoughOnHandBadgeFromValue(enoughByWish[wishId] ?? emptyEnoughOnHand(), (key, values) => String(t(key, values ?? {})))
}

function formatIso(iso: string): string {
  if (!iso) return '—'
  return formatGaIsoLabel(iso, locale.value)
}

function stageLabel(stage?: string): string {
  return stage === 'fein'
    ? t('grossanlass.planung.wishForms.stageFein')
    : t('grossanlass.planung.wishForms.stageGrob')
}

function needUnset(wish: GaPreviewWishTemplate): boolean {
  return wishPeriodLooksLikeSubmitTime(wish.fromIso, wish.toIso, wish.createdAt)
}

function deltaOf(wish: GaPreviewWishTemplate): 'wide' | 'fit' | 'none' {
  if (needUnset(wish)) return 'none'
  return feinDeltaKind({
    ...props.article,
    feinWish: { label: wish.label, ressort: wish.ressort, fromIso: wish.fromIso, toIso: wish.toIso },
  })
}

function toEditorFromWish(wish: GaPreviewWishTemplate): Editor {
  if (needUnset(wish) && props.article.presentFromIso && props.article.presentToIso) {
    return toEditor(props.article.presentFromIso, props.article.presentToIso)
  }
  return toEditor(wish.fromIso, wish.toIso)
}

function toEditor(fromIso: string, toIso: string): Editor {
  const from = snapDateToQuarterHour(parseLocalDate(fromIso))
  const to = snapDateToQuarterHour(parseLocalDate(toIso))
  return {
    range: [startOfLocalDay(from), startOfLocalDay(to)],
    timeFrom: from,
    timeTo: to,
  }
}

function editorToIso(editor: Editor): { from: string; to: string } | null {
  if (!editor.range || !editor.timeFrom || !editor.timeTo) return null
  const from = combineDayAndTime(editor.range[0], editor.timeFrom)
  const to = combineDayAndTime(editor.range[1], editor.timeTo)
  return {
    from: combineIso(localDateToIsoDateString(from), `${pad(from.getHours())}:${pad(from.getMinutes())}`),
    to: combineIso(localDateToIsoDateString(to), `${pad(to.getHours())}:${pad(to.getMinutes())}`),
  }
}

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

async function saveWish(wish: GaPreviewWishTemplate) {
  const editor = editors[wish.id]
  const iso = editor ? editorToIso(editor) : null
  if (!iso || !props.departmentId) return
  const enough = enoughByWish[wish.id] ?? emptyEnoughOnHand()
  const enoughError = validateEnoughOnHand(enough, (key, values) => String(t(key, values ?? {})))
  if (enoughError) {
    toast.error(enoughError)
    return
  }
  savingId.value = wish.id
  try {
    if (wish.roundId) {
      const enoughFields = enoughOnHandToPayload(enough)
      await updateGrossanlassWish(props.departmentId, wish.roundId, wish.id, {
        valid_from: iso.from,
        valid_to: iso.to,
        last_stage: 'fein',
        enough_on_hand: enoughFields.enough_on_hand,
        enough_on_hand_source: enoughFields.enough_on_hand_source as 'stock' | 'commitment' | null | undefined,
        enough_on_hand_detail: enoughFields.enough_on_hand_detail,
        enough_on_hand_ref_id: enoughFields.enough_on_hand_ref_id,
      })
    }
    await updateGrossanlassCommitment(props.departmentId, props.article.id, {
      wish_from: iso.from,
      wish_to: iso.to,
      wish_label: wish.label,
    })
    toast.success(t('grossanlass.materials.detailWishSaved'))
    emit('saved')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    savingId.value = ''
  }
}
</script>

<style scoped>
.panel-intro,
.panel-partner,
.wish-meta,
.wish-advice {
  margin: 0 0 8px;
  font-size: 0.85rem;
  color: #64748b;
}
.wish-meta--unset { color: #9a3412; font-weight: 600; }
.wish-advice { color: #9a3412; font-weight: 600; }
.wish-list {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.wish-card {
  display: grid;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.wish-card--wide { border-color: #fdba74; }
.wish-card__head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.wish-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  background: #e2e8f0;
  color: #334155;
}
.wish-badge--wide { background: #ffedd5; color: #c2410c; }
.wish-badge--fit { background: #dcfce7; color: #166534; }
.wish-badge--enough { background: #dbeafe; color: #1d4ed8; }
.wish-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>

