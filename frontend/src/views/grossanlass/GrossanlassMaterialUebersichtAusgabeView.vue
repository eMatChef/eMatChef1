<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.chain.issueDeskIntro') }}</p>

    <div class="desk-toolbar">
      <div class="bucket-toggle" role="tablist">
        <button
          type="button"
          class="bucket-toggle__btn"
          :class="{ 'is-active': desk === 'person' }"
          @click="desk = 'person'"
        >
          {{ t('grossanlass.chain.deskPerson') }}
        </button>
        <button
          type="button"
          class="bucket-toggle__btn"
          :class="{ 'is-active': desk === 'material' }"
          @click="desk = 'material'"
        >
          {{ t('grossanlass.chain.deskMaterial') }}
        </button>
      </div>
      <EButton variant="secondary" size="small" @click="goCards">
        {{ t('grossanlass.chain.openUserCards') }}
      </EButton>
    </div>

    <template v-if="desk === 'person'">
      <div class="scan-row">
        <button
          v-for="card in cards"
          :key="card.id"
          type="button"
          class="person-chip"
          :class="{ 'is-on': holderId === card.id }"
          @click="holderId = card.id"
        >
          {{ card.name }}
          <span>{{ card.ressort }}</span>
        </button>
        <EButton variant="secondary" size="small" :disabled="!holder" @click="clearHolder">
          {{ t('grossanlass.chain.scanNext') }}
        </EButton>
      </div>

      <section v-if="holder" class="holder-card">
        <div>
          <strong>{{ holder.name }}</strong>
          <span class="meta">{{ holder.ressort }} · {{ holder.role }} · {{ holder.code }}</span>
          <span class="chip" :class="{ ok: holder.printed }">
            {{ holder.printed ? t('grossanlass.chain.cardPrinted') : t('grossanlass.chain.cardMissing') }}
          </span>
          <span v-if="holder.mayDrive" class="chip ok">{{ t('grossanlass.chain.mayDrive') }}</span>
        </div>
        <EButton
          variant="primary"
          size="small"
          :disabled="!dueNow.length"
          @click="issueAllNow"
        >
          {{ t('grossanlass.chain.issueAllNow', { count: dueNow.length }) }}
        </EButton>
      </section>

      <template v-if="holder">
        <h3>{{ t('grossanlass.chain.dueNowTitle') }}</h3>
        <p class="bucket-hint">{{ t('grossanlass.chain.dueNowHint') }}</p>
        <ul class="issue-list">
          <li v-for="row in dueNow" :key="row.id" class="issue-card" :class="row.bucket">
            <div class="issue-card__when">
              <strong>{{ row.whenLabel }}</strong>
              <span v-if="row.bucket === 'express'" class="chip express">{{ t('grossanlass.chain.expressShort') }}</span>
            </div>
            <div class="issue-card__body">
              <strong>{{ row.name }}</strong>
              <span class="meta">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }} · {{ row.plannedFor || t('grossanlass.chain.noEinsatz') }}</span>
            </div>
            <EButton variant="primary" size="small" @click="issueToHolder(row)">
              {{ t('grossanlass.chain.issueAction') }}
            </EButton>
          </li>
        </ul>
        <p v-if="!dueNow.length" class="empty">{{ t('grossanlass.chain.dueNowEmpty') }}</p>

        <h3>{{ t('grossanlass.chain.dueLaterTitle') }}</h3>
        <ul class="issue-list">
          <li v-for="row in dueLater" :key="row.id" class="issue-card is-later">
            <div class="issue-card__when"><strong>{{ row.whenLabel }}</strong></div>
            <div class="issue-card__body">
              <strong>{{ row.name }}</strong>
              <span class="meta">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }} · {{ t('grossanlass.chain.notToday') }}</span>
            </div>
          </li>
        </ul>
        <p v-if="!dueLater.length" class="empty">{{ t('grossanlass.chain.dueLaterEmpty') }}</p>
      </template>
      <p v-else class="empty">{{ t('grossanlass.chain.scanPrompt') }}</p>
    </template>

    <template v-else>
      <div class="bucket-toggle" role="tablist">
        <button
          v-for="bucket in buckets"
          :key="bucket.id"
          type="button"
          class="bucket-toggle__btn"
          :class="[`is-${bucket.id}`, { 'is-active': view === bucket.id }]"
          @click="view = bucket.id"
        >
          {{ t(`grossanlass.chain.issueBucket.${bucket.id}`) }}
          <span>{{ bucket.count }}</span>
        </button>
      </div>
      <p class="bucket-hint">{{ t(`grossanlass.chain.issueBucketHint.${view}`) }}</p>
      <ul class="issue-list">
        <li v-for="row in visible" :key="row.id" class="issue-card" :class="row.bucket">
          <div class="issue-card__when">
            <strong>{{ row.whenLabel }}</strong>
            <span v-if="row.bucket === 'express'" class="chip express">{{ t('grossanlass.chain.expressShort') }}</span>
            <span v-else class="chip" :class="row.place">{{ t(`grossanlass.chain.place.${row.place}`) }}</span>
          </div>
          <div class="issue-card__body">
            <strong>{{ row.name }}</strong>
            <span class="meta">
              {{ t('grossanlass.chain.qtyLine', { n: row.qty }) }}
              · {{ row.plannedFor || t('grossanlass.chain.noEinsatz') }}
            </span>
            <span v-if="row.place === 'out'" class="meta">
              {{ row.recipient }}
              <template v-if="personName(row)"> · {{ personName(row) }}</template>
            </span>
          </div>
          <EButton variant="primary" size="small" :disabled="row.place === 'out'" @click="openIssue(row)">
            {{ t('grossanlass.chain.issueAction') }}
          </EButton>
        </li>
      </ul>
      <p v-if="!visible.length" class="empty">{{ t('grossanlass.chain.issueEmpty') }}</p>
    </template>

    <EDialog v-model="dialogOpen" :title="t('grossanlass.chain.issueDialogTitle')" max-width="520">
      <p v-if="active" class="hint">
        {{ active.name }} · {{ active.whenLabel }}
        · {{ active.plannedFor || t('grossanlass.chain.noEinsatz') }}
      </p>
      <ESelect
        v-model="recipientKind"
        :label="t('grossanlass.chain.recipientKind')"
        :items="kindItems"
        hide-details
      />
      <ESelect
        v-model="recipient"
        :label="t('grossanlass.chain.recipient')"
        :items="recipientItems"
        hide-details
      />
      <ESelect
        v-model="personId"
        :label="t('grossanlass.chain.pickupPerson')"
        :items="personItems"
        hide-details
      />
      <p v-if="selectedPerson && !selectedPerson.printed" class="warn">
        {{ t('grossanlass.chain.cardNotPrinted') }}
      </p>
      <label v-if="active?.family === 'vehicle'" class="driver">
        <input v-model="driverOk" type="checkbox">
        {{ t('grossanlass.chain.driverCheck') }}
      </label>
      <template #actions>
        <EButton variant="secondary" size="small" @click="dialogOpen = false">{{ t('common.close') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!recipient || !personId" @click="confirmIssue">
          {{ t('grossanlass.chain.issueConfirm') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import {
  issueItem,
  issuesForCard,
  listIssues,
  listUserCards,
  userCardById,
  type GaChainIssue,
  type GaIssueBucket,
  type GaIssueRecipientKind,
} from '@/views/grossanlass/grossanlassChainPreviewStore'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const desk = ref<'person' | 'material'>('person')
const view = ref<GaIssueBucket>('today')
const holderId = ref('')
const issues = computed(() => listIssues())
const cards = computed(() => listUserCards())
const dialogOpen = ref(false)
const active = ref<GaChainIssue | null>(null)
const recipientKind = ref<GaIssueRecipientKind>('ressort')
const recipient = ref('')
const personId = ref('')
const driverOk = ref(false)

const holder = computed(() => (holderId.value ? userCardById(holderId.value) : undefined))
const holderLines = computed(() => (holderId.value ? issuesForCard(holderId.value) : []))
const dueNow = computed(() =>
  holderLines.value.filter((row) => row.place !== 'out' && (row.bucket === 'today' || row.bucket === 'express')),
)
const dueLater = computed(() =>
  holderLines.value.filter((row) => row.place !== 'out' && row.bucket === 'tomorrow'),
)

const buckets = computed(() =>
  (['today', 'tomorrow', 'express'] as const).map((id) => ({
    id,
    count: issues.value.filter((row) => row.bucket === id && row.place !== 'out').length,
  })),
)

const visible = computed(() => issues.value.filter((row) => row.bucket === view.value))

const kindItems = computed(() => [
  { title: t('grossanlass.chain.kindRessort'), value: 'ressort' },
  { title: t('grossanlass.chain.kindGuest'), value: 'guest' },
])

const recipientItems = computed(() => {
  if (recipientKind.value === 'guest') {
    return [
      { title: t('grossanlass.materials.sourceWinterthur'), value: t('grossanlass.materials.sourceWinterthur') },
      { title: t('grossanlass.materials.sourceZuerich'), value: t('grossanlass.materials.sourceZuerich') },
      { title: t('grossanlass.materials.sourceUster'), value: t('grossanlass.materials.sourceUster') },
    ]
  }
  return [
    { title: 'Verpflegung', value: 'Verpflegung' },
    { title: 'Bau', value: 'Bau' },
    { title: 'Sicherheit', value: 'Sicherheit' },
  ]
})

const personItems = computed(() =>
  cards.value.map((card) => ({
    title: `${card.name} · ${card.ressort}${card.printed ? '' : ` · ${t('grossanlass.chain.noCardYet')}`}`,
    value: card.id,
  })),
)

const selectedPerson = computed(() => (personId.value ? userCardById(personId.value) : undefined))

function personName(row: GaChainIssue): string {
  return row.personId ? (userCardById(row.personId)?.name ?? '') : ''
}

function canIssueVehicle(row: GaChainIssue, personIdValue: string): boolean {
  if (row.family !== 'vehicle') return true
  const person = userCardById(personIdValue)
  return !!person?.mayDrive || driverOk.value
}

function issueToHolder(row: GaChainIssue) {
  const person = holder.value
  if (!person) return
  if (!canIssueVehicle(row, person.id)) {
    toast.error(t('grossanlass.chain.needDriver'))
    return
  }
  issueItem(row.id, row.recipientKind ?? 'ressort', row.recipient || person.ressort, person.mayDrive, person.id)
  toast.success(t('grossanlass.chain.issuedToast'))
}

function issueAllNow() {
  const person = holder.value
  if (!person) return
  for (const row of dueNow.value) {
    if (!canIssueVehicle(row, person.id)) {
      toast.error(t('grossanlass.chain.needDriver'))
      return
    }
  }
  for (const row of [...dueNow.value]) {
    issueItem(row.id, row.recipientKind ?? 'ressort', row.recipient || person.ressort, person.mayDrive, person.id)
  }
  toast.success(t('grossanlass.chain.issuedAllToast'))
}

function clearHolder() {
  holderId.value = ''
}

function openIssue(row: GaChainIssue) {
  active.value = row
  recipientKind.value = row.recipientKind ?? 'ressort'
  recipient.value = row.recipient || (recipientKind.value === 'ressort' ? 'Verpflegung' : '')
  const match = cards.value.find((card) => card.ressort === row.recipient && card.printed)
    ?? cards.value.find((card) => card.ressort === row.recipient)
  personId.value = row.personId || match?.id || ''
  driverOk.value = !!match?.mayDrive
  dialogOpen.value = true
}

function confirmIssue() {
  const row = active.value
  const person = selectedPerson.value
  if (!row || !recipient.value || !person) return
  if (row.family === 'vehicle' && !person.mayDrive && !driverOk.value) {
    toast.error(t('grossanlass.chain.needDriver'))
    return
  }
  if (row.family === 'vehicle' && person.mayDrive) driverOk.value = true
  issueItem(row.id, recipientKind.value, recipient.value, driverOk.value, person.id)
  dialogOpen.value = false
  toast.success(t('grossanlass.chain.issuedToast'))
}

function goCards() {
  const id = String(route.params.departmentId || '')
  if (id) void router.push(`/${id}/einstellungen/karten`)
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; }
.desk-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.bucket-toggle {
  display: inline-flex;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  margin-bottom: 10px;
}
.bucket-toggle__btn {
  border: 0;
  background: transparent;
  padding: 8px 12px;
  font: inherit;
  font-size: 0.85rem;
  color: #64748b;
  cursor: pointer;
  display: inline-flex;
  gap: 6px;
  align-items: center;
}
.bucket-toggle__btn span {
  min-width: 1.2em;
  font-size: 0.72rem;
  font-weight: 700;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 0 6px;
}
.bucket-toggle__btn.is-active { font-weight: 700; color: #0f172a; background: #f8fafc; }
.bucket-toggle__btn.is-express.is-active { background: #fef2f2; color: #b91c1c; }
.scan-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
.person-chip {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  font: inherit;
  text-align: left;
  cursor: pointer;
  display: grid;
  gap: 2px;
}
.person-chip span { font-size: 0.72rem; color: #64748b; }
.person-chip.is-on { border-color: #86efac; background: #ecfdf5; }
.holder-card {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #86efac;
  background: #ecfdf5;
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 16px;
}
.holder-card > div { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
h3 { margin: 16px 0 4px; font-size: 0.95rem; }
.bucket-hint { margin: 0 0 12px; font-size: 0.82rem; color: #64748b; }
.issue-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.issue-card {
  display: grid;
  grid-template-columns: 88px 1fr auto;
  gap: 12px;
  align-items: center;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px 14px;
}
.issue-card.express { border-color: #fecaca; background: #fff7f7; }
.issue-card.is-later { opacity: 0.7; }
.issue-card__when { display: grid; gap: 6px; }
.issue-card__when strong { font-size: 1rem; }
.issue-card__body { display: grid; gap: 2px; min-width: 0; }
.meta { color: #64748b; font-size: 0.78rem; }
.chip { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 999px; width: fit-content; }
.chip.lager { background: #e0f2fe; color: #0369a1; }
.chip.assigned { background: #ffedd5; color: #c2410c; }
.chip.out { background: #dcfce7; color: #166534; }
.chip.express { background: #fee2e2; color: #b91c1c; }
.chip.ok { background: #dcfce7; color: #166534; }
.empty, .hint { color: #64748b; font-size: 0.9rem; }
.warn { margin: 8px 0 0; color: #c2410c; font-size: 0.82rem; }
.driver { display: flex; gap: 8px; align-items: center; margin-top: 12px; font-size: 0.9rem; }
@media (max-width: 640px) {
  .issue-card { grid-template-columns: 1fr; }
}
</style>
