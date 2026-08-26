import { describe, expect, it } from 'vitest'
import { nextStartCell, sheetCellForItem, sheetPageCount } from '@/print/sheetPlacement'

describe('sheetPlacement', () => {
  it('fills from the start cell and wraps onto a full next sheet', () => {
    expect(sheetCellForItem(0, 10, 3)).toEqual({ pageIndex: 0, cellIndex: 3 })
    expect(sheetCellForItem(6, 10, 3)).toEqual({ pageIndex: 0, cellIndex: 9 })
    expect(sheetCellForItem(7, 10, 3)).toEqual({ pageIndex: 1, cellIndex: 0 })
    expect(sheetCellForItem(8, 10, 3)).toEqual({ pageIndex: 1, cellIndex: 1 })
  })

  it('counts pages including a leftover first sheet', () => {
    expect(sheetPageCount(3, 10, 0)).toBe(1)
    expect(sheetPageCount(7, 10, 3)).toBe(1)
    expect(sheetPageCount(8, 10, 3)).toBe(2)
    expect(sheetPageCount(12, 10, 0)).toBe(2)
  })

  it('remembers the next free cell after a print', () => {
    expect(nextStartCell(3, 10, 0)).toBe(4)
    expect(nextStartCell(5, 10, 7)).toBe(3)
    expect(nextStartCell(10, 10, 0)).toBe(1)
    expect(nextStartCell(1, 10, 9)).toBe(1)
  })
})
