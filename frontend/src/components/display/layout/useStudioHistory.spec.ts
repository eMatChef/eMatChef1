import { describe, expect, it } from 'vitest'
import { useStudioHistory } from './useStudioHistory'

describe('useStudioHistory', () => {
  function setup() {
    const state = { value: { n: 1 } }
    const history = useStudioHistory(
      () => state.value,
      (v) => {
        state.value = v
      },
    )
    return { state, history }
  }

  it('macht Änderungen rückgängig und stellt sie wieder her', () => {
    const { state, history } = setup()
    history.checkpoint()
    state.value.n = 2
    history.checkpoint()
    state.value.n = 3
    expect(history.undo()).toBe(true)
    expect(state.value.n).toBe(2)
    expect(history.undo()).toBe(true)
    expect(state.value.n).toBe(1)
    expect(history.undo()).toBe(false)
    expect(history.redo()).toBe(true)
    expect(state.value.n).toBe(2)
    expect(history.canRedo.value).toBe(true)
  })

  it('verwirft eine neue Änderung die Redo-Liste und unveränderte Schnappschüsse', () => {
    const { state, history } = setup()
    history.checkpoint()
    state.value.n = 2
    history.undo()
    history.checkpoint()
    state.value.n = 5
    expect(history.canRedo.value).toBe(false)
    history.checkpoint()
    history.discardIfUnchanged()
    expect(history.undo()).toBe(true)
    expect(state.value.n).toBe(1)
  })
})
