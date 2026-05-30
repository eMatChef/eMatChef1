import { reactive } from 'vue'

/**
 * Hält pro Formularfeld den letzten DB-Stand für AutoSaveField `:baseline`.
 * Nach Laden oder Speichern `syncBaselines()` aufrufen.
 */
export function useFormFieldBaselines<T extends Record<string, unknown>>(source: T) {
  const baselines = reactive(cloneFormSnapshot(source)) as T

  function cloneFormSnapshot(form: T): T {
    return JSON.parse(JSON.stringify(form)) as T
  }

  function syncBaselines(from?: T) {
    Object.assign(baselines, cloneFormSnapshot(from ?? source))
  }

  function baselineFor<K extends keyof T>(key: K): T[K] {
    return baselines[key]
  }

  function syncBaselineFor<K extends keyof T>(key: K) {
    baselines[key] = JSON.parse(JSON.stringify(source[key])) as T[K]
  }

  return {
    baselines,
    syncBaselines,
    syncBaselineFor,
    baselineFor,
  }
}
