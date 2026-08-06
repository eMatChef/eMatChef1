import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import {
  formatReturnCrateLineMeta,
  returnCrateLineMissingQty,
  returnCrateLineSurplusQty,
  returnCrateLineInputCap,
} from '@/utils/materialJourneyReturnCrateLineMeta'

const i18n = createI18n({
  legacy: false,
  locale: 'de',
  messages: {
    de: {
      activities: {
        packList: {
          returnCrateModalMetaOrdered: 'bestellt {n}',
          returnCrateModalMetaConsumed: 'verbrauch {n}',
          returnCrateModalMetaRepair: 'reparatur {n}',
          returnCrateModalMetaLoss: 'verlust {n}',
        },
      },
    },
  },
})

describe('materialJourneyReturnCrateLineMeta', () => {
  it('blendet 0-Werte aus, behält bestellt und verbrauch', () => {
    const meta = formatReturnCrateLineMeta(
      { ordered: 10, consumed: 5, loss: 0, repair: 0 },
      i18n.global.t,
    )
    expect(meta).toBe('bestellt 10 · verbrauch 5')
  })

  it('zeigt reparatur und verlust nur wenn > 0', () => {
    const meta = formatReturnCrateLineMeta(
      { ordered: 10, consumed: 2, loss: 1, repair: 3 },
      i18n.global.t,
    )
    expect(meta).toBe('bestellt 10 · verbrauch 2 · reparatur 3 · verlust 1')
  })

  it('missing qty nur bei included', () => {
    expect(returnCrateLineMissingQty(true, 5, 3)).toBe(2)
    expect(returnCrateLineMissingQty(false, 5, 3)).toBe(0)
    expect(returnCrateLineMissingQty(true, 5, 5)).toBe(0)
  })

  it('surplus qty nur bei included und qty > max', () => {
    expect(returnCrateLineSurplusQty(true, 5, 7)).toBe(2)
    expect(returnCrateLineSurplusQty(false, 5, 7)).toBe(0)
    expect(returnCrateLineSurplusQty(true, 5, 5)).toBe(0)
    expect(returnCrateLineSurplusQty(true, 5, 3)).toBe(0)
  })

  it('input cap erlaubt Übermenge über max', () => {
    expect(returnCrateLineInputCap(10, 5)).toBeGreaterThanOrEqual(7)
    expect(returnCrateLineInputCap(10, 5)).toBe(Math.max(20, 60, 55))
  })
})
