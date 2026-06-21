<template>
  <div class="beschaffung-offerten">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.offerten.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="lines.length === 0"
      variant="default"
      icon="mdi-file-document-outline"
      :title="t('grossanlass.beschaffung.offerten.emptyTitle')"
      :description="t('grossanlass.beschaffung.offerten.emptyDescription')"
    />

    <div v-else class="lines-list">
      <article v-for="line in lines" :key="line.id" class="line-card">
        <GrossanlassProcurementLineSummary :line="line" />

        <div class="quotes-block">
          <h4>{{ t('grossanlass.beschaffung.offerten.quotesTitle') }}</h4>
          <ul v-if="line.quotes.length" class="quotes-list">
            <li v-for="quote in line.quotes" :key="quote.id" class="quote-row" :class="{ 'is-selected': quote.selected }">
              <div>
                <strong>{{ quote.supplier }}</strong>
                <span v-if="quote.supplier_address?.city_line" class="quote-supplier-meta">
                  · {{ quote.supplier_address.city_line }}
                </span>
                <span class="quote-amount">{{ formatChf(quote.amount_chf) }}</span>
                <p v-if="quote.notes" class="quote-notes">{{ quote.notes }}</p>
                <a
                  v-if="quote.pdf_url"
                  :href="resolvePdfUrl(quote.pdf_url)"
                  target="_blank"
                  rel="noopener"
                  class="quote-pdf-link"
                >
                  {{ t('grossanlass.beschaffung.offerten.viewPdf') }}
                </a>
              </div>
              <div class="quote-actions">
                <EButton
                  v-if="!quote.selected && canEditQuotes(line)"
                  variant="primary"
                  size="small"
                  :loading="selectingId === quote.id"
                  @click="selectQuote(line, quote.id)"
                >
                  {{ t('grossanlass.beschaffung.offerten.selectQuote') }}
                </EButton>
                <span v-if="quote.selected" class="selected-badge">{{ t('grossanlass.beschaffung.offerten.selected') }}</span>
                <button
                  v-if="canEditQuotes(line)"
                  type="button"
                  class="icon-btn"
                  :title="t('common.edit')"
                  @click="openEditQuote(line, quote)"
                >
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
                <button
                  v-if="!quote.selected && canEditQuotes(line)"
                  type="button"
                  class="icon-btn icon-btn--danger"
                  :title="t('common.delete')"
                  @click="deleteQuote(line, quote.id)"
                >
                  <v-icon icon="mdi-delete-outline" size="16" />
                </button>
              </div>
            </li>
          </ul>
          <p v-else class="muted">{{ t('grossanlass.beschaffung.offerten.noQuotes') }}</p>

          <EButton
            v-if="canEditQuotes(line)"
            variant="secondary"
            size="small"
            @click="openAddQuote(line)"
          >
            {{ t('grossanlass.beschaffung.offerten.addQuote') }}
          </EButton>
        </div>
      </article>
    </div>

    <GrossanlassProcurementQuoteDialog
      v-if="quoteDialogLine"
      v-model="quoteDialogOpen"
      :department-id="departmentId()"
      :line="quoteDialogLine"
      :quote="quoteDialogQuote"
      @saved="onQuoteSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import GrossanlassProcurementQuoteDialog from '@/components/grossanlass/GrossanlassProcurementQuoteDialog.vue'
import { EButton } from '@/components/form/base'
import { resolveMediaPreviewUrl } from '@/api/media'
import {
  deleteGrossanlassProcurementQuote,
  formatChf,
  listGrossanlassProcurementLines,
  selectGrossanlassProcurementQuote,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementQuote,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = () => String(route.params.departmentId || '')
const lines = ref<GrossanlassProcurementLine[]>([])
const isLoading = ref(true)
const selectingId = ref<string | null>(null)

const quoteDialogOpen = ref(false)
const quoteDialogLine = ref<GrossanlassProcurementLine | null>(null)
const quoteDialogQuote = ref<GrossanlassProcurementQuote | null>(null)

function canEditQuotes(line: GrossanlassProcurementLine): boolean {
  return ['bedarf', 'offerte_eingeholt', 'budgetiert'].includes(line.status)
}

function resolvePdfUrl(url: string): string {
  return resolveMediaPreviewUrl(url)
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    const all = await listGrossanlassProcurementLines(departmentId())
    lines.value = all.filter((l) => l.status !== 'erhalten')
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.offerten.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

function openAddQuote(line: GrossanlassProcurementLine) {
  quoteDialogLine.value = line
  quoteDialogQuote.value = null
  quoteDialogOpen.value = true
}

function openEditQuote(line: GrossanlassProcurementLine, quote: GrossanlassProcurementQuote) {
  quoteDialogLine.value = line
  quoteDialogQuote.value = quote
  quoteDialogOpen.value = true
}

async function onQuoteSaved() {
  toast.success(t('grossanlass.beschaffung.offerten.addSuccess'))
  await load()
}

async function selectQuote(line: GrossanlassProcurementLine, quoteId: string) {
  selectingId.value = quoteId
  try {
    await selectGrossanlassProcurementQuote(departmentId(), line.id, quoteId)
    toast.success(t('grossanlass.beschaffung.offerten.selectSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.offerten.errorSave'))
  } finally {
    selectingId.value = null
  }
}

async function deleteQuote(line: GrossanlassProcurementLine, quoteId: string) {
  const ok = await confirm.confirm({
    title: t('common.delete'),
    message: t('grossanlass.beschaffung.offerten.deleteQuoteConfirm'),
  })
  if (!ok) return
  try {
    await deleteGrossanlassProcurementQuote(departmentId(), line.id, quoteId)
    toast.success(t('grossanlass.beschaffung.offerten.deleteSuccess'))
    await load()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.offerten.errorSave'))
  }
}

onMounted(load)
</script>

<style scoped>
.beschaffung-offerten { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.lines-list { display: flex; flex-direction: column; gap: 12px; }
.line-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; background: #fff; }
.quotes-block { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e5e7eb; }
.quotes-block h4 { margin: 0 0 8px; font-size: 0.85rem; font-weight: 600; }
.quotes-list { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.quote-row { display: flex; justify-content: space-between; gap: 8px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 6px; }
.quote-row.is-selected { border-color: #93c5fd; background: #eff6ff; }
.quote-amount { margin-left: 8px; font-weight: 600; }
.quote-supplier-meta { font-size: 0.78rem; color: #64748b; }
.quote-notes { margin: 4px 0 0; font-size: 0.75rem; color: #64748b; }
.quote-pdf-link { display: inline-block; margin-top: 4px; font-size: 0.75rem; color: #2563eb; }
.quote-actions { display: flex; align-items: center; gap: 6px; }
.selected-badge { font-size: 0.72rem; font-weight: 600; color: #1d4ed8; }
.muted { font-size: 0.8rem; color: #94a3b8; margin: 0 0 8px; }
.icon-btn { border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; width: 28px; height: 28px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.icon-btn--danger { color: #dc2626; }
</style>
