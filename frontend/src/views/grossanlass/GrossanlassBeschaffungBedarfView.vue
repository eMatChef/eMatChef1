<template>
  <div class="beschaffung-bedarf">
    <p class="bedarf-intro">{{ t('grossanlass.beschaffung.bedarf.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <div v-else class="bedarf-layout">
      <section class="bedarf-panel bedarf-panel--pool">
        <div class="panel-head">
          <h3>{{ t('grossanlass.beschaffung.bedarf.poolTitle') }}</h3>
          <span class="panel-count">{{ pool.length }}</span>
        </div>
        <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.poolHint') }}</p>

        <EEmptyState
          v-if="pool.length === 0"
          variant="default"
          icon="mdi-clipboard-check-outline"
          :title="t('grossanlass.beschaffung.bedarf.poolEmptyTitle')"
          :description="t('grossanlass.beschaffung.bedarf.poolEmptyDescription')"
        />

        <div v-else class="pool-actions">
          <EButton
            variant="primary"
            size="small"
            :disabled="selectedWishIds.length === 0 || isSaving"
            :loading="isSaving"
            @click="bundleSelected"
          >
            {{ t('grossanlass.beschaffung.bedarf.bundleAction', { count: selectedWishIds.length }) }}
          </EButton>
          <EButton
            v-if="selectedWishIds.length > 0 && lines.length > 0"
            variant="secondary"
            size="small"
            :disabled="!mergeTargetLineId || isSaving"
            @click="mergeIntoLine"
          >
            {{ t('grossanlass.beschaffung.bedarf.mergeIntoLine') }}
          </EButton>
          <ESelect
            v-if="selectedWishIds.length > 0 && lines.length > 0"
            v-model="mergeTargetLineId"
            :items="lineSelectItems"
            :label="t('grossanlass.beschaffung.bedarf.mergeTarget')"
            hide-details
            density="compact"
            class="merge-select"
          />
        </div>

        <div v-if="pool.length > 0" class="wish-pool-list">
          <label
            v-for="wish in pool"
            :key="wish.id"
            class="pool-row"
            :class="{ 'is-selected': selectedWishIds.includes(wish.id) }"
          >
            <input
              type="checkbox"
              :value="wish.id"
              :checked="selectedWishIds.includes(wish.id)"
              @change="toggleWish(wish.id)"
            />
            <div class="pool-row__body">
              <div class="pool-row__main">
                <strong>{{ wish.quantity }}× {{ wish.label }}</strong>
                <span class="kind-tag">{{ wishKindLabel(wish.wish_kind) }}</span>
              </div>
              <div class="pool-row__meta">
                {{ wish.group_name }} · {{ wish.location }}
              </div>
              <div class="pool-row__meta">
                {{ wish.round_name }} · {{ wish.created_by_name }}
              </div>
            </div>
          </label>
        </div>
      </section>

      <section class="bedarf-panel bedarf-panel--lines">
        <div class="panel-head">
          <h3>{{ t('grossanlass.beschaffung.bedarf.linesTitle') }}</h3>
          <span class="panel-count">{{ lines.length }}</span>
        </div>
        <p class="panel-hint">{{ t('grossanlass.beschaffung.bedarf.linesHint') }}</p>

        <EEmptyState
          v-if="lines.length === 0"
          variant="default"
          icon="mdi-package-variant-closed"
          :title="t('grossanlass.beschaffung.bedarf.linesEmptyTitle')"
          :description="t('grossanlass.beschaffung.bedarf.linesEmptyDescription')"
        />

        <div v-else class="lines-list">
          <div v-for="line in lines" :key="line.id" class="line-card">
            <div class="line-card__head">
              <div>
                <strong>{{ line.quantity }}× {{ line.label }}</strong>
                <span class="status-chip">{{ statusLabel(line.status) }}</span>
              </div>
              <button
                v-if="line.status === 'bedarf'"
                type="button"
                class="icon-btn icon-btn--danger"
                :title="t('common.delete')"
                @click="removeLine(line)"
              >
                <v-icon icon="mdi-delete-outline" size="18" />
              </button>
            </div>
            <div class="line-card__meta">
              {{ line.group_name }} · {{ line.location }}
            </div>
            <div class="line-card__meta">
              {{ t('grossanlass.beschaffung.bedarf.wishCount', { count: line.wish_count }) }}
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESelect } from '@/components/form/base'
import {
  addWishesToGrossanlassProcurementLine,
  createGrossanlassProcurementLine,
  deleteGrossanlassProcurementLine,
  getGrossanlassBedarfOverview,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementPoolWish,
} from '@/api/grossanlassProcurement'
import type { GrossanlassWishKind } from '@/api/grossanlassWishes'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))

const pool = ref<GrossanlassProcurementPoolWish[]>([])
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const selectedWishIds = ref<string[]>([])
const mergeTargetLineId = ref<string | null>(null)

const lineSelectItems = computed(() =>
  lines.value
    .filter((l) => l.status === 'bedarf')
    .map((l) => ({ title: `${l.quantity}× ${l.label}`, value: l.id })),
)

function wishKindLabel(kind: GrossanlassWishKind): string {
  switch (kind) {
    case 'material':
      return t('grossanlass.wishes.kindMaterial')
    case 'fahrzeug':
      return t('grossanlass.wishes.kindFahrzeug')
    default:
      return t('grossanlass.wishes.kindBeides')
  }
}

function statusLabel(status: string): string {
  if (status === 'bedarf') return t('grossanlass.beschaffung.bedarf.statusBedarf')
  return status
}

function toggleWish(id: string) {
  if (selectedWishIds.value.includes(id)) {
    selectedWishIds.value = selectedWishIds.value.filter((x) => x !== id)
  } else {
    selectedWishIds.value = [...selectedWishIds.value, id]
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    const data = await getGrossanlassBedarfOverview(departmentId.value)
    pool.value = data.pool
    lines.value = data.lines
    selectedWishIds.value = []
    mergeTargetLineId.value = lineSelectItems.value[0]?.value ?? null
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

async function bundleSelected() {
  if (!departmentId.value || selectedWishIds.value.length === 0) return
  isSaving.value = true
  try {
    await createGrossanlassProcurementLine(departmentId.value, {
      wish_line_ids: selectedWishIds.value,
    })
    toast.success(t('grossanlass.beschaffung.bedarf.bundleSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorBundle'))
  } finally {
    isSaving.value = false
  }
}

async function mergeIntoLine() {
  if (!departmentId.value || !mergeTargetLineId.value || selectedWishIds.value.length === 0) return
  isSaving.value = true
  try {
    await addWishesToGrossanlassProcurementLine(departmentId.value, mergeTargetLineId.value, {
      wish_line_ids: selectedWishIds.value,
    })
    toast.success(t('grossanlass.beschaffung.bedarf.mergeSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorMerge'))
  } finally {
    isSaving.value = false
  }
}

async function removeLine(line: GrossanlassProcurementLine) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.bedarf.deleteTitle'),
    message: t('grossanlass.beschaffung.bedarf.deleteMessage', { label: line.label }),
  })
  if (!ok) return
  try {
    await deleteGrossanlassProcurementLine(departmentId.value, line.id)
    toast.success(t('grossanlass.beschaffung.bedarf.deleteSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.bedarf.errorDelete'))
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-bedarf {
  padding: 8px 0 24px;
}

.bedarf-intro {
  margin: 0 0 16px;
  color: #64748b;
  font-size: 0.9rem;
}

.bedarf-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 960px) {
  .bedarf-layout {
    grid-template-columns: 1fr;
  }
}

.bedarf-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
}

.panel-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.panel-head h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.panel-count {
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 2px 8px;
}

.panel-hint {
  margin: 0 0 12px;
  font-size: 0.82rem;
  color: #94a3b8;
}

.pool-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
  margin-bottom: 12px;
}

.merge-select {
  flex: 1 1 200px;
  min-width: 160px;
}

.wish-pool-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 520px;
  overflow-y: auto;
}

.pool-row {
  display: flex;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
}

.pool-row.is-selected {
  border-color: #93c5fd;
  background: #eff6ff;
}

.pool-row__body {
  flex: 1;
  min-width: 0;
}

.pool-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.pool-row__meta {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 2px;
}

.kind-tag {
  font-size: 0.72rem;
  color: #6b7280;
}

.lines-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.line-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
}

.line-card__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.line-card__meta {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 4px;
}

.status-chip {
  display: inline-block;
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  background: #e0e7ff;
  color: #3730a3;
}

.icon-btn {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  width: 28px;
  height: 28px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-btn--danger {
  color: #dc2626;
}
</style>
