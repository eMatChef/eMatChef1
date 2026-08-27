import { describe, expect, it } from 'vitest'
import { defaultPrintFace, parsePrintFace } from '@/print/printFace'

describe('printFace', () => {
  it('defaults user cards to the colored badge', () => {
    expect(defaultPrintFace('user_card')).toEqual({ design: 'badge', color: true, rounded: true })
    expect(defaultPrintFace('label')).toEqual({ design: 'label', color: false, rounded: false })
  })

  it('parses stored face choices', () => {
    expect(parsePrintFace({ design: 'badge', color: false, rounded: true }, 'label')).toEqual({
      design: 'badge',
      color: false,
      rounded: true,
    })
  })
})
