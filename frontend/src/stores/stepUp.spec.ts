import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useStepUpStore } from './stepUp'

describe('step-up store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shares one dialog between parallel requests and resolves them together', async () => {
    const store = useStepUpStore()

    const a = store.request()
    const b = store.request()
    expect(store.isOpen).toBe(true)

    store.finish(true)

    expect(await a).toBe(true)
    expect(await b).toBe(true)
    expect(store.isOpen).toBe(false)
  })

  it('opens a fresh dialog for a later request and reports cancellation', async () => {
    const store = useStepUpStore()
    const first = store.request()
    store.finish(false)
    expect(await first).toBe(false)

    const second = store.request()
    expect(store.isOpen).toBe(true)
    store.finish(true)
    expect(await second).toBe(true)
  })
})
