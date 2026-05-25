import { postMovePackItem } from '@/api/activityPackItems'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { getPublicMaterialBatchByCodes } from '@/api/public/publicLookup'
import {
  getBackendStage,
  getStageLeftQty,
  isPackReturnOrUnpackWarehouseStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import {
  autoPackStageForProfile,
  packWorkflowProfileForActivityType,
  type PackWorkflowProfile,
} from '@/components/activities/packWorkflowProfile'
import type { ScanParseResult } from '@/utils/scanParser'

export type DevicesHinErrorCode =
  | 'wrong_scan_type'
  | 'not_editable'
  | 'lookup_failed'
  | 'wrong_department'
  | 'not_on_list'
  | 'hin_stage_blocked'
  | 'nothing_to_move'
  | 'move_failed'
  | 'offline'

export type DevicesHinResult =
  | {
      ok: true
      packItem: ActivityPackItem
      moveQty: number
      materialName: string
      stage: PackStage
    }
  | { ok: false; code: DevicesHinErrorCode; detail?: string }

export interface DevicesHinContext {
  profile: PackWorkflowProfile
  stage: PackStage
}

export function resolveDevicesHinStage(
  activityType: string,
  activityStatus: string,
  canManageMaterials: boolean,
): DevicesHinContext {
  const profile = packWorkflowProfileForActivityType(activityType || 'activity')
  const stage = autoPackStageForProfile(profile, activityStatus, canManageMaterials)
  return { profile, stage }
}

export function getDevicesHinLeftQty(
  packItem: ActivityPackItem,
  activityType: string,
  activityStatus: string,
  canManageMaterials: boolean,
): number {
  const { profile, stage } = resolveDevicesHinStage(activityType, activityStatus, canManageMaterials)
  return getStageLeftQty(packItem, stage, profile)
}

function findPackItemByMaterialId(
  packItems: ActivityPackItem[],
  materialItemId: string,
): ActivityPackItem | undefined {
  return packItems.find((p) => p.materialItemId === materialItemId)
}

/**
 * Hin-Flow (D3): ein Bündel (+1) oder gesamte Restmenge (moveAll).
 */
export async function executeDevicesHinMoveForPackItem(params: {
  activityId: string
  departmentId: string
  activityType: string
  activityStatus: string
  isPackListEditable: boolean
  packItem: ActivityPackItem
  moveAll: boolean
  canManageMaterials: boolean
  materialName?: string
}): Promise<DevicesHinResult> {
  if (!params.isPackListEditable) {
    return { ok: false, code: 'not_editable' }
  }

  const { profile, stage } = resolveDevicesHinStage(
    params.activityType,
    params.activityStatus,
    params.canManageMaterials,
  )

  if (isPackReturnOrUnpackWarehouseStage(stage)) {
    return { ok: false, code: 'hin_stage_blocked' }
  }

  const left = getStageLeftQty(params.packItem, stage, profile)
  if (left <= 0) {
    return { ok: false, code: 'nothing_to_move' }
  }

  const moveQty = params.moveAll ? left : Math.min(1, left)

  try {
    const updated = await postMovePackItem(params.activityId, params.packItem.id, {
      stage: getBackendStage(stage),
      quantity: moveQty,
    })
    return {
      ok: true,
      packItem: updated,
      moveQty,
      materialName: params.materialName || params.packItem.materialName,
      stage,
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } }; message?: string; code?: string }
    if (!err.response && (err.message === 'Network Error' || err.code === 'ERR_NETWORK')) {
      return { ok: false, code: 'offline', detail: err.message }
    }
    return {
      ok: false,
      code: 'move_failed',
      detail: err.response?.data?.error || err.message,
    }
  }
}

/**
 * Material-QR → Pack-Zeile → move (+1 Bündel).
 */
export async function executeDevicesHinMove(params: {
  activityId: string
  departmentId: string
  activityType: string
  activityStatus: string
  isPackListEditable: boolean
  packItems: ActivityPackItem[]
  scan: ScanParseResult
  canManageMaterials: boolean
}): Promise<DevicesHinResult> {
  if (params.scan.type !== 'material_batch') {
    return { ok: false, code: 'wrong_scan_type' }
  }

  let lookup
  try {
    lookup = await getPublicMaterialBatchByCodes(params.scan.materialCode, params.scan.batchCode)
  } catch {
    return { ok: false, code: 'lookup_failed' }
  }

  const matDept = lookup.department?.id
  if (matDept && matDept !== params.departmentId) {
    return { ok: false, code: 'wrong_department' }
  }

  const materialId = lookup.material?.id
  if (!materialId) {
    return { ok: false, code: 'lookup_failed' }
  }

  const packItem = findPackItemByMaterialId(params.packItems, materialId)
  if (!packItem) {
    return { ok: false, code: 'not_on_list' }
  }

  return executeDevicesHinMoveForPackItem({
    activityId: params.activityId,
    departmentId: params.departmentId,
    activityType: params.activityType,
    activityStatus: params.activityStatus,
    isPackListEditable: params.isPackListEditable,
    packItem,
    moveAll: false,
    canManageMaterials: params.canManageMaterials,
    materialName: packItem.materialName || lookup.material.name,
  })
}
