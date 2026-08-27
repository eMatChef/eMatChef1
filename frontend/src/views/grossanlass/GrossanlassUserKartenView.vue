<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.chain.cardsIntroLive') }}</p>
    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />
    <v-alert v-else-if="error" type="error" variant="tonal" :text="error" class="mb-3" />
    <EEmptyState
      v-else-if="cards.length === 0"
      variant="default"
      icon="mdi-card-account-details"
      :title="t('grossanlass.chain.cardsEmptyTitle')"
      :description="t('grossanlass.chain.cardsEmptyText')"
    />

    <div v-if="!isLoading" class="toolbar">
      <EButton variant="primary" size="small" @click="showHelperDialog = true">
        {{ t('grossanlass.planung.ressorts.helperHeading') }}
      </EButton>
        <EButton
          v-if="cards.length > 0"
          variant="secondary"
          size="small"
          @click="printBulk"
        >
          {{ t('grossanlass.chain.printAllCards', { count: bulkRows.length }) }}
        </EButton>
    </div>

    <EDialog v-model="showHelperDialog" :max-width="480" :title="t('grossanlass.planung.ressorts.helperHeading')">
      <GrossanlassHelperInviteForm
        :department-id="departmentId"
        :groups="groups"
        @created="onHelperCreated"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showHelperDialog = false">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>

    <div v-if="!isLoading && cards.length > 0" class="layout">
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-check">
              <input
                type="checkbox"
                :checked="allSelected"
                :indeterminate="partialSelected"
                :aria-label="t('grossanlass.chain.selectAllCards')"
                @click.stop.prevent="toggleAll"
              >
            </th>
            <th>{{ t('grossanlass.chain.colPerson') }}</th>
            <th>{{ t('grossanlass.chain.colRessort') }}</th>
            <th>{{ t('grossanlass.chain.colCard') }}</th>
            <th>{{ t('grossanlass.chain.colDrive') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="card in cards"
            :key="card.user_id"
            :class="{ 'is-active': previewId === card.user_id }"
            @click="previewId = card.user_id"
          >
            <td class="col-check" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.includes(card.user_id)"
                :aria-label="card.name"
                @change="toggleSelected(card.user_id)"
              >
            </td>
            <td>
              <strong>{{ card.name }}</strong>
              <span class="meta">{{ card.role }} · {{ card.code }}</span>
            </td>
            <td>{{ card.ressort }}</td>
            <td>
              <span class="chip" :class="{ ok: card.printed }">
                {{ card.printed ? t('grossanlass.chain.cardPrinted') : t('grossanlass.chain.cardMissing') }}
              </span>
            </td>
            <td>
              <button type="button" class="drive-open" @click.stop="openDrive(card)">
                <span class="chip" :class="{ ok: card.may_drive }">{{ driveSummary(card) }}</span>
              </button>
            </td>
            <td>
              <div class="row-actions">
                <EButton variant="secondary" size="small" @click.stop="printOne(card)">
                  {{ t('grossanlass.chain.printCard') }}
                </EButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <aside v-if="preview" class="badge" aria-label="Badge preview">
        <p class="badge__kicker">{{ preview.event_name }} · eMatChef</p>
        <p class="badge__name">{{ preview.name }}</p>
        <p class="badge__ressort">{{ preview.ressort }} · {{ preview.role }}</p>
        <a
          v-if="previewQrUrl"
          class="badge__qr-link"
          :href="previewQrUrl"
          target="_blank"
          rel="noopener noreferrer"
          @click.stop
        >
          <PublicQrTag
            :url="previewQrUrl"
            :code="preview.code"
            :size="148"
            :image-label="preview.name"
            :image-entity-id="preview.code"
          />
        </a>
        <p class="badge__code">{{ preview.code }}</p>
        <p v-if="preview.may_drive" class="badge__drive">{{ t('grossanlass.chain.mayDriveBadge') }}</p>
        <p v-if="preview.drive_classes?.length" class="badge__classes">{{ driveClassLabels(preview).join(' · ') }}</p>
      </aside>
    </div>

    <GrossanlassDriveRightsDialog
      v-model="showDriveDialog"
      :department-id="departmentId"
      :card="driveCard"
      @saved="replaceCard"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'
import GrossanlassHelperInviteForm from '@/components/grossanlass/GrossanlassHelperInviteForm.vue'
import GrossanlassDriveRightsDialog from '@/components/grossanlass/GrossanlassDriveRightsDialog.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  getGrossanlassUserCards,
  updateGrossanlassUserCard,
  type GrossanlassUserCard,
} from '@/api/grossanlassUserCards'
import { driveClassLabelKey } from '@/views/grossanlass/grossanlassDriveCategories'
import { resolveUserCardPublicUrl } from '@/utils/publicQrUrl'
import { usePrintJob } from '@/composables/usePrintJob'
import { USER_CARD_PRINT_CONTENT } from '@/print/layoutFields'
import type { PrintJobItem } from '@/print/printJob'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()
const { openPrint } = usePrintJob()
const cards = ref<GrossanlassUserCard[]>([])
const groups = ref<GrossanlassGroup[]>([])
const previewId = ref('')
const isLoading = ref(true)
const error = ref('')
const showHelperDialog = ref(false)
const showDriveDialog = ref(false)
const driveCardId = ref('')
const selectedIds = ref<string[]>([])

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const preview = computed(
  () => cards.value.find((row) => row.user_id === previewId.value) ?? cards.value[0] ?? null,
)
const previewQrUrl = computed(() => {
  const row = preview.value
  if (!row) return ''
  return resolveUserCardPublicUrl(row.qr_url, row.code)
})
const bulkRows = computed(() => {
  if (!selectedIds.value.length) return cards.value
  const picked = new Set(selectedIds.value)
  return cards.value.filter((row) => picked.has(row.user_id))
})
const allSelected = computed(
  () => cards.value.length > 0 && selectedIds.value.length === cards.value.length,
)
const partialSelected = computed(
  () => selectedIds.value.length > 0 && selectedIds.value.length < cards.value.length,
)

function toggleSelected(userId: string) {
  selectedIds.value = selectedIds.value.includes(userId)
    ? selectedIds.value.filter((id) => id !== userId)
    : [...selectedIds.value, userId]
}

function toggleAll() {
  selectedIds.value = allSelected.value ? [] : cards.value.map((row) => row.user_id)
}
const driveCard = computed(
  () => cards.value.find((row) => row.user_id === driveCardId.value) ?? null,
)

function driveClassLabels(card: GrossanlassUserCard): string[] {
  return (card.drive_classes ?? []).map((code) => t(driveClassLabelKey(code)))
}

function driveSummary(card: GrossanlassUserCard): string {
  const labels = driveClassLabels(card)
  if (!labels.length) return t('grossanlass.chain.drive.none')
  const prefix = card.may_drive
    ? t('grossanlass.chain.drive.summaryOk')
    : t('grossanlass.chain.drive.summaryPending')
  return `${prefix}: ${labels.join(', ')}`
}

function openDrive(card: GrossanlassUserCard) {
  driveCardId.value = card.user_id
  previewId.value = card.user_id
  showDriveDialog.value = true
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    const [nextCards, nextGroups] = await Promise.all([
      getGrossanlassUserCards(departmentId.value),
      getGrossanlassGroups(departmentId.value),
    ])
    cards.value = nextCards
    groups.value = nextGroups
    if (!previewId.value && cards.value[0]) previewId.value = cards.value[0].user_id
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.chain.cardsLoadError')
  } finally {
    isLoading.value = false
  }
}

async function onHelperCreated(result: { card: GrossanlassUserCard }) {
  showHelperDialog.value = false
  await load()
  previewId.value = result.card.user_id
}

function replaceCard(next: GrossanlassUserCard) {
  cards.value = cards.value.map((row) => (row.user_id === next.user_id ? next : row))
}

function cardPublicUrl(card: GrossanlassUserCard): string {
  return resolveUserCardPublicUrl(card.qr_url, card.code)
}

function cardPrintItem(card: GrossanlassUserCard): PrintJobItem {
  const drive = driveSummary(card)
  const url = cardPublicUrl(card)
  const item: PrintJobItem = {
    label: card.name,
    public_code: card.code,
    public_url: url,
    extras: {
      event: card.event_name,
      ressort: card.ressort,
      role: card.role,
      drive,
    },
  }
  if (departmentId.value) {
    item.cart = {
      department_id: departmentId.value,
      entity_type: 'user_card',
      entity_id: card.user_id,
      label: `${card.name} · ${card.ressort}`,
      public_code: card.code,
      public_url: url,
    }
  }
  return item
}

async function markPrinted(rows: GrossanlassUserCard[]) {
  if (!departmentId.value) return
  for (const card of rows) {
    if (card.printed) continue
    replaceCard(await updateGrossanlassUserCard(departmentId.value, card.user_id, { print: true }))
  }
}

function printOne(card: GrossanlassUserCard) {
  if (!departmentId.value) return
  previewId.value = card.user_id
  openPrint({
    departmentId: departmentId.value,
    items: [cardPrintItem(card)],
    availableFields: USER_CARD_PRINT_CONTENT,
    kind: 'user_card',
    onPrinted: async () => {
      try {
        await markPrinted([card])
      } catch {
        toast.error(t('grossanlass.chain.cardsSaveError'))
      }
    },
  })
}

function printBulk() {
  if (!departmentId.value) return
  const rows = bulkRows.value
  if (!rows.length) return
  openPrint({
    departmentId: departmentId.value,
    items: rows.map(cardPrintItem),
    availableFields: USER_CARD_PRINT_CONTENT,
    kind: 'user_card',
    onPrinted: async () => {
      try {
        await markPrinted(rows)
      } catch {
        toast.error(t('grossanlass.chain.cardsSaveError'))
      }
    },
  })
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; max-width: 640px; }
.toolbar { margin-bottom: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
.row-actions { display: flex; flex-wrap: wrap; gap: 4px; }
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 240px;
  gap: 16px;
  align-items: start;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.data-table th { background: #f8fafc; }
.data-table tr { cursor: pointer; }
.col-check { width: 36px; padding-right: 0; vertical-align: middle; }
.col-check input { margin: 0; cursor: pointer; }
.data-table tr.is-active { background: #ecfdf3; }
.meta { display: block; color: #64748b; font-size: 0.75rem; margin-top: 2px; }
.chip { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #ffedd5; color: #c2410c; }
.chip.ok { background: #dcfce7; color: #166534; }
.drive-open { border: 0; background: none; padding: 0; cursor: pointer; text-align: left; }
.badge__classes { margin: 4px 0 0; font-size: 0.68rem; color: #166534; font-weight: 600; }
.badge {
  border: 1px solid #166534;
  border-radius: 14px;
  padding: 16px 14px;
  background: linear-gradient(180deg, #ecfdf3 0%, #fff 55%);
  text-align: center;
}
.badge__kicker { margin: 0; font-size: 0.68rem; letter-spacing: 0.08em; text-transform: uppercase; color: #166534; font-weight: 700; }
.badge__name { margin: 8px 0 0; font-size: 1.15rem; font-weight: 800; }
.badge__ressort { margin: 2px 0 12px; color: #64748b; font-size: 0.8rem; }
.badge__qr-link { display: inline-flex; margin: 0 auto 8px; }
.badge__code { margin: 0; font-family: ui-monospace, monospace; font-size: 0.72rem; color: #334155; }
.badge__drive { margin: 8px 0 0; font-size: 0.72rem; font-weight: 700; color: #166534; }
@media (max-width: 860px) {
  .layout { grid-template-columns: 1fr; }
}
</style>
