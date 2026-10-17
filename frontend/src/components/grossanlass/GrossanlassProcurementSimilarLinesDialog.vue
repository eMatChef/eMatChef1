<template>
  <EDialog
    v-model="open"
    :max-width="760"
    scrollable
    :title="t('grossanlass.beschaffung.bedarf.takeover.title')"
  >
    <p class="similar-hint">{{ t('grossanlass.beschaffung.bedarf.takeover.hint') }}</p>
    <ELoadingState v-if="isLoading" variant="inline" :message="t('common.loading')" />
    <p v-else-if="loadError" class="similar-error">{{ loadError }}</p>

    <template v-else>
      <section
        v-for="wish in openWishes"
        :key="wish.id"
        class="similar-wish"
        :data-testid="`similar-wish-${wish.id}`"
      >
        <header class="similar-wish__head">
          <strong>{{ wish.quantity }} {{ wish.quantity_unit || '' }} {{ wish.label }}</strong>
          <span class="similar-wish__meta">{{ [wish.group_name, wish.category_name].filter(Boolean).join(' · ') }}</span>
        </header>

        <p v-if="(matches[wish.id] ?? []).length === 0" class="similar-none">
          {{ t('grossanlass.beschaffung.bedarf.takeover.none') }}
        </p>
        <ul v-else class="similar-list">
          <li
            v-for="match in matches[wish.id]"
            :key="match.line_id"
            class="similar-card"
            :data-testid="`similar-line-${match.line_id}`"
          >
            <div class="similar-card__main">
              <div class="similar-card__title">
                <strong>{{ match.label }}</strong>
                <span class="tier" :class="`tier--${match.tier}`">
                  {{ t(`grossanlass.beschaffung.bedarf.takeover.tier.${match.tier}`) }}
                </span>
              </div>
              <p class="similar-card__meta">
                {{ [categoryText(match), `${match.quantity} ${match.quantity_unit}`, supplyText(match), statusText(match)].filter(Boolean).join(' · ') }}
              </p>
              <p class="similar-card__why">
                {{ t('grossanlass.beschaffung.bedarf.takeover.why') }}
                {{ match.reasons.map((r) => t(`grossanlass.beschaffung.bedarf.takeover.reason.${r}`)).join(', ') }}
              </p>
              <p v-if="!match.unit_compatible" class="similar-error">
                {{ t('grossanlass.beschaffung.bedarf.takeover.unitMismatch', { unit: match.quantity_unit, wishUnit: wish.quantity_unit || '' }) }}
              </p>
            </div>
            <div class="similar-card__actions">
              <EButton
                variant="primary"
                size="small"
                :disabled="!match.unit_compatible || busy"
                :loading="busyLineId === match.line_id"
                @click="addTo(wish, match)"
              >
                {{ t('grossanlass.beschaffung.bedarf.takeover.addToLine') }}
              </EButton>
              <EButton variant="secondary" size="small" :disabled="busy" @click="emit('view-line', match.line_id)">
                {{ t('grossanlass.beschaffung.bedarf.takeover.viewLine') }}
              </EButton>
            </div>
          </li>
        </ul>
      </section>

      <p v-if="openWishes.length === 0 && !isLoading" class="similar-none">
        {{ t('grossanlass.beschaffung.bedarf.takeover.allDone') }}
      </p>
    </template>

    <template #actions>
      <EButton variant="secondary" size="small" :disabled="busy" @click="open = false">
        {{ t('grossanlass.beschaffung.bedarf.takeover.later') }}
      </EButton>
      <EButton
        v-if="openWishes.length > 0"
        variant="primary"
        size="small"
        :disabled="busy"
        data-testid="similar-create-new"
        @click="emit('create-new', [...openWishes])"
      >
        {{ t('grossanlass.beschaffung.bedarf.takeover.createNew', { count: openWishes.length }) }}
      </EButton>
    </template>
    <GrossanlassProcurementQuantityConfirmDialog
      v-model="quantityDialogOpen"
      :info="quantityInfo"
      @confirm="onQuantityConfirmed"
    />
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'
import {
  addWishesToGrossanlassProcurementLine,
  getGrossanlassSimilarProcurementLines,
  type GrossanlassProcurementLineMatch,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import { procurementStatusLabel } from '@/utils/grossanlassProcurementStatus'
import GrossanlassProcurementQuantityConfirmDialog from '@/components/grossanlass/GrossanlassProcurementQuantityConfirmDialog.vue'
import { quantityConfirmationFromError, type QuantityConfirmationInfo } from '@/utils/grossanlassProcurementTakeover'

const props = defineProps<{
  departmentId: string
  wishes: GrossanlassProcurementPoolWish[]
}>()

const emit = defineEmits<{
  /** Ein Wunsch wurde erfolgreich einer Position zugeordnet. */
  added: [wishId: string]
  'view-line': [lineId: string]
  'create-new': [wishes: GrossanlassProcurementPoolWish[]]
}>()

const open = defineModel<boolean>({ required: true })
const { t } = useI18n()
const toast = useToast()

const isLoading = ref(false)
const loadError = ref('')
const matches = ref<Record<string, GrossanlassProcurementLineMatch[]>>({})
const doneIds = ref<string[]>([])
const busyLineId = ref<string | null>(null)
const busy = computed(() => busyLineId.value !== null)

const openWishes = computed(() => props.wishes.filter((wish) => !doneIds.value.includes(wish.id)))

watch(open, (visible) => {
  if (visible) void load()
})

async function load() {
  doneIds.value = []
  loadError.value = ''
  matches.value = {}
  if (props.wishes.length === 0) return
  isLoading.value = true
  try {
    matches.value = await getGrossanlassSimilarProcurementLines(props.departmentId, props.wishes.map((w) => w.id))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('grossanlass.beschaffung.bedarf.takeover.errorLoad')
  } finally {
    isLoading.value = false
  }
}

function categoryText(match: GrossanlassProcurementLineMatch): string {
  return [match.category_parent_name, match.category_name].filter(Boolean).join(' / ')
}

function supplyText(match: GrossanlassProcurementLineMatch): string {
  const parts: string[] = []
  if (match.cost_kind) parts.push(t(`grossanlass.beschaffung.kosten.kind.${match.cost_kind}`, match.cost_kind))
  return parts.join(' · ')
}

function statusText(match: GrossanlassProcurementLineMatch): string {
  return procurementStatusLabel(match.status, (key) => t(key))
}

const quantityInfo = ref<QuantityConfirmationInfo | null>(null)
const quantityDialogOpen = ref(false)
const pendingAdd = ref<{ wish: GrossanlassProcurementPoolWish; match: GrossanlassProcurementLineMatch } | null>(null)

async function addTo(
  wish: GrossanlassProcurementPoolWish,
  match: GrossanlassProcurementLineMatch,
  confirmed?: { quantity: number | null },
) {
  busyLineId.value = match.line_id
  try {
    await addWishesToGrossanlassProcurementLine(props.departmentId, match.line_id, {
      wish_line_ids: [wish.id],
      ...(confirmed
        ? { confirm_quantity_change: true, ...(confirmed.quantity !== null ? { quantity: confirmed.quantity } : {}) }
        : {}),
    })
    doneIds.value = [...doneIds.value, wish.id]
    toast.success(t('grossanlass.beschaffung.bedarf.mergeSuccess'))
    emit('added', wish.id)
    if (openWishes.value.length === 0) open.value = false
  } catch (e: unknown) {
    const info = quantityConfirmationFromError(e)
    if (info && !confirmed) {
      quantityInfo.value = info
      pendingAdd.value = { wish, match }
      quantityDialogOpen.value = true
      return
    }
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorMerge'))
  } finally {
    busyLineId.value = null
  }
}

async function onQuantityConfirmed(quantity: number | null) {
  const pending = pendingAdd.value
  pendingAdd.value = null
  if (pending) await addTo(pending.wish, pending.match, { quantity })
}
</script>

<style scoped>
.similar-hint { margin: 0 0 12px; font-size: 0.82rem; color: #475569; }
.similar-error { margin: 4px 0 0; font-size: 0.78rem; color: #dc2626; }
.similar-none { margin: 4px 0 12px; font-size: 0.82rem; color: #64748b; }
.similar-wish { border-top: 1px solid #e2e8f0; padding: 12px 0; }
.similar-wish:first-of-type { border-top: 0; }
.similar-wish__head { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.similar-wish__meta { font-size: 0.78rem; color: #64748b; }
.similar-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.similar-card { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
.similar-card__main { min-width: 0; flex: 1 1 280px; }
.similar-card__title { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
.similar-card__meta, .similar-card__why { margin: 2px 0 0; font-size: 0.78rem; color: #64748b; }
.similar-card__actions { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 6px; }
.tier { font-size: 0.7rem; font-weight: 600; border-radius: 999px; padding: 1px 8px; background: #e2e8f0; color: #334155; }
.tier--exact { background: #dcfce7; color: #166534; }
.tier--similar { background: #dbeafe; color: #1e40af; }
.tier--possible { background: #fef3c7; color: #92400e; }
</style>
