import { describe, expect, it } from 'vitest'
import {
  normalizeProcurementLabel,
  procurementLabelsAreSimilar,
  procurementMatchKind,
} from '@/utils/grossanlassProcurementMatch'

describe('grossanlassProcurementMatch', () => {
  it('treats German plurals and umlauts as the same', () => {
    expect(normalizeProcurementLabel('Nägel')).toBe(normalizeProcurementLabel('Naegel'))
    expect(normalizeProcurementLabel('Schrauben!')).toBe(normalizeProcurementLabel('schraube'))
    expect(procurementLabelsAreSimilar('Schrauben', 'Schraube')).toBe(true)
    expect(procurementLabelsAreSimilar('Maschinen', 'Maschine')).toBe(true)
  })

  it('ranks exact and similar labels', () => {
    expect(procurementMatchKind(['Gator'], ['Gator', '2× Gator'])).toBe('exact')
    expect(procurementMatchKind(['Gator'], ['2× Gator'])).toBe('similar')
    expect(procurementMatchKind(['Wasser'], ['Zelt'])).toBeNull()
  })
})
