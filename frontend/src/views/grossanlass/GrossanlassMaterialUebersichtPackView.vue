<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.chain.packIntro') }}</p>
    <section v-for="phase in phases" :key="phase.id" class="card">
      <h3>{{ t(`grossanlass.chain.packPhase.${phase.id}`) }}</h3>
      <label v-for="row in phase.rows" :key="row.id" class="pack-line">
        <input type="checkbox" :checked="row.packed" @change="onToggle(row.id, !row.packed)">
        <span>
          <strong>{{ row.name }}</strong>
          <span class="qty">{{ t('grossanlass.chain.qtyLine', { n: row.qty }) }}</span>
        </span>
      </label>
      <p v-if="!phase.rows.length" class="empty">{{ t('grossanlass.chain.issueEmpty') }}</p>
    </section>

    <section v-for="einsatz in tripEinsaetze" :key="einsatz.id" class="card">
      <div class="pack-head">
        <h3>{{ einsatz.object_name }}</h3>
        <EButton variant="secondary" size="small" :loading="busyId === einsatz.id" @click="addPack(einsatz.id)">
          {{ t('grossanlass.chain.packAddPalette') }}
        </EButton>
      </div>
      <article v-for="pack in einsatz.packs ?? []" :key="pack.id" class="palette">
        <div class="palette__meta">
          <strong>{{ t('grossanlass.chain.packPalette', { n: pack.sort_order + 1 }) }}</strong>
          <a :href="pack.qr_url" target="_blank" rel="noopener">{{ pack.public_code }}</a>
          <span class="qty">{{ t(`public.lookup.packStatus.${pack.status}`) }}</span>
        </div>
        <v-alert v-if="pack.warning" type="warning" variant="tonal" density="compact" class="mb-2">
          {{ pack.warning }}
        </v-alert>
        <label v-for="line in pack.lines" :key="line.id" class="pack-line pack-line--qty">
          <span>
            <strong>{{ line.label }}</strong>
            <span class="qty">
              {{ line.valid_from ? formatWindow(line.valid_from, line.valid_to) : '' }}
            </span>
          </span>
          <input
            type="number"
            min="0"
            :value="line.qty_packed"
            @change="onQty(line.id, ($event.target as HTMLInputElement).value)"
          >
          <span class="qty">/ {{ line.qty_needed }}</span>
        </label>
        <EButton
          v-if="einsatz.delivery === 'trip' && !pack.trip_released"
          variant="primary"
          size="small"
          :loading="busyId === pack.id"
          @click="releasePack(pack.id)"
        >
          {{ t('grossanlass.materialUebersicht.tripsRelease') }}
        </EButton>
      </article>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import {
  addGrossanlassPack,
  releaseGrossanlassPack,
  updateGrossanlassPackLine,
} from '@/api/grossanlassLogistics'

const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const uebersicht = useGaUebersicht()
const busyId = ref<string | null>(null)
const departmentId = computed(() => String(route.params.departmentId || ''))
const lines = computed(() => uebersicht.data.value?.pack ?? [])
const phases = computed(() =>
  (['aufbau', 'anlass'] as const).map((id) => ({
    id,
    rows: lines.value.filter((row) => row.phase === id),
  })),
)
const tripEinsaetze = computed(() =>
  (uebersicht.data.value?.einsaetze ?? []).filter((row) => (row.packs ?? []).length > 0),
)

function formatWindow(from: string | null, to: string | null): string {
  if (!from) return ''
  const fmt = (iso: string) => {
    try {
      return new Intl.DateTimeFormat(locale.value, { day: '2-digit', month: '2-digit' }).format(new Date(iso))
    } catch {
      return iso
    }
  }
  return to ? `${fmt(from)}–${fmt(to)}` : fmt(from)
}

async function onToggle(id: string, packed: boolean) {
  try {
    await uebersicht.togglePacked(id, packed)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function onQty(lineId: string, raw: string) {
  if (!departmentId.value) return
  busyId.value = lineId
  try {
    await updateGrossanlassPackLine(departmentId.value, lineId, { qty_packed: Number(raw) || 0 })
    await uebersicht.load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyId.value = null
  }
}

async function addPack(einsatzId: string) {
  if (!departmentId.value) return
  busyId.value = einsatzId
  try {
    await addGrossanlassPack(departmentId.value, einsatzId)
    await uebersicht.load()
    toast.success(t('grossanlass.chain.packAdded'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyId.value = null
  }
}

async function releasePack(packId: string) {
  if (!departmentId.value) return
  busyId.value = packId
  try {
    await releaseGrossanlassPack(departmentId.value, packId)
    await uebersicht.load()
    toast.success(t('grossanlass.materialUebersicht.tripsReleasedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    busyId.value = null
  }
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #fff; }
.pack-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
.pack-line { display: flex; gap: 10px; align-items: center; padding: 6px 0; cursor: pointer; }
.pack-line--qty { cursor: default; }
.pack-line--qty input { width: 64px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 6px; }
.qty { margin-left: 8px; color: #64748b; font-size: 0.8rem; }
.empty { color: #64748b; font-size: 0.85rem; }
h3 { margin: 0 0 8px; font-size: 0.95rem; }
.palette { border-top: 1px dashed #e5e7eb; padding-top: 10px; margin-top: 10px; }
.palette__meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
</style>
