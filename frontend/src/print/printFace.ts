export type PrintFaceDesign = 'label' | 'badge'

export type PrintFace = {
  design: PrintFaceDesign
  color: boolean
  rounded: boolean
}

export const PRINT_FACE_ACCENT = '#166534'
export const PRINT_FACE_FILL = '#ecfdf3'
export const PRINT_FACE_INK = '#111827'
export const PRINT_FACE_MUTED = '#64748b'

export function defaultPrintFace(kind: string): PrintFace {
  if (kind === 'user_card') return { design: 'badge', color: true, rounded: true }
  return { design: 'label', color: false, rounded: false }
}

export function parsePrintFace(raw: unknown, kind = 'label'): PrintFace {
  const fallback = defaultPrintFace(kind)
  if (!raw || typeof raw !== 'object') return fallback
  const value = raw as Partial<PrintFace>
  return {
    design: value.design === 'badge' ? 'badge' : 'label',
    color: typeof value.color === 'boolean' ? value.color : fallback.color,
    rounded: typeof value.rounded === 'boolean' ? value.rounded : fallback.rounded,
  }
}

export function faceInk(face: PrintFace) {
  return {
    accent: face.color ? PRINT_FACE_ACCENT : PRINT_FACE_INK,
    fill: face.color ? PRINT_FACE_FILL : '#ffffff',
    ink: PRINT_FACE_INK,
    muted: face.color ? PRINT_FACE_MUTED : '#4b5563',
  }
}

export function faceRadius(face: PrintFace, width: number, height: number): number {
  if (!face.rounded) return 0
  return Math.min(width, height) * 0.08
}
