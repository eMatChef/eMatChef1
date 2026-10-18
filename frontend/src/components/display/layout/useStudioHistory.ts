import { computed, ref } from 'vue'

/**
 * Undo/Redo für Layoutänderungen im Studio. Vor jeder Änderung wird ein Schnappschuss des ganzen Dokuments abgelegt
 * (Layouts sind klein und begrenzt); Wiederherstellen ersetzt das Dokument vollständig.
 */
export function useStudioHistory<T>(get: () => T | null, set: (value: T) => void, limit = 100) {
  const undoStack = ref<string[]>([])
  const redoStack = ref<string[]>([])

  const snapshot = (): string | null => {
    const value = get()
    return value === null ? null : JSON.stringify(value)
  }

  function checkpoint(): void {
    const s = snapshot()
    if (s === null) return
    if (undoStack.value[undoStack.value.length - 1] === s) return
    undoStack.value.push(s)
    if (undoStack.value.length > limit) undoStack.value.shift()
    redoStack.value = []
  }

  /** Verwirft den letzten Schnappschuss, wenn die Aktion nichts verändert hat (z. B. abgebrochenes Ziehen). */
  function discardIfUnchanged(): void {
    const s = snapshot()
    if (s !== null && undoStack.value[undoStack.value.length - 1] === s) undoStack.value.pop()
  }

  function undo(): boolean {
    const previous = undoStack.value.pop()
    const current = snapshot()
    if (previous === undefined || current === null) return false
    redoStack.value.push(current)
    set(JSON.parse(previous) as T)
    return true
  }

  function redo(): boolean {
    const next = redoStack.value.pop()
    const current = snapshot()
    if (next === undefined || current === null) return false
    undoStack.value.push(current)
    set(JSON.parse(next) as T)
    return true
  }

  function reset(): void {
    undoStack.value = []
    redoStack.value = []
  }

  return {
    checkpoint,
    discardIfUnchanged,
    undo,
    redo,
    reset,
    canUndo: computed(() => undoStack.value.length > 0),
    canRedo: computed(() => redoStack.value.length > 0),
  }
}
