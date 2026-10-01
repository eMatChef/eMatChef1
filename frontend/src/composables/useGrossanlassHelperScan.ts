import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import type { MaterialScanSessionEntry } from '@/composables/useMaterialJourneyScan'
import type { GrossanlassUserCard } from '@/api/grossanlassUserCards'
import { getGrossanlassHelperScanContext } from '@/api/grossanlassUebersicht'
import { getPublicGaPackByCode, getPublicGaPlaceByCode } from '@/api/public/publicLookup'
import {
  clearActivePack,
  readActivePack,
  rememberActivePack,
  scanArriveGrossanlassPack,
  scanStartGrossanlassPack,
  type GaLogisticsPack,
  type GaPlace,
} from '@/api/grossanlassLogistics'
import type { GaEinsatzOrgGroup } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import type { GaHelperAssignment } from '@/views/grossanlass/grossanlassHelperAssignment'
import { assignmentsFromScanContext } from '@/views/grossanlass/grossanlassHelperScanFilter'
import { parseScanInput } from '@/utils/scanParser'

export type GaHelperScanResult =
  | {
      kind: 'place'
      place: GaPlace
      assignments: GaHelperAssignment[]
      activePack: { packId: string; departmentId: string; publicCode: string } | null
    }
  | {
      kind: 'pack'
      place: null
      pack: GaLogisticsPack
      assignments: GaHelperAssignment[]
      activePack: null
    }

type HelperScanOptions = {
  departmentId: () => string
  locale: () => string
  orgGroups: () => GaEinsatzOrgGroup[]
  onChanged?: () => void | Promise<void>
}

export function useGrossanlassHelperScan(options: HelperScanOptions) {
  const { t } = useI18n()
  const toast = useToast()
  const scanQuery = ref('')
  const scanLoading = ref(false)
  const arriveBusy = ref(false)
  const sessionLog = ref<MaterialScanSessionEntry[]>([])
  const scanResult = ref<GaHelperScanResult | null>(null)
  const scanCards = ref<GrossanlassUserCard[]>([])
  const lastScanQuery = ref<{ placeId?: string; einsatzId?: string } | null>(null)

  const packTargetLabel = computed(() => {
    const pack = readActivePack()
    const deptId = options.departmentId()
    if (!pack || pack.departmentId !== deptId) return null
    return t('grossanlass.meinRessort.scanActivePack', { code: pack.publicCode })
  })

  function pushLog(label: string, tone: MaterialScanSessionEntry['tone']) {
    sessionLog.value = [
      { id: `${Date.now()}-${Math.random()}`, at: new Date(), label, tone },
      ...sessionLog.value.slice(0, 8),
    ]
  }

  function assertDepartmentEntity(
    entityDeptId: string | undefined,
    deptId: string,
  ): boolean {
    if (entityDeptId && entityDeptId !== deptId) {
      toast.error(t('grossanlass.meinRessort.scanWrongDepartment'))
      pushLog(t('grossanlass.meinRessort.scanWrongDepartment'), 'error')
      return false
    }
    return true
  }

  async function loadScanContext(params: { placeId?: string; einsatzId?: string }) {
    const deptId = options.departmentId()
    if (!deptId) return null
    lastScanQuery.value = params
    const payload = await getGrossanlassHelperScanContext(deptId, params)
    scanCards.value = payload.cards ?? []
    return assignmentsFromScanContext(payload, options.locale(), options.orgGroups())
  }

  async function showPlaceResult(place: GaPlace, deptId: string) {
    const assignments = await loadScanContext({ placeId: place.id }) ?? []
    const active = readActivePack()
    scanResult.value = {
      kind: 'place',
      place,
      assignments,
      activePack: active && active.departmentId === deptId ? active : null,
    }
    pushLog(t('grossanlass.meinRessort.scanResultPlaceLog', { name: place.name }), 'success')
  }

  async function showPackResult(pack: GaLogisticsPack) {
    const assignments = await loadScanContext({ einsatzId: pack.einsatz_id }) ?? []
    scanResult.value = {
      kind: 'pack',
      pack,
      place: null,
      assignments,
      activePack: null,
    }
    pushLog(
      t('grossanlass.meinRessort.scanResultPackLog', { code: pack.public_code }),
      'info',
    )
  }

  async function refreshScanResult() {
    const result = scanResult.value
    const deptId = options.departmentId()
    if (!result || !deptId || !lastScanQuery.value) return

    const assignments = await loadScanContext(lastScanQuery.value) ?? []
    if (result.kind === 'place') {
      const active = readActivePack()
      scanResult.value = {
        kind: 'place',
        place: result.place,
        assignments,
        activePack: active && active.departmentId === deptId ? active : null,
      }
      return
    }

    scanResult.value = {
      kind: 'pack',
      pack: result.pack,
      place: null,
      assignments,
      activePack: null,
    }
  }

  async function runChanged() {
    await options.onChanged?.()
    await refreshScanResult()
  }

  async function handlePackCode(packCode: string, deptId: string) {
    const pack = await getPublicGaPackByCode(packCode)
    if (!assertDepartmentEntity(pack.department?.id, deptId)) return

    const started = await scanStartGrossanlassPack(deptId, pack.id)
    rememberActivePack({
      packId: pack.id,
      departmentId: deptId,
      publicCode: pack.public_code,
    })
    await showPackResult(pack)
    if (started.warning) {
      toast.warning(started.warning)
    }
    await runChanged()
  }

  async function handlePlaceCode(placeCode: string, deptId: string) {
    const place = await getPublicGaPlaceByCode(placeCode)
    if (!assertDepartmentEntity(place.department?.id, deptId)) return
    await showPlaceResult(place, deptId)
  }

  async function tryRawPublicCode(raw: string, deptId: string) {
    try {
      await handlePackCode(raw, deptId)
      return
    } catch {
      /* try place next */
    }
    try {
      await handlePlaceCode(raw, deptId)
      return
    } catch {
      /* fall through */
    }
    toast.error(t('grossanlass.meinRessort.scanUnknown'))
    pushLog(t('grossanlass.meinRessort.scanUnknown'), 'error')
  }

  async function submit() {
    const raw = scanQuery.value.trim()
    const deptId = options.departmentId()
    if (!raw || scanLoading.value || !deptId) return

    scanLoading.value = true
    try {
      const parsed = parseScanInput(raw)
      if (parsed.type === 'ga_pack') {
        await handlePackCode(parsed.packCode, deptId)
      } else if (parsed.type === 'ga_place') {
        await handlePlaceCode(parsed.placeCode, deptId)
      } else {
        await tryRawPublicCode(raw, deptId)
      }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      const message = err.response?.data?.error || t('public.lookup.scanError')
      toast.error(message)
      pushLog(message, 'error')
    } finally {
      scanLoading.value = false
      scanQuery.value = ''
    }
  }

  async function arriveAtScannedPlace() {
    const result = scanResult.value
    const deptId = options.departmentId()
    if (result?.kind !== 'place' || !result.activePack || !deptId || arriveBusy.value) return

    arriveBusy.value = true
    try {
      await scanArriveGrossanlassPack(deptId, result.activePack.packId, result.place.id)
      clearActivePack()
      pushLog(t('public.lookup.placeArrived'), 'success')
      toast.success(t('public.lookup.placeArrived'))
      scanResult.value = {
        ...result,
        activePack: null,
      }
      await runChanged()
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      toast.error(err.response?.data?.error || t('public.lookup.wrongPlace'))
    } finally {
      arriveBusy.value = false
    }
  }

  function clearActivePackSelection() {
    clearActivePack()
    if (scanResult.value?.kind === 'place') {
      scanResult.value = { ...scanResult.value, activePack: null }
    }
    pushLog(t('grossanlass.meinRessort.scanPackCleared'), 'warning')
  }

  function clearScanResult() {
    scanResult.value = null
    lastScanQuery.value = null
    scanCards.value = []
  }

  return {
    scanQuery,
    scanLoading,
    arriveBusy,
    sessionLog,
    scanResult,
    scanCards,
    packTargetLabel,
    submit,
    arriveAtScannedPlace,
    clearActivePackSelection,
    clearScanResult,
  }
}
