<template>
  <section v-if="result" class="helper-scan-result">
    <header class="helper-scan-result__head">
      <div>
        <p class="helper-scan-result__kind">{{ kindLabel }}</p>
        <h3>{{ title }}</h3>
        <p v-if="subtitle" class="helper-scan-result__sub">{{ subtitle }}</p>
      </div>
      <EButton variant="secondary" size="x-small" @click="$emit('close')">
        {{ t('common.close') }}
      </EButton>
    </header>

    <div v-if="result.kind === 'place' && result.activePack" class="helper-scan-result__pack-hint">
      <span>{{ t('grossanlass.meinRessort.scanActivePack', { code: result.activePack.publicCode }) }}</span>
      <EButton variant="primary" size="small" :loading="arriveBusy" @click="$emit('arrive')">
        {{ t('public.lookup.placeArrive') }}
      </EButton>
    </div>

    <p v-if="!hasAssignments" class="helper-scan-result__empty">
      {{ t('grossanlass.meinRessort.scanNoAssignments') }}
    </p>

    <div v-if="groups.fahrauftraege.length" class="helper-scan-result__block">
      <h4>{{ t('grossanlass.meinRessort.homeFahrauftraegeTitle') }}</h4>
      <ul>
        <li v-for="row in groups.fahrauftraege" :key="row.id">
          <GrossanlassHelperAssignmentRow :assignment="row" @open="$emit('open-assignment', row)" />
        </li>
      </ul>
    </div>

    <div v-if="groups.bauauftraege.length" class="helper-scan-result__block">
      <h4>{{ t('grossanlass.meineEinsaetze.bauauftraegeTitle') }}</h4>
      <ul>
        <li v-for="row in groups.bauauftraege" :key="row.id">
          <GrossanlassHelperAssignmentRow :assignment="row" @open="$emit('open-assignment', row)" />
        </li>
      </ul>
    </div>

    <div v-if="groups.einsaetze.length" class="helper-scan-result__block">
      <h4>{{ t('grossanlass.meinRessort.homeEinsaetzeTitle') }}</h4>
      <ul>
        <li v-for="row in groups.einsaetze" :key="row.id">
          <GrossanlassHelperAssignmentRow :assignment="row" @open="$emit('open-assignment', row)" />
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import GrossanlassHelperAssignmentRow from '@/views/grossanlass/GrossanlassHelperAssignmentRow.vue'
import type { GaHelperScanResult } from '@/composables/useGrossanlassHelperScan'
import { splitHelperAssignments } from '@/views/grossanlass/grossanlassHelperScanFilter'
import type { GaHelperAssignment } from '@/views/grossanlass/grossanlassHelperAssignment'

const props = defineProps<{
  result: GaHelperScanResult | null
  arriveBusy?: boolean
}>()

defineEmits<{
  close: []
  arrive: []
  'open-assignment': [assignment: GaHelperAssignment]
}>()

const { t } = useI18n()

const groups = computed(() => splitHelperAssignments(props.result?.assignments ?? []))

const hasAssignments = computed(() => (props.result?.assignments.length ?? 0) > 0)

const kindLabel = computed(() => {
  if (props.result?.kind === 'pack') return t('grossanlass.meinRessort.scanResultPack')
  if (props.result?.kind === 'place') return t('grossanlass.meinRessort.scanResultPlace')
  return ''
})

const title = computed(() => {
  if (!props.result) return ''
  if (props.result.kind === 'place') return props.result.place.name
  return t('grossanlass.meinRessort.scanResultPackTitle', { code: props.result.pack.public_code })
})

const subtitle = computed(() => {
  if (!props.result) return ''
  if (props.result.kind === 'place' && props.result.place.public_code) {
    return props.result.place.public_code
  }
  if (props.result.kind === 'pack' && props.result.pack.current_place_name) {
    return props.result.pack.current_place_name
  }
  return ''
})
</script>

<style scoped>
.helper-scan-result {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 14px 16px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.helper-scan-result__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.helper-scan-result__kind {
  margin: 0 0 4px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.helper-scan-result h3 {
  margin: 0;
  font-size: 1.05rem;
  color: #0f172a;
}

.helper-scan-result__sub {
  margin: 4px 0 0;
  font-size: 0.82rem;
  color: #64748b;
}

.helper-scan-result__pack-hint {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #ecfdf5;
  font-size: 0.85rem;
  color: #047857;
}

.helper-scan-result__empty {
  margin: 0;
  font-size: 0.85rem;
  color: #64748b;
}

.helper-scan-result__block h4 {
  margin: 0 0 8px;
  font-size: 0.9rem;
  color: #334155;
}

.helper-scan-result__block ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.helper-scan-result__block li {
  margin: 0;
  padding: 0;
}
</style>
