<template>
  <div class="devices-scan-capture" :class="{ 'devices-scan-capture--desktop': isDesktop }">
    <label class="scan-label" :for="inputId">{{ t('devices.scan.label') }}</label>
    <input
      :id="inputId"
      ref="inputRef"
      v-model="buffer"
      type="text"
      class="scan-input"
      :class="{ 'scan-input--hidden': hideInput }"
      autocomplete="off"
      autocapitalize="off"
      spellcheck="false"
      :placeholder="t('devices.scan.placeholder')"
      @keydown.enter.prevent="onEnter"
    />
    <p v-if="showHint" class="scan-hint muted">{{ t('devices.scan.hint') }}</p>
    <ul v-if="log.length && showLog" class="scan-log">
      <li v-for="(entry, idx) in log" :key="idx" class="scan-log-item">
        <time class="scan-log-time">{{ entry.time }}</time>
        <span class="scan-log-summary">{{ entry.summary }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatScanParseResult, parseScanInput, type ScanParseResult } from '@/utils/scanParser'

const props = withDefaults(
  defineProps<{
    hideInput?: boolean
    showLog?: boolean
    showHint?: boolean
    isDesktop?: boolean
    autofocus?: boolean
  }>(),
  {
    hideInput: false,
    showLog: true,
    showHint: true,
    isDesktop: false,
    autofocus: true,
  },
)

const emit = defineEmits<{
  scan: [result: ScanParseResult]
}>()

const { t } = useI18n()
const inputId = useId()
const inputRef = ref<HTMLInputElement | null>(null)
const buffer = ref('')

interface LogEntry {
  time: string
  summary: string
}

const log = ref<LogEntry[]>([])

function pushLog(result: ScanParseResult) {
  const entry: LogEntry = {
    time: new Date().toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
    summary: formatScanParseResult(result),
  }
  log.value = [entry, ...log.value].slice(0, 20)
}

function onEnter() {
  const raw = buffer.value.trim()
  if (!raw) return
  const result = parseScanInput(raw)
  pushLog(result)
  emit('scan', result)
  buffer.value = ''
  void nextTick(() => inputRef.value?.focus())
}

function focusInput() {
  inputRef.value?.focus()
}

onMounted(() => {
  if (props.autofocus) {
    void nextTick(() => focusInput())
  }
})

watch(
  () => props.autofocus,
  (v) => {
    if (v) void nextTick(() => focusInput())
  },
)

defineExpose({ focusInput })
</script>

<style scoped>
.devices-scan-capture {
  margin-bottom: 12px;
}

.scan-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
}

.scan-input {
  width: 100%;
  box-sizing: border-box;
  border: 2px solid #2563eb;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: 16px;
  font-family: ui-monospace, monospace;
  background: #fff;
}

.scan-input--hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.devices-scan-capture--desktop .scan-input:not(.scan-input--hidden) {
  font-size: 14px;
  padding: 10px 12px;
}

.scan-hint {
  margin: 8px 0 0;
  font-size: 12px;
  line-height: 1.4;
}

.scan-log {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  max-height: 160px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.scan-log-item {
  display: flex;
  gap: 10px;
  padding: 8px 10px;
  font-size: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.scan-log-item:last-child {
  border-bottom: none;
}

.scan-log-time {
  flex-shrink: 0;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

.scan-log-summary {
  word-break: break-all;
  font-family: ui-monospace, monospace;
}

.muted {
  color: #64748b;
}
</style>
