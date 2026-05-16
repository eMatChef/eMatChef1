<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackCrateShellInlinePanel' })

export interface PackCrateShellPeekLine {
  id: string
  materialName: string
  quantity: number
  /** material_item.id für Kistencheck / Lager / Meldungen */
  materialItemId?: string | null
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
  }>(),
  { defaultExpanded: false, separateSectionRows: false, realityBanner: null, showTemplateToggle: false, useRealityView: true },
)

const emit = defineEmits<{
  'toggle-reality-view': []
}>()

const { t } = useI18n()

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
</script>

<template>
  <div class="pack-combo-crate-inline">
    <div v-if="realityBanner" class="pack-crate-reality-banner" role="status">
      <p class="pack-crate-reality-banner__text">{{ realityBanner }}</p>
      <button
        v-if="showTemplateToggle"
        type="button"
        class="btn-outline btn-xs pack-crate-reality-banner__toggle"
        @click="emit('toggle-reality-view')"
      >
        {{
          useRealityView
            ? t('activities.packList.crateCheckShowTemplate')
            : t('activities.packList.crateCheckShowReality')
        }}
      </button>
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
          </template>
        </div>
      </div>
    </template>
    <template v-else-if="separateSectionRows">
      <div
        v-for="sec in displaySections"
        :key="'flat-' + sec.subsectionKey"
        class="pack-combo-crate-inline__subsection pack-combo-crate-inline__subsection--flat"
      >
        <div class="pack-container-subsection-title">{{ sec.title }}</div>
        <ul v-if="sec.lines.length > 0" class="pack-combo-crate-inline__list pack-combo-crate-inline__list--flat">
          <li
            v-for="line in sec.lines"
            :key="sec.subsectionKey + '-' + line.id"
            class="pack-container-line"
          >
            <div class="pack-container-line-main">
              <span class="pack-container-line-name">{{ line.materialName }}</span>
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
</style>

<style src="@/styles/views/activities/pack-container-card.css"></style>
