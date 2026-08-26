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
import type { LayoutSample } from '@/print/renderPrintLayout'

export type PrintJobItem = {
  label: string
  public_code?: string | null
  public_url: string
  extras?: Partial<Record<'event' | 'ressort' | 'role' | 'drive', string>>
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
  const lines: string[] = []
  if (enabled.includes('title') && item.label.trim()) lines.push(item.label.trim())
  const extras = item.extras || {}
  if (enabled.includes('event') && extras.event?.trim()) lines.push(extras.event.trim())
  const place: string[] = []
  if (enabled.includes('ressort') && extras.ressort?.trim()) place.push(extras.ressort.trim())
  if (enabled.includes('role') && extras.role?.trim()) place.push(extras.role.trim())
  if (place.length) lines.push(place.join(' · '))
  if (enabled.includes('drive') && extras.drive?.trim()) lines.push(extras.drive.trim())
  return {
    label: lines.join('\n'),
    public_url: enabled.includes('qr') ? item.public_url : '',
    public_code: enabled.includes('code') ? (item.public_code || '') : '',
  }
}

export async function executePrintJob(options: {
  departmentId: string
  layout: PrintLayout
  items: PrintJobItem[]
  enabledFields: PrintContentKey[]
  startIndex: number
}): Promise<'ql' | 'pdf'> {
  const jobLayout = layoutWithEnabledFields(options.layout, layoutKeysFromContent(options.enabledFields))
  const rows = options.items.map((item) => sampleForPrint(item, options.enabledFields))
  if (isBrotherQlLayout(jobLayout)) {
    if (!cachedQlDevice) cachedQlDevice = await requestBrotherQlDevice()
    await printCartLayoutToQl(cachedQlDevice, jobLayout, rows)
    return 'ql'
  }
  await downloadCartLayoutPdf(
    options.departmentId,
    jobLayout,
    rows,
    options.startIndex,
    true,
  )
  return 'pdf'
}
