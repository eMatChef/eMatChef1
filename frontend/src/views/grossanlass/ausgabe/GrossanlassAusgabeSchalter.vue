<template>
  <div class="schalter">
    <p v-if="last" class="last" role="status">
      <v-icon icon="mdi-check-circle" color="success" size="20" />
      {{ t('grossanlass.ausgabe.last', { person: last.person, n: last.lines }) }}<template v-if="last.context"> · {{ last.context }}</template>
    </p>

    <!-- 1. Empfänger -->
    <section class="step" :class="{ 'step--done': !!session.personId }">
      <header class="step__head"><span class="num">1</span><h3>{{ t('grossanlass.ausgabe.step.recipient') }}</h3></header>
      <div v-if="!session.personId" class="scan">
        <ESearchField
          ref="personField"
          v-model="personQuery"
          class="scan__field"
          :label="t('grossanlass.ausgabe.scanPerson')"
          autofocus
          @keydown.enter="onPersonEnter"
        />
        <div class="quick">
          <button v-for="p in quickPeople" :key="p.id" type="button" class="quick__btn" @click="pickPerson(p)">
            <strong>{{ p.name }}</strong><small>{{ p.ressort }}</small>
          </button>
        </div>
        <EButton variant="secondary" size="small" @click="goCards">{{ t('grossanlass.chain.openUserCards') }}</EButton>
      </div>
      <div v-else class="chosen">
        <v-icon icon="mdi-account-circle-outline" size="30" />
        <div>
          <strong>{{ session.personName }}</strong>
          <small>{{ personInfo }}</small>
        </div>
        <EButton variant="text" size="small" @click="clearPerson">{{ t('grossanlass.ausgabe.change') }}</EButton>
      </div>
      <v-alert v-for="w in recipientWarnings" :key="w.kind" type="warning" variant="tonal" density="compact">
        {{ t(`grossanlass.ausgabe.warn.${w.kind}`, w.params ?? {}) }}
      </v-alert>
    </section>

    <!-- 2. Kontext -->
    <section class="step" :class="{ 'step--done': !!session.contextId }">
      <header class="step__head"><span class="num">2</span><h3>{{ t('grossanlass.ausgabe.step.context') }} <small>{{ t('grossanlass.ausgabe.optional') }}</small></h3></header>
      <div class="ctx">
        <button type="button" class="chip" :class="{ 'chip--on': session.contextId === '' }" @click="setContext('')">{{ t('grossanlass.ausgabe.noContext') }}</button>
        <button v-for="c in contexts" :key="c.id" type="button" class="chip" :class="{ 'chip--on': session.contextId === c.id }" @click="setContext(c.id)">
          <v-icon icon="mdi-hammer-wrench" size="16" /> {{ c.label }}
        </button>
      </div>
      <p v-if="activeContext" class="ctx__ressort">{{ activeContext.ressort }}</p>
    </section>

    <!-- Schnellausgabe -->
    <section v-if="activeContext" class="plan">
      <header class="plan__head">
        <h3>{{ t('grossanlass.ausgabe.plan.title', { name: activeContext.label }) }}</h3>
        <EButton size="small" variant="secondary" @click="takeNeeded">
          <v-icon icon="mdi-lightning-bolt-outline" start size="16" /> {{ t('grossanlass.ausgabe.plan.takeNeeded') }}
        </EButton>
      </header>
      <div class="plan__table" role="table">
        <div class="plan__row plan__row--head" role="row">
          <span>{{ t('grossanlass.ausgabe.plan.article') }}</span><span>{{ t('grossanlass.ausgabe.plan.planned') }}</span>
          <span>{{ t('grossanlass.ausgabe.plan.issued') }}</span><span>{{ t('grossanlass.ausgabe.plan.needed') }}</span><span />
        </div>
        <div v-for="row in plan" :key="row.article.id" class="plan__row" role="row">
          <span class="plan__name">
            {{ row.article.name }}
            <v-chip size="x-small" variant="tonal" :color="row.article.kind === 'returnable' ? 'warning' : 'info'">{{ t(`grossanlass.ausgabe.kind.${row.article.kind}`) }}</v-chip>
          </span>
          <span :data-label="t('grossanlass.ausgabe.plan.planned')">{{ row.planned }}</span>
          <span :data-label="t('grossanlass.ausgabe.plan.issued')">{{ row.issued }}</span>
          <span :data-label="t('grossanlass.ausgabe.plan.needed')" :class="{ 'plan__needed': row.needed > 0, 'plan__ok': row.needed === 0 }">{{ row.needed }}</span>
          <EButton size="small" variant="secondary" :disabled="row.needed === 0 || row.inSession > 0" @click="addArticle(row.article.id, row.needed)">
            {{ row.inSession > 0 ? t('grossanlass.ausgabe.plan.inSession') : t('grossanlass.ausgabe.plan.add') }}
          </EButton>
        </div>
      </div>
    </section>

    <!-- 3. Material -->
    <section class="step">
      <header class="step__head"><span class="num">3</span><h3>{{ t('grossanlass.ausgabe.step.material') }}</h3></header>
      <ESearchField
        ref="articleField"
        v-model="articleQuery"
        class="scan__field"
        :label="t('grossanlass.ausgabe.scanArticle')"
        @keydown.enter="onArticleEnter"
      />
      <ul v-if="suggestions.length" class="suggest">
        <li v-for="a in suggestions" :key="a.id">
          <button type="button" @click="pickArticle(a.id)">
            <strong>{{ a.name }}</strong><small>{{ a.code }} · {{ t(`grossanlass.ausgabe.kind.${a.kind}`) }} · {{ t('grossanlass.ausgabe.stockLine', { n: a.stock, unit: a.unit }) }}</small>
          </button>
        </li>
      </ul>
      <v-alert v-if="unknown" type="error" variant="tonal" density="compact">{{ t('grossanlass.ausgabe.warn.unknown') }}</v-alert>

      <ul v-if="session.lines.length" class="lines">
        <li v-for="line in lines" :key="line.articleId" class="line" :class="{ 'line--blocked': line.blocking }">
          <div class="line__main">
            <strong>{{ line.article.name }}</strong>
            <span class="line__chips">
              <v-chip size="x-small" variant="flat" :color="line.article.kind === 'returnable' ? 'warning' : 'info'">{{ t(`grossanlass.ausgabe.kind.${line.article.kind}`) }}</v-chip>
              <small>{{ line.article.code }} · {{ line.article.place }}</small>
            </span>
            <small v-if="line.article.kind === 'returnable'" class="line__note">{{ t('grossanlass.ausgabe.returnNote') }}</small>
            <small v-else class="line__note">{{ t('grossanlass.ausgabe.consumableNote') }}</small>
            <v-alert v-for="w in line.warnings" :key="w.kind" :type="w.blocking ? 'error' : 'warning'" variant="tonal" density="compact">
              {{ t(`grossanlass.ausgabe.warn.${w.kind}`, w.params ?? {}) }}
            </v-alert>
          </div>
          <div class="line__qty">
            <button type="button" class="qbtn" :aria-label="t('grossanlass.ausgabe.less')" @click="changeQty(line.articleId, -1)">−</button>
            <input class="qinput" type="number" min="1" :value="line.qty" :aria-label="t('grossanlass.rueckbau.decide.qty')" @change="setLineQty(line.articleId, Number(($event.target as HTMLInputElement).value))">
            <button type="button" class="qbtn" :aria-label="t('grossanlass.ausgabe.more')" @click="changeQty(line.articleId, 1)">+</button>
          </div>
          <button type="button" class="line__remove" :aria-label="t('common.delete')" @click="removeLine(line.articleId)"><v-icon icon="mdi-close" size="20" /></button>
        </li>
      </ul>
      <EButton v-if="activeContext && session.lines.length" variant="secondary" @click="focusArticle">
        <v-icon icon="mdi-plus" start size="18" /> {{ t('grossanlass.ausgabe.addMore') }}
      </EButton>
    </section>

    <!-- Bestätigen -->
    <footer class="confirm">
      <div class="confirm__sum">
        <strong v-if="session.personName">{{ session.personName }}</strong>
        <span v-if="activeContext"> · {{ t('grossanlass.ausgabe.order') }}: {{ activeContext.label }}</span>
        <small v-if="session.lines.length"> · {{ t('grossanlass.ausgabe.positions', { n: session.lines.length }) }}</small>
      </div>
      <EButton variant="primary" size="x-large" :disabled="!ready" @click="confirm">
        <v-icon icon="mdi-check-bold" start size="22" /> {{ t('grossanlass.ausgabe.confirm') }}
      </EButton>
    </footer>

    <details class="legacy">
      <summary>{{ t('grossanlass.ausgabe.legacy') }}</summary>
      <GrossanlassAusgabeGeplant />
    </details>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ESearchField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import GrossanlassAusgabeGeplant from './GrossanlassAusgabeGeplant.vue'
import {
  addArticle,
  addNeededFromPlan,
  articleById,
  canConfirm,
  changeQty,
  confirmIssue,
  contextById,
  contextPlan,
  findPerson,
  lineWarnings,
  mergePeople,
  personWarnings,
  removeLine,
  scanArticle,
  searchArticles,
  setContext,
  setLineQty,
  setRecipient,
  useGaAusgabeMock,
  type GaAusgabePerson,
} from './gaAusgabeMock'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const uebersicht = useGaUebersicht()
const { people, contexts, session, last, loans } = useGaAusgabeMock()

const personQuery = ref('')
const articleQuery = ref('')
const unknown = ref(false)
const personField = ref<{ $el?: HTMLElement } | null>(null)
const articleField = ref<{ $el?: HTMLElement } | null>(null)

// Echte Benutzerkarten als Empfänger übernehmen (Demo-Personen bleiben als Ergänzung)
onMounted(() => {
  const cards = uebersicht.data.value?.cards ?? []
  mergePeople(cards.map((card) => ({ id: card.user_id, name: card.name, code: card.code, ressort: card.ressort })))
})

const quickPeople = computed(() => {
  const q = personQuery.value.trim().toLowerCase()
  const list = q ? people.value.filter((p) => `${p.name} ${p.code}`.toLowerCase().includes(q)) : people.value
  return list.slice(0, 8)
})
const activeContext = computed(() => contextById(session.value.contextId))
const plan = computed(() => {
  void session.value.lines
  return contextPlan(session.value.contextId)
})
const lines = computed(() =>
  session.value.lines.map((line) => {
    const warnings = lineWarnings(line)
    return { ...line, article: articleById(line.articleId)!, warnings, blocking: warnings.some((w) => w.blocking) }
  }).filter((line) => !!line.article),
)
const recipientWarnings = computed(() => (session.value.personName ? personWarnings(session.value.personName) : []))
const personInfo = computed(() => {
  const open = loans.value.filter((row) => row.personName === session.value.personName).length
  const p = people.value.find((row) => row.id === session.value.personId)
  return `${p?.ressort ?? ''} · ${p?.code ?? ''}${open ? ` · ${t('grossanlass.ausgabe.openLoans', { n: open })}` : ''}`
})
const suggestions = computed(() => (articleQuery.value.trim().length >= 2 ? searchArticles(articleQuery.value) : []))
const ready = computed(() => canConfirm())

function focus(field: { $el?: HTMLElement } | null) {
  void nextTick(() => (field?.$el?.querySelector('input') as HTMLInputElement | null)?.focus())
}
function focusArticle() {
  focus(articleField.value)
}
function pickPerson(p: GaAusgabePerson) {
  setRecipient(p)
  personQuery.value = ''
  focus(articleField.value)
}
function onPersonEnter() {
  const hit = findPerson(personQuery.value)
  if (hit) pickPerson(hit)
  else toast.error(t('grossanlass.ausgabe.personNotFound'))
}
function clearPerson() {
  setRecipient(null)
  focus(personField.value)
}
function pickArticle(id: string) {
  addArticle(id, 1)
  unknown.value = false
  articleQuery.value = ''
  focus(articleField.value)
}
function onArticleEnter() {
  if (!articleQuery.value.trim()) return
  const result = scanArticle(articleQuery.value)
  unknown.value = !result.ok
  articleQuery.value = ''
  focus(articleField.value)
}
function takeNeeded() {
  const n = addNeededFromPlan(session.value.contextId)
  if (n) toast.success(t('grossanlass.ausgabe.plan.taken', { n }))
}
function confirm() {
  const result = confirmIssue()
  if (result) {
    toast.success(t('grossanlass.ausgabe.confirmed', { n: result.lines }))
    focus(personField.value)
  }
}
function goCards() {
  const id = String(route.params.departmentId || '')
  if (id) void router.push(`/${id}/settings/user-karten`)
}
</script>

<style scoped>
.schalter {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 96px;
}
.last {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 10px 14px;
  border-radius: 12px;
  background: #ecfdf5;
  color: #065f46;
  font-weight: 600;
}
.step {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}
.step--done {
  border-color: #86efac;
}
.step__head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.step__head h3 {
  margin: 0;
  font-size: 1rem;
}
.step__head small {
  color: #94a3b8;
  font-weight: 400;
}
.num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #111827;
  color: #fff;
  font-weight: 700;
  font-size: 0.85rem;
}
.scan {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.scan__field :deep(input) {
  min-height: 52px;
  font-size: 1.15rem;
}
.quick {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 8px;
}
.quick__btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-height: 56px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.quick__btn small,
.chosen small {
  color: #64748b;
}
.chosen {
  display: flex;
  align-items: center;
  gap: 12px;
}
.chosen > div {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
}
.ctx {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 6px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  font-weight: 600;
  cursor: pointer;
}
.chip--on {
  border-color: #059669;
  background: #ecfdf5;
  color: #065f46;
}
.ctx__ressort {
  margin: 0;
  color: #64748b;
  font-size: 0.84rem;
}
.plan {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid #bae6fd;
  border-radius: 14px;
  background: #f0f9ff;
}
.plan__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.plan__head h3 {
  margin: 0;
  font-size: 1rem;
}
.plan__row {
  display: grid;
  grid-template-columns: minmax(150px, 3fr) repeat(3, minmax(60px, 1fr)) auto;
  gap: 8px;
  align-items: center;
  padding: 8px 4px;
  border-bottom: 1px solid #e0f2fe;
}
.plan__row--head {
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 600;
}
.plan__name {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}
.plan__needed {
  color: #b45309;
  font-weight: 700;
}
.plan__ok {
  color: #15803d;
}
.suggest {
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.suggest button {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  min-height: 52px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.suggest small {
  color: #64748b;
}
.lines {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.line--blocked {
  border-color: #fca5a5;
  background: #fef2f2;
}
.line__main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.line__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.line__chips small,
.line__note {
  color: #64748b;
}
.line__qty {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.qbtn {
  width: 48px;
  height: 48px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #fff;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
}
.qinput {
  width: 72px;
  height: 48px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  text-align: center;
  font-size: 1.2rem;
  font-weight: 700;
}
.line__remove {
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: none;
  cursor: pointer;
}
.confirm {
  position: sticky;
  bottom: 0;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 -4px 14px rgba(15, 23, 42, 0.08);
}
.confirm__sum small {
  color: #64748b;
}
.legacy {
  margin-top: 12px;
  padding: 10px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.legacy summary {
  cursor: pointer;
  font-weight: 600;
  color: #475569;
}
@media (max-width: 720px) {
  .plan__row--head {
    display: none;
  }
  .plan__row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .plan__name {
    grid-column: 1 / -1;
  }
  .plan__row > span[data-label]::before {
    content: attr(data-label) ': ';
    color: #64748b;
    font-size: 0.72rem;
  }
  .line {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .line__qty {
    grid-column: 1 / -1;
  }
}
</style>
