<script setup lang="ts">
import { computed, inject, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackCrateShellCheckLineActions from '@/components/activities/PackCrateShellCheckLineActions.vue'
import PackShellInlineLooseIssueRow from '@/components/activities/PackShellInlineLooseIssueRow.vue'
import { shellForwardExpectedQty, shellForwardLineKey } from '@/components/activities/packCrateForwardCheck'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import { EButton } from '@/components/form/base'
import type { ActivityPackItem } from '@/api/activityPackItems'

defineOptions({ name: 'PackCrateShellInlinePanel' })

export interface PackCrateShellPeekLine {
  id: string
  materialName: string
  quantity: number
  /** material_item.id für Kistencheck / Lager / Meldungen */
  materialItemId?: string | null
  /** Seriennummer / Label der erwarteten Charge (Sichtprüfung) */
  serialHint?: string | null
  /** Nach Kistencheck: Status der Zeile (ok, loss, extra, …) */
  checkStatus?: string | null
  /** Soll zum Zeitpunkt des Checks */
  sollQty?: number | null
  /** Gezählt in der Kiste (vor Nachlegen) */
  countedQty?: number | null
  /** Aus Lager in die Kiste nachgelegt */
  replenishQty?: number | null
}

export interface PackCrateShellPeekSection {
  subsectionKey: string
  title: string
  lines: PackCrateShellPeekLine[]
}

const props = withDefaults(
  defineProps<{
    sections: PackCrateShellPeekSection[]
    emptyHint: string
    /** In eingebetteter Kiste: Inhalt sofort sichtbar */
    defaultExpanded?: boolean
    /** Zusatz und Fix als eigene Zeilen (nicht ein gemeinsames «Kisteninhalt»-Panel) */
    separateSectionRows?: boolean
    /** Hinweis nach Kistencheck (Nachlegen, Umschalten, …) */
    realityBanner?: string | null
    showTemplateToggle?: boolean
    useRealityView?: boolean
    /** Phys.-Kombi: Inhaltcheck mit − / Zähler / + / ✓ */
    checkPackItem?: ActivityPackItem | null
    /** Gepackt → Am Event: lose Ausgabe pro Zeile (ohne Packkiste) */
    looseIssueContainerId?: string | null
    looseIssueCrateLabel?: string | null
    stageRightLabel?: string
  }>(),
  {
    defaultExpanded: false,
    separateSectionRows: false,
    realityBanner: null,
    showTemplateToggle: false,
    useRealityView: true,
    checkPackItem: null,
    looseIssueContainerId: null,
    looseIssueCrateLabel: null,
    stageRightLabel: '',
  },
)

const emit = defineEmits<{
  'toggle-reality-view': []
  'repeat-check': []
}>()

const { t } = useI18n()

const ctx = inject(PACK_WAREHOUSE_ISSUE_INJECT_KEY, null) as Record<string, unknown> | null

const interactiveShellCheck = computed(() => {
  const pi = props.checkPackItem
  if (!pi || !ctx) return false
  const fn = ctx.shellCheckPendingForPackItem as ((p: ActivityPackItem) => boolean) | undefined
  return fn ? fn(pi) : false
})

const useLooseIssueRows = computed(
  () =>
    Boolean((props.looseIssueContainerId ?? '').trim()) &&
    !interactiveShellCheck.value,
)

function lineCheckKey(subsectionKey: string, lineId: string): string {
  return shellForwardLineKey(subsectionKey, lineId)
}

function reviewForLine(subsectionKey: string, line: PackCrateShellPeekLine) {
  const pi = props.checkPackItem
  const fn = ctx?.shellCheckReviewForLine as
    | ((packItemId: string, key: string, expectedQty: number) => unknown)
    | undefined
  const key = lineCheckKey(subsectionKey, line.id)
  const expected = shellForwardExpectedQty(subsectionKey === 'extra', line.quantity)
  if (!pi || !fn) return { countedQty: expected, status: null }
  return fn(pi.id, key, expected) as import('@/components/activities/packCrateForwardCheck').ShellForwardLineReview
}

function historyReplenishForLine(subsectionKey: string, lineId: string): boolean {
  const pi = props.checkPackItem
  const fn = ctx?.shellCheckHistoryReplenishForKey as
    | ((packItemId: string, key: string) => boolean)
    | undefined
  if (!pi || !fn) return false
  return fn(pi.id, lineCheckKey(subsectionKey, lineId))
}

function onCountedChange(subsectionKey: string, line: PackCrateShellPeekLine, raw: number) {
  const pi = props.checkPackItem
  const fn = ctx?.shellCheckPatchLine as
    | ((
        packItemId: string,
        key: string,
        expectedQty: number,
        isExtra: boolean,
        patch: { countedQty: number },
      ) => void)
    | undefined
  if (!pi || !fn) return
  fn(
    pi.id,
    lineCheckKey(subsectionKey, line.id),
    shellForwardExpectedQty(subsectionKey === 'extra', line.quantity),
    subsectionKey === 'extra',
    { countedQty: raw },
  )
}

function onLineOk(subsectionKey: string, line: PackCrateShellPeekLine) {
  const pi = props.checkPackItem
  const fn = ctx?.shellCheckSetLineOk as
    | ((packItemId: string, key: string, expectedQty: number, isExtra: boolean) => void)
    | undefined
  if (!pi || !fn) return
  fn(
    pi.id,
    lineCheckKey(subsectionKey, line.id),
    shellForwardExpectedQty(subsectionKey === 'extra', line.quantity),
    subsectionKey === 'extra',
  )
}

/** Pro Unterabschnitt (extra / fixed / all): true = aufgeklappt */
const expandedByKey = reactive<Record<string, boolean>>({})

function isOpen(key: string): boolean {
  if (props.defaultExpanded) return true
  return expandedByKey[key] === true
}

function toggleKey(key: string) {
  expandedByKey[key] = !isOpen(key)
}

/** Wie Behälterzeilen «Am Event»: Fix im Behälter zuerst, dann Zusatz, dann übrige (z. B. «Inhalt») */
const displaySections = computed(() => {
  const rank = (k: string) => (k === 'fixed' ? 0 : k === 'extra' ? 1 : 2)
  return [...props.sections].sort((a, b) => rank(a.subsectionKey) - rank(b.subsectionKey))
})

/** Mehrere Unterabschnitte: ein Panel — ausser bei separateSectionRows (Phys.-Kombi-Kiste) */
const useUnifiedPeekPanel = computed(
  () => !props.separateSectionRows && props.sections.length > 1,
)
const unifiedPeekOpen = ref(props.defaultExpanded)

function rowAriaLabel(sec: PackCrateShellPeekSection): string {
  return `${sec.title} — ${t('activities.packList.cratePeekRowToggleAria')}`
}

function unifiedPeekAriaLabel(): string {
  return `${t('activities.packList.cratePeekCombinedToggle')} — ${t('activities.packList.cratePeekRowToggleAria')}`
}

function statusBadgeKey(line: PackCrateShellPeekLine): string | null {
  const st = (line.checkStatus ?? '').trim()
  if (!st || st === 'ok') return null
  return st
}

const totalLines = computed(() =>
  props.sections.reduce((n, sec) => n + sec.lines.length, 0),
)

const flatLines = computed(() => props.sections.flatMap((sec) => sec.lines))

/** Phys.-Kombi: Fix zu, Zusatz offen — wie im Kisten-Picker */
const subsectionCollapsed = reactive<Record<string, boolean>>({})

function subCollapseKey(subsectionKey: string): string {
  const pi = props.checkPackItem?.id ?? 'peek'
  return `${pi}:${subsectionKey}`
}

function defaultSubCollapsed(subsectionKey: string): boolean {
  if (!props.defaultExpanded) return true
  if (totalLines.value <= 1) return false
  if (subsectionKey === 'fixed') return true
  if (subsectionKey === 'extra') return false
  if (subsectionKey === 'all') {
    const hasFixExtra = props.sections.some(
      (s) => s.subsectionKey === 'fixed' || s.subsectionKey === 'extra',
    )
    return hasFixExtra
  }
  return true
}

function isSubCollapsed(subsectionKey: string): boolean {
  const k = subCollapseKey(subsectionKey)
  return k in subsectionCollapsed ? subsectionCollapsed[k] : defaultSubCollapsed(subsectionKey)
}

function toggleSub(subsectionKey: string) {
  subsectionCollapsed[subCollapseKey(subsectionKey)] = !isSubCollapsed(subsectionKey)
}

function applySubsectionDefaults() {
  for (const sec of props.sections) {
    subsectionCollapsed[subCollapseKey(sec.subsectionKey)] = defaultSubCollapsed(sec.subsectionKey)
  }
}

watch(
  () => props.defaultExpanded,
  (exp) => {
    if (exp) applySubsectionDefaults()
  },
)

watch(
  () => props.sections,
  () => {
    if (props.defaultExpanded) applySubsectionDefaults()
  },
)
</script>

<template>
  <div
    class="pack-combo-crate-inline"
    :class="{ 'pack-combo-crate-inline--container-style': separateSectionRows }"
  >
    <div v-if="realityBanner" class="pack-crate-reality-banner" role="status">
      <p class="pack-crate-reality-banner__text">{{ realityBanner }}</p>
      <EButton
        v-if="showTemplateToggle"
        variant="secondary"
        size="x-small"
        class="pack-crate-reality-banner__toggle"
        @click="emit('toggle-reality-view')"
      >
        {{
          useRealityView
            ? t('activities.packList.crateCheckShowTemplate')
            : t('activities.packList.crateCheckShowReality')
        }}
      </EButton>
    </div>
    <template v-if="sections.length === 0">
      <p class="pack-combo-crate-inline__empty text-muted">{{ emptyHint }}</p>
    </template>
    <template v-else-if="useUnifiedPeekPanel">
      <div class="pack-combo-crate-inline__unified">
        <button
          type="button"
          class="pack-combo-crate-inline__row-toggle"
          :aria-expanded="unifiedPeekOpen"
          :aria-label="unifiedPeekAriaLabel()"
          @click.stop="unifiedPeekOpen = !unifiedPeekOpen"
        >
          <span class="pack-combo-crate-inline__chev" aria-hidden="true">{{
            unifiedPeekOpen ? '▼' : '▶'
          }}</span>
          <span class="pack-combo-crate-inline__row-label">{{ t('activities.packList.cratePeekCombinedToggle') }}</span>
        </button>
        <div v-show="unifiedPeekOpen" class="pack-combo-crate-inline__body pack-combo-crate-inline__body--unified">
          <template v-for="sec in displaySections" :key="'u-' + sec.subsectionKey">
            <div class="pack-combo-crate-inline__subheading">{{ sec.title }}</div>
            <ul v-if="sec.lines.length > 0" class="pack-combo-crate-inline__list">
              <li
                v-for="line in sec.lines"
                :key="sec.subsectionKey + '-' + line.id"
                class="pack-combo-crate-inline__line"
              >
                <span class="pack-combo-crate-inline__name">{{ line.materialName }}</span>
                <span
                  v-if="line.serialHint"
                  class="pack-combo-crate-inline__serial text-muted"
                  :title="t('activities.packList.shellForwardSerialCheckTitle')"
                >
                  {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
                </span>
                <span v-if="sec.subsectionKey === 'extra'" class="pack-combo-crate-inline__qty text-muted">
                  {{ t('activities.packList.shellForwardExtraCountOnly') }}
                </span>
                <span v-else class="pack-combo-crate-inline__qty text-muted">
                  {{ line.quantity }}×
                  <span
                    v-if="line.sollQty != null && line.sollQty !== line.quantity"
                    class="pack-combo-crate-inline__soll-hint"
                  >
                    {{ t('activities.packList.crateCheckWasSoll', { n: line.sollQty }) }}
                  </span>
                  <span
                    v-if="(line.replenishQty ?? 0) > 0"
                    class="pack-combo-crate-inline__check-badge pack-combo-crate-inline__check-badge--replenish"
                  >
                    {{
                      t('activities.packList.crateCheckReplenishedBadge', {
                        n: line.replenishQty,
                      })
                    }}
                  </span>
                  <span v-if="statusBadgeKey(line)" class="pack-combo-crate-inline__check-badge">
                    {{ t(`activities.packList.crateCheckStatus_${statusBadgeKey(line)}`) }}
                  </span>
                </span>
              </li>
            </ul>
          </template>
        </div>
      </div>
    </template>
    <template v-else-if="separateSectionRows">
      <template v-if="totalLines <= 1">
        <div v-if="flatLines.length > 0" class="pack-combo-crate-inline__flat-lines">
          <template v-for="sec in displaySections" :key="'flat-one-' + sec.subsectionKey">
            <template v-for="line in sec.lines" :key="sec.subsectionKey + '-' + line.id">
              <PackCrateShellCheckLineActions
                v-if="interactiveShellCheck"
                :material-name="line.materialName"
                :expected-qty="sec.subsectionKey === 'extra' ? 0 : line.quantity"
                :serial-hint="line.serialHint"
                :review="reviewForLine(sec.subsectionKey, line)"
                :is-extra="sec.subsectionKey === 'extra'"
                :minus-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :plus-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :input-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :check-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                @update:counted-qty="onCountedChange(sec.subsectionKey, line, $event)"
                @ok="onLineOk(sec.subsectionKey, line)"
              />
              <PackShellInlineLooseIssueRow
                v-else-if="useLooseIssueRows"
                :container-id="looseIssueContainerId!"
                :line="line"
                :crate-label="looseIssueCrateLabel ?? ''"
                :stage-right-label="stageRightLabel"
              />
              <div v-else class="pack-container-line">
                <div class="pack-container-line-main">
                  <span class="pack-container-line-name">{{ line.materialName }}</span>
                  <span v-if="line.serialHint" class="pack-combo-crate-inline__serial text-muted">
                    {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
                  </span>
                  <span class="pack-container-line-qty text-muted">{{
                    t('activities.common.piecesShort', { count: line.quantity })
                  }}</span>
                </div>
              </div>
            </template>
          </template>
        </div>
      </template>
      <template v-else>
        <div
          v-for="sec in displaySections"
          :key="'flat-' + sec.subsectionKey"
          class="pack-combo-crate-inline__subsection-block"
        >
          <button
            type="button"
            class="pack-container-subsection-toggle"
            :aria-expanded="!isSubCollapsed(sec.subsectionKey)"
            :aria-label="`${sec.title} — ${t('activities.packList.cratePeekRowToggleAria')}`"
            @click.stop="toggleSub(sec.subsectionKey)"
          >
            <span class="pack-container-chevron pack-container-chevron--subsection" aria-hidden="true">
              <svg
                v-if="isSubCollapsed(sec.subsectionKey)"
                class="pack-container-subsection-chevron-svg"
                width="12"
                height="12"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.3"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="9 18 15 12 9 6" />
              </svg>
              <svg
                v-else
                class="pack-container-subsection-chevron-svg"
                width="12"
                height="12"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.3"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </span>
            <span class="pack-container-subsection-toggle-label">{{ sec.title }}</span>
            <span
              v-if="sec.lines.length > 0"
              class="pack-container-chip pack-container-chip--subsection text-muted"
              >{{ t('activities.common.itemsUnit', { count: sec.lines.length }) }}</span
            >
          </button>
          <ul
            v-show="!isSubCollapsed(sec.subsectionKey)"
            v-if="sec.lines.length > 0"
            class="pack-combo-crate-inline__list pack-combo-crate-inline__list--nested"
          >
            <li
              v-for="line in sec.lines"
              :key="sec.subsectionKey + '-' + line.id"
              :class="
                interactiveShellCheck || useLooseIssueRows
                  ? 'pack-combo-crate-inline__check-li'
                  : 'pack-container-line'
              "
            >
              <PackCrateShellCheckLineActions
                v-if="interactiveShellCheck"
                :material-name="line.materialName"
                :expected-qty="sec.subsectionKey === 'extra' ? 0 : line.quantity"
                :serial-hint="line.serialHint"
                :review="reviewForLine(sec.subsectionKey, line)"
                :is-extra="sec.subsectionKey === 'extra'"
                :minus-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :plus-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :input-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                :check-disabled="historyReplenishForLine(sec.subsectionKey, line.id)"
                @update:counted-qty="onCountedChange(sec.subsectionKey, line, $event)"
                @ok="onLineOk(sec.subsectionKey, line)"
              />
              <PackShellInlineLooseIssueRow
                v-else-if="useLooseIssueRows"
                :container-id="looseIssueContainerId!"
                :line="line"
                :crate-label="looseIssueCrateLabel ?? ''"
                :stage-right-label="stageRightLabel"
              />
              <div v-else class="pack-container-line-main">
                <span class="pack-container-line-name">{{ line.materialName }}</span>
                <span
                  v-if="line.serialHint"
                  class="pack-combo-crate-inline__serial text-muted"
                  :title="t('activities.packList.shellForwardSerialCheckTitle')"
                >
                  {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
                </span>
                <span class="pack-container-line-qty text-muted">
                  {{ t('activities.common.piecesShort', { count: line.quantity }) }}
                  <span
                    v-if="line.sollQty != null && line.sollQty !== line.quantity"
                    class="pack-combo-crate-inline__soll-hint"
                  >
                    {{ t('activities.packList.crateCheckWasSoll', { n: line.sollQty }) }}
                  </span>
                  <span
                    v-if="(line.replenishQty ?? 0) > 0"
                    class="pack-combo-crate-inline__check-badge pack-combo-crate-inline__check-badge--replenish"
                  >
                    {{
                      t('activities.packList.crateCheckReplenishedBadge', {
                        n: line.replenishQty,
                      })
                    }}
                  </span>
                  <span v-if="statusBadgeKey(line)" class="pack-combo-crate-inline__check-badge">
                    {{ t(`activities.packList.crateCheckStatus_${statusBadgeKey(line)}`) }}
                  </span>
                </span>
              </div>
            </li>
          </ul>
        </div>
      </template>
    </template>
    <template v-else>
      <div
        v-for="sec in displaySections"
        :key="sec.subsectionKey"
        class="pack-combo-crate-inline__block"
      >
        <button
          type="button"
          class="pack-combo-crate-inline__row-toggle"
          :aria-expanded="isOpen(sec.subsectionKey)"
          :aria-label="rowAriaLabel(sec)"
          @click.stop="toggleKey(sec.subsectionKey)"
        >
          <span class="pack-combo-crate-inline__chev" aria-hidden="true">{{
            isOpen(sec.subsectionKey) ? '▼' : '▶'
          }}</span>
          <span class="pack-combo-crate-inline__row-label">{{ sec.title }}</span>
        </button>
        <div v-show="isOpen(sec.subsectionKey)" class="pack-combo-crate-inline__body">
          <ul v-if="sec.lines.length > 0" class="pack-combo-crate-inline__list">
            <li
              v-for="line in sec.lines"
              :key="sec.subsectionKey + '-' + line.id"
              class="pack-combo-crate-inline__line"
            >
              <span class="pack-combo-crate-inline__name">{{ line.materialName }}</span>
              <span
                v-if="line.serialHint"
                class="pack-combo-crate-inline__serial text-muted"
                :title="t('activities.packList.shellForwardSerialCheckTitle')"
              >
                {{ t('activities.packList.shellForwardSerialSn', { serial: line.serialHint }) }}
              </span>
              <span class="pack-combo-crate-inline__qty text-muted">
                {{ line.quantity }}×
                <span
                  v-if="line.sollQty != null && line.sollQty !== line.quantity"
                  class="pack-combo-crate-inline__soll-hint"
                >
                  {{ t('activities.packList.crateCheckWasSoll', { n: line.sollQty }) }}
                </span>
                <span
                  v-if="(line.replenishQty ?? 0) > 0"
                  class="pack-combo-crate-inline__check-badge pack-combo-crate-inline__check-badge--replenish"
                >
                  {{
                    t('activities.packList.crateCheckReplenishedBadge', {
                      n: line.replenishQty,
                    })
                  }}
                </span>
                <span v-if="statusBadgeKey(line)" class="pack-combo-crate-inline__check-badge">
                  {{ t(`activities.packList.crateCheckStatus_${statusBadgeKey(line)}`) }}
                </span>
              </span>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>

<style src="@/styles/views/activities/pack-container-card.css"></style>
<style src="@/styles/views/activities/pack-shell-combo.css"></style>
<style scoped>
.pack-combo-crate-inline {
  margin-top: 8px;
  width: 100%;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pack-combo-crate-inline__block {
  width: 100%;
}

.pack-combo-crate-inline__row-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin: 0;
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  text-align: left;
  cursor: pointer;
}

.pack-combo-crate-inline__row-toggle:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.pack-combo-crate-inline__chev {
  flex-shrink: 0;
  font-size: 11px;
  width: 1.1em;
  text-align: center;
  color: #64748b;
}

.pack-combo-crate-inline__row-label {
  flex: 1;
  min-width: 0;
}

.pack-combo-crate-inline__body {
  margin-top: 6px;
  padding: 8px 10px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 0 0 8px 8px;
  border-top: none;
  background: #fafafa;
}

.pack-combo-crate-inline__body--unified {
  padding-top: 10px;
}

.pack-combo-crate-inline__subheading {
  margin: 12px 0 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.pack-combo-crate-inline__subheading:first-child {
  margin-top: 0;
}

.pack-combo-crate-inline__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.pack-combo-crate-inline__line {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 4px 0;
  font-size: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.pack-combo-crate-inline__line:last-child {
  border-bottom: none;
}

.pack-combo-crate-inline__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pack-combo-crate-inline__qty {
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}

.pack-combo-crate-inline__soll-hint {
  margin-left: 4px;
  font-size: 11px;
  opacity: 0.85;
}

.pack-crate-reality-banner {
  margin-bottom: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  font-size: 12px;
  line-height: 1.4;
}

.pack-crate-reality-banner__text {
  margin: 0 0 6px;
}

.pack-crate-reality-banner__toggle {
  margin: 0;
}

.pack-combo-crate-inline__serial {
  display: block;
  margin-top: 1px;
  font-size: 10.5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  letter-spacing: -0.02em;
  line-height: 1.3;
}

.pack-combo-crate-inline__check-badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  background: #fef3c7;
  color: #92400e;
}

.pack-combo-crate-inline__check-badge--replenish {
  background: #dbeafe;
  color: #1d4ed8;
}

.pack-combo-crate-inline__empty {
  margin: 4px 0 0;
  font-size: 12px;
}

.pack-combo-crate-inline__subsection--flat:first-child .pack-container-subsection-title {
  margin-top: 4px;
}

.pack-combo-crate-inline__list--flat {
  margin: 0;
  padding: 0;
  list-style: none;
}

.pack-combo-crate-inline__list--check {
  padding: 4px 8px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fafafa;
}

.pack-combo-crate-inline__check-li {
  list-style: none;
}
</style>
