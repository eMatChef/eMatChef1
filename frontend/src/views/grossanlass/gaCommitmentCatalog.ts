import { computed, inject, provide, ref, watch, type ComputedRef, type InjectionKey, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getGrossanlassCommitments,
  type GrossanlassCommitment,
} from '@/api/grossanlassCommitments'
import {
  commitmentToArticle,
  commitmentToPreviewRow,
} from '@/views/grossanlass/grossanlassCommitmentMap'
import type { GaPreviewRow } from '@/views/grossanlass/grossanlassMaterialsPreviewData'
import type { GaZusageArticle } from '@/views/grossanlass/grossanlassZusagePreviewData'

export type GaCommitmentCatalog = {
  loading: Ref<boolean>
  error: Ref<string | null>
  commitments: Ref<GrossanlassCommitment[]>
  articles: ComputedRef<GaZusageArticle[]>
  rows: ComputedRef<GaPreviewRow[]>
  load: () => Promise<void>
  upsert: (row: GrossanlassCommitment) => void
}

export const gaCommitmentCatalogKey: InjectionKey<GaCommitmentCatalog> = Symbol('gaCommitmentCatalog')

export function createGaCommitmentCatalog(
  departmentId: ComputedRef<string>,
  t: (key: string) => string,
  locale: ComputedRef<string> | Ref<string>,
): GaCommitmentCatalog {
  const commitments = ref<GrossanlassCommitment[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    if (!departmentId.value) return
    loading.value = true
    error.value = null
    try {
      commitments.value = await getGrossanlassCommitments(departmentId.value)
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      error.value = err.response?.data?.error || 'load-error'
      commitments.value = []
    } finally {
      loading.value = false
    }
  }

  function upsert(row: GrossanlassCommitment) {
    const index = commitments.value.findIndex((item) => item.id === row.id)
    if (index >= 0) {
      const next = [...commitments.value]
      next[index] = row
      commitments.value = next
      return
    }
    commitments.value = [row, ...commitments.value]
  }

  const articles = computed(() => commitments.value.map((row) => commitmentToArticle(row)))
  const rows = computed(() =>
    commitments.value.map((row) => commitmentToPreviewRow(row, t, locale.value)),
  )

  watch(departmentId, () => {
    void load()
  }, { immediate: true })

  return { loading, error, commitments, articles, rows, load, upsert }
}

export function provideGaCommitmentCatalog(): GaCommitmentCatalog {
  const route = useRoute()
  const { t, locale } = useI18n()
  const departmentId = computed(() => String(route.params.departmentId || ''))
  const catalog = createGaCommitmentCatalog(departmentId, (key) => String(t(key)), locale)
  provide(gaCommitmentCatalogKey, catalog)
  return catalog
}

export function useGaCommitmentCatalog(): GaCommitmentCatalog {
  const injected = inject(gaCommitmentCatalogKey, null)
  if (injected) return injected
  const route = useRoute()
  const { t, locale } = useI18n()
  const departmentId = computed(() => String(route.params.departmentId || ''))
  return createGaCommitmentCatalog(departmentId, (key) => String(t(key)), locale)
}
