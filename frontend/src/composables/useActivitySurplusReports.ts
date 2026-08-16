import { computed, ref, watch, type Ref } from 'vue'
import {
  deleteActivitySurplusReport,
  getActivitySurplusReports,
  patchActivitySurplusReport,
  postActivitySurplusReport,
  type ActivitySurplusReport,
  type ActivitySurplusReportKind,
  type CreateActivitySurplusReportBody,
  type PatchActivitySurplusReportBody,
} from '@/api/activitySurplusReports'

export function useActivitySurplusReports(options: {
  activityId: Ref<string>
  enabled?: Ref<boolean>
}) {
  const reports = ref<ActivitySurplusReport[]>([])
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref<string | null>(null)

  const openReports = computed(() =>
    reports.value.filter((r) => r.status === 'open' || r.status === 'matched'),
  )

  async function reload(): Promise<void> {
    if (!options.activityId.value) return
    if (options.enabled && !options.enabled.value) {
      reports.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      reports.value = await getActivitySurplusReports(options.activityId.value)
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e)
      reports.value = []
    } finally {
      loading.value = false
    }
  }

  async function createReport(body: {
    nameFreeText: string
    qty: number
    kind: ActivitySurplusReportKind
    expiryDate?: string | null
    notes?: string | null
  }): Promise<ActivitySurplusReport | null> {
    if (!options.activityId.value) return null
    submitting.value = true
    try {
      const payload: CreateActivitySurplusReportBody = {
        name_free_text: body.nameFreeText,
        qty: body.qty,
        kind: body.kind,
        expiry_date: body.expiryDate ?? null,
        notes: body.notes ?? null,
      }
      const created = await postActivitySurplusReport(options.activityId.value, payload)
      reports.value = [created, ...reports.value.filter((r) => r.id !== created.id)]
      return created
    } finally {
      submitting.value = false
    }
  }

  async function updateReport(
    reportId: string,
    body: PatchActivitySurplusReportBody,
  ): Promise<ActivitySurplusReport | null> {
    if (!options.activityId.value) return null
    submitting.value = true
    try {
      const updated = await patchActivitySurplusReport(options.activityId.value, reportId, body)
      reports.value = reports.value.map((r) => (r.id === reportId ? updated : r))
      return updated
    } finally {
      submitting.value = false
    }
  }

  async function removeReport(reportId: string): Promise<void> {
    if (!options.activityId.value) return
    submitting.value = true
    try {
      await deleteActivitySurplusReport(options.activityId.value, reportId)
      reports.value = reports.value.filter((r) => r.id !== reportId)
    } finally {
      submitting.value = false
    }
  }

  async function dismissReport(reportId: string): Promise<void> {
    await updateReport(reportId, { status: 'dismissed' })
  }

  const enabled = computed(() => options.enabled?.value ?? true)

  watch(
    [options.activityId, enabled],
    () => {
      void reload()
    },
    { immediate: true },
  )

  return {
    reports,
    openReports,
    loading,
    submitting,
    error,
    reload,
    createReport,
    updateReport,
    removeReport,
    dismissReport,
  }
}
