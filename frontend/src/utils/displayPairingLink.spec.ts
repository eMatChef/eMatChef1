import { describe, expect, it } from 'vitest'
import { extractDisplayPairingToken } from './displayPairingLink'

const TOKEN = 'Wom7WegZp-ZzHo6nLtD_0Vyb3S4AeKXiAsx9P8c2ukc'

describe('extractDisplayPairingToken', () => {
  it('reads the token from the pairing url', () => {
    expect(extractDisplayPairingToken(`https://app.ematchef.test/connect-display/${TOKEN}`)).toBe(TOKEN)
    expect(extractDisplayPairingToken(`  https://app.ematchef.ch/connect-display/${TOKEN}?x=1 `)).toBe(TOKEN)
  })

  it('accepts a bare token', () => {
    expect(extractDisplayPairingToken(TOKEN)).toBe(TOKEN)
  })

  it('rejects other qr codes and short values', () => {
    expect(extractDisplayPairingToken('https://qr.ematchef.ch/i/a/ABC123')).toBeNull()
    expect(extractDisplayPairingToken('https://app.ematchef.test/connect-display/short')).toBeNull()
    expect(extractDisplayPairingToken('dsifb9b539ad')).toBeNull()
    expect(extractDisplayPairingToken('')).toBeNull()
  })
})
