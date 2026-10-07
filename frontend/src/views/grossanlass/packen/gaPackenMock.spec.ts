import { beforeEach, describe, expect, it } from 'vitest'
import {
  createPalette,
  linePackableNow,
  markPaletteDelivered,
  markPaletteReady,
  packStatusCounts,
  packableLines,
  projectStatus,
  projectTotals,
  resetGaPackenDemo,
  sendFahrauftrag,
  useGaPackenMock,
} from './gaPackenMock'

function project(id: string) {
  const found = useGaPackenMock().projects.value.find((row) => row.id === id)
  if (!found) throw new Error(id)
  return found
}

describe('gaPackenMock', () => {
  beforeEach(() => resetGaPackenDemo())

  it('classifies projects', () => {
    expect(projectStatus(project('pp-bar-west'))).toBe('partial')
    expect(projectStatus(project('pp-crew-zelt'))).toBe('packable')
    expect(projectStatus(project('pp-catering'))).toBe('waiting')
    expect(projectStatus(project('pp-info-pagode'))).toBe('done')
    expect(packStatusCounts(useGaPackenMock().projects.value)).toEqual({ packable: 1, partial: 1, waiting: 1, done: 1 })
  })

  it('suggests what can be packed now', () => {
    const bar = project('pp-bar-west')
    expect(packableLines(bar).map((line) => line.id)).toEqual(['pl-bw-holz', 'pl-bw-schrauben'])
    expect(linePackableNow(bar.lines[1]!)).toBe(120)
  })

  it('packs only the wood for Bar West although other material is missing', () => {
    const bar = project('pp-bar-west')
    const palette = createPalette('pp-bar-west', [{ lineId: 'pl-bw-holz', qty: 14 }], { title: 'Holz für Bar West' })
    expect(palette?.lines).toEqual([{ label: 'Kantholz 6×12 cm', qty: 14 }])
    expect(palette?.code).toBe('PK-0042')
    expect(bar.lines[0]!.packed).toBe(14)
    expect(bar.lines[0]!.available).toBe(0)
    expect(projectStatus(bar)).toBe('partial')
    expect(projectTotals(bar).missing).toBe(80 + 2)
  })

  it('does not pack more than available', () => {
    const palette = createPalette('pp-bar-west', [{ lineId: 'pl-bw-schrauben', qty: 999 }])
    expect(palette?.lines[0]?.qty).toBe(120)
    expect(createPalette('pp-catering', [{ lineId: 'pl-ca-zelt', qty: 1 }])).toBeNull()
  })

  it('sends a ready palette to logistics only once', () => {
    const palette = createPalette('pp-crew-zelt', [{ lineId: 'pl-cz-boden', qty: 24 }])!
    expect(sendFahrauftrag(palette)).toBe(false)
    markPaletteReady(palette)
    expect(sendFahrauftrag(palette)).toBe(true)
    expect(palette.status).toBe('sent')
    expect(sendFahrauftrag(palette)).toBe(false)
    markPaletteDelivered(palette)
    expect(palette.status).toBe('delivered')
  })
})
