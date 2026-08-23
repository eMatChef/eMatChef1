<template>
  <PageShell
    :title="t('grossanlass.chain.guestTitle')"
    :subtitle="t('grossanlass.chain.guestSubtitle')"
  >
    <GrossanlassPreviewBanner />
    <p v-if="!published" class="hint">{{ t('grossanlass.chain.guestNeedPublish') }}</p>

    <template v-else>
      <ESelect
        v-model="deptId"
        class="dept-select"
        :label="t('grossanlass.chain.guestPick')"
        :items="deptItems"
        hide-details
      />

      <section v-if="participant" class="card">
        <div class="row">
          <strong>{{ t(participant.nameKey) }}</strong>
          <span class="chip" :class="participant.status">{{ t(`grossanlass.chain.participantStatus.${participant.status}`) }}</span>
        </div>
        <div v-if="participant.status === 'pending'" class="actions">
          <EButton variant="primary" size="small" @click="accept">{{ t('grossanlass.chain.acceptInvite') }}</EButton>
          <EButton variant="secondary" size="small" @click="reject">{{ t('grossanlass.chain.rejectInvite') }}</EButton>
        </div>
      </section>

      <template v-if="participant?.status === 'accepted'">
        <section class="card">
          <h3>{{ t('grossanlass.chain.guestJsTitle') }}</h3>
          <p class="hint">{{ t('grossanlass.chain.guestJsHint') }}</p>
          <div v-for="article in jsArticles" :key="article.id" class="js-row">
            <label>{{ article.name }}</label>
            <input v-model.number="jsDraft[article.id]" class="qty" type="number" min="0" step="1">
          </div>
          <EButton variant="primary" size="small" @click="saveJs">{{ t('grossanlass.chain.guestJsSave') }}</EButton>
        </section>

        <section class="card">
          <h3>{{ t('grossanlass.chain.guestLoanTitle') }}</h3>
          <p class="hint">{{ t('grossanlass.chain.guestLoanHint') }}</p>
          <ETextField v-model="loanName" :label="t('grossanlass.chain.guestLoanName')" hide-details />
          <ETextField v-model="loanQty" type="number" :label="t('grossanlass.chain.guestLoanQty')" hide-details />
          <EButton variant="primary" size="small" :disabled="!loanName.trim()" @click="offerLoan">
            {{ t('grossanlass.chain.guestLoanOffer') }}
          </EButton>
        </section>
      </template>
    </template>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import { createGuestJsArticles } from '@/views/grossanlass/grossanlassGaestePreviewData'
import {
  acceptGuestInvite,
  addGuestOfferedLoan,
  guestJsQty,
  isGrossanlassPublished,
  listChainParticipants,
  rejectGuestInvite,
  setGuestJsQty,
} from '@/views/grossanlass/grossanlassChainPreviewStore'

const { t } = useI18n()
const toast = useToast()
const published = computed(() => isGrossanlassPublished())
const deptId = ref('wt')
const loanName = ref('')
const loanQty = ref<string | number>(4)
const jsDraft = reactive<Record<string, number>>({})

function tr(key: string, values?: Record<string, string | number>) {
  return values ? String(t(key, values)) : String(t(key))
}

const jsArticles = computed(() => createGuestJsArticles(tr))
const deptItems = computed(() =>
  listChainParticipants().map((row) => ({ title: t(row.nameKey), value: row.id })),
)
const participant = computed(() => listChainParticipants().find((row) => row.id === deptId.value) ?? null)

function syncJsDraft() {
  const id = deptId.value
  for (const article of jsArticles.value) {
    const line = article.lines.find((item) => item.departmentId === id)
    jsDraft[article.id] = guestJsQty(article.id, id, line?.qty ?? 0)
  }
}

watch([deptId, jsArticles], syncJsDraft, { immediate: true })

function accept() {
  acceptGuestInvite(deptId.value)
  toast.success(t('grossanlass.chain.acceptedToast'))
}

function reject() {
  rejectGuestInvite(deptId.value)
  toast.success(t('grossanlass.chain.rejectedToast'))
}

function saveJs() {
  for (const article of jsArticles.value) {
    setGuestJsQty(article.id, deptId.value, Number(jsDraft[article.id] || 0))
  }
  toast.success(t('grossanlass.chain.guestJsSaved'))
}

function offerLoan() {
  addGuestOfferedLoan({
    departmentId: deptId.value,
    name: loanName.value.trim(),
    qty: Number(loanQty.value) || 1,
    family: 'material',
  })
  loanName.value = ''
  toast.success(t('grossanlass.chain.guestLoanOffered'))
}
</script>

<style scoped>
.hint { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; }
.dept-select { max-width: 320px; margin-bottom: 14px; }
.card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 14px;
  display: grid;
  gap: 10px;
}
.card h3 { margin: 0; font-size: 0.95rem; }
.row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.actions { display: flex; gap: 8px; }
.js-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.qty {
  width: 88px;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}
.chip {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: #e2e8f0;
}
.chip.accepted { background: #dcfce7; color: #166534; }
.chip.pending { background: #ffedd5; color: #c2410c; }
.chip.rejected { background: #fee2e2; color: #b91c1c; }
</style>
