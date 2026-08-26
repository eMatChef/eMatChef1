import type { AddPrintCartItemRequest } from '@/api/tasks'
import type { PrintLayout } from '@/api/printLayouts'
import {
  DEFAULT_PRINT_CONTENT,
  layoutKeysFromContent,
  layoutWithEnabledFields,
  type PrintContentKey,
} from '@/print/layoutFields'
import { requestBrotherQlDevice } from '@/print/brotherQlUsb'
import {
  downloadCartLayoutPdf,
  isBrotherQlLayout,
  printCartLayoutToQl,
} from '@/print/printCartLayout'
import type { PrintFace } from '@/print/printFace'
import type { LayoutSample } from '@/print/renderPrintLayout'

export type PrintJobItem = {
  label: string
  public_code?: string | null
  public_url: string
  extras?: Partial<Record<'event' | 'ressort' | 'role' | 'drive', string>>
  /** If set, the print dialog can queue this row into the print cart. */
  cart?: AddPrintCartItemRequest
}

export type OpenPrintJobOptions = {
  departmentId: string
  items: PrintJobItem[]
  /** Checkboxes in the print dialog. Default: QR, Bezeichnung, Public-Code. */
  availableFields?: PrintContentKey[]
  /** Remembers field choices per kind (label vs user_card). */
  kind?: string
  onPrinted?: () => void | Promise<void>
}

let cachedQlDevice: USBDevice | null = null

export function defaultFieldsFor(available: PrintContentKey[]): PrintContentKey[] {
  const list = available.length ? available : DEFAULT_PRINT_CONTENT
  const base = DEFAULT_PRINT_CONTENT.filter((key) => list.includes(key))
  const extras = list.filter((key) => !DEFAULT_PRINT_CONTENT.includes(key))
  const next = [...base, ...extras]
  return next.length ? next : ['qr']
}

export function sampleForPrint(item: PrintJobItem, enabled: PrintContentKey[]): LayoutSample {
  const extras = item.extras || {}
  const lines: string[] = []
  const name = enabled.includes('title') ? item.label.trim() : ''
  if (name) lines.push(name)
  const event = enabled.includes('event') ? extras.event?.trim() || '' : ''
  if (event) lines.push(event)
  const place: string[] = []
  if (enabled.includes('ressort') && extras.ressort?.trim()) place.push(extras.ressort.trim())
  if (enabled.includes('role') && extras.role?.trim()) place.push(extras.role.trim())
  const placeLine = place.join(' · ')
  if (placeLine) lines.push(placeLine)
  const drive = enabled.includes('drive') ? extras.drive?.trim() || '' : ''
  if (drive) lines.push(drive)
  return {
    label: lines.join('\n'),
    name,
    public_url: enabled.includes('qr') ? item.public_url : '',
    public_code: enabled.includes('code') ? (item.public_code || '') : '',
    event,
    place: placeLine,
    drive,
  }
}

export async function executePrintJob(options: {
  departmentId: string
  layout: PrintLayout
  items: PrintJobItem[]
  enabledFields: PrintContentKey[]
  startIndex: number
  face?: PrintFace
}): Promise<'ql' | 'pdf'> {
  const jobLayout = layoutWithEnabledFields(options.layout, layoutKeysFromContent(options.enabledFields))
  const rows = options.items.map((item) => sampleForPrint(item, options.enabledFields))
  if (isBrotherQlLayout(jobLayout)) {
    if (!cachedQlDevice) cachedQlDevice = await requestBrotherQlDevice()
    await printCartLayoutToQl(cachedQlDevice, jobLayout, rows, options.face)
    return 'ql'
  }
  await downloadCartLayoutPdf(
    options.departmentId,
    jobLayout,
    rows,
    options.startIndex,
    true,
    options.face,
  )
  return 'pdf'
}
