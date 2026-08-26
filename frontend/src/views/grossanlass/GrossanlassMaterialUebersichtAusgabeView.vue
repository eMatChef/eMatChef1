<template>
  <div class="ga-preview-page">
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
          :key="card.user_id"
          type="button"
          class="person-chip"
          :class="{ 'is-on': holderId === card.user_id }"
          @click="holderId = card.user_id"
        >
          {{ card.name }}
          <span>{{ card.ressort }}</span>
        </button>
        <EButton variant="secondary" size="small" :disabled="!holder" @click="holderId = ''">
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
          <span v-if="holder.may_drive" class="chip ok">{{ t('grossanlass.chain.mayDrive') }}</span>
        </div>
        <EButton
          variant="primary"
          size="small"
          :disabled="!dueNow.length"
          @click="issueAll"
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
              <strong>{{ row.when_label }}</strong>
              <span v-if="row.bucket === 'express'" class="chip express">{{ t('grossanlass.chain.expressShort') }}</span>
            </div>
            <div class="issue-card__body">
              <strong>{{ row.name }}</strong>
              <span class="meta">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }} · {{ row.planned_for || t('grossanlass.chain.noEinsatz') }}</span>
            </div>
            <EButton variant="primary" size="small" :disabled="row.place === 'out'" @click="issueOne(row.id)">
              {{ t('grossanlass.chain.issueAction') }}
            </EButton>
          </li>
        </ul>
        <p v-if="!dueNow.length" class="empty">{{ t('grossanlass.chain.dueNowEmpty') }}</p>
      </template>
      <p v-else class="empty">{{ t('grossanlass.chain.scanPrompt') }}</p>
    </template>

    <template v-else>
      <ul class="issue-list">
        <li v-for="row in openIssues" :key="row.id" class="issue-card" :class="row.bucket">
          <div class="issue-card__when"><strong>{{ row.when_label }}</strong></div>
          <div class="issue-card__body">
            <strong>{{ row.name }}</strong>
            <span class="meta">{{ row.recipient }} · {{ t(`grossanlass.chain.issueBucket.${row.bucket}`) }}</span>
          </div>
          <EButton variant="primary" size="small" :disabled="row.place === 'out'" @click="issueOne(row.id)">
            {{ t('grossanlass.chain.issueAction') }}
          </EButton>
        </li>
      </ul>
      <p v-if="!openIssues.length" class="empty">{{ t('grossanlass.chain.issueEmpty') }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const uebersicht = useGaUebersicht()
const desk = ref<'person' | 'material'>('person')
const holderId = ref('')

const cards = computed(() => uebersicht.data.value?.cards ?? [])
const issues = computed(() => uebersicht.data.value?.issues ?? [])
const holder = computed(() => cards.value.find((card) => card.user_id === holderId.value) ?? null)
const openIssues = computed(() => issues.value.filter((row) => row.place !== 'out'))
const dueNow = computed(() => {
  if (!holder.value) return []
  return openIssues.value.filter((row) =>
    row.bucket !== 'tomorrow'
    && (row.recipient === holder.value?.ressort || row.person_id === holder.value?.user_id),
  )
})

function goCards() {
  const id = String(route.params.departmentId || '')
  if (id) void router.push(`/${id}/einstellungen/karten`)
}

async function issueOne(id: string) {
  try {
    await uebersicht.issue(id, holderId.value || undefined)
    toast.success(t('grossanlass.chain.issuedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function issueAll() {
  for (const row of dueNow.value) {
    await issueOne(row.id)
  }
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.desk-toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.bucket-toggle { display: flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
.bucket-toggle__btn { border: 0; background: #fff; padding: 8px 12px; cursor: pointer; font-weight: 600; }
.bucket-toggle__btn.is-active { background: #111827; color: #fff; }
.scan-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; align-items: center; }
.person-chip { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; padding: 8px 10px; text-align: left; cursor: pointer; display: flex; flex-direction: column; }
.person-chip.is-on { border-color: #111827; }
.person-chip span { font-size: 0.75rem; color: #64748b; }
.holder-card, .issue-card { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; margin-bottom: 10px; }
.meta { display: block; font-size: 0.8rem; color: #64748b; }
.chip { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #ffedd5; color: #c2410c; margin-left: 6px; }
.chip.ok { background: #dcfce7; color: #166534; }
.chip.express { background: #fee2e2; color: #b91c1c; }
.issue-list { list-style: none; margin: 0; padding: 0; }
.issue-card__body { flex: 1; }
.empty, .bucket-hint { color: #64748b; font-size: 0.85rem; }
h3 { margin: 16px 0 4px; font-size: 0.95rem; }
</style>
