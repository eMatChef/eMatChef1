import { describe, expect, it } from 'vitest'
import { formatScanParseResult, isScanLikeInput, parseScanInput } from '@/utils/scanParser'

describe('parseScanInput', () => {
  it('reads GA-Ort from /i/ga/ and legacy /i/p/', () => {
    expect(parseScanInput('https://qr.ematchef.ch/i/ga/ga1a2b3c4d')).toEqual({
      type: 'ga_place',
      placeCode: 'ga1a2b3c4d',
      raw: 'https://qr.ematchef.ch/i/ga/ga1a2b3c4d',
    })
    expect(parseScanInput('/i/p/pl1a2b3c4d').type).toBe('ga_place')
    expect(formatScanParseResult(parseScanInput('/i/ga/ga1a2b3c4d'))).toBe('ga_place:ga1a2b3c4d')
  })

  it('does not treat warehouse QR as GA-Ort', () => {
    expect(parseScanInput('/i/l/loc1').type).toBe('storage_address')
    expect(isScanLikeInput('/i/ga/ga1a2b3c4d')).toBe(true)
  })
})
