/**
 * Zeitliche Auswertung des Helferbedarfs von Aufgaben (Spiegel von
 * GrossanlassBauprojektService::helperDemandOverTime im Backend).
 * Zeitlich getrennte Aufgaben werden nicht addiert: der Spitzenbedarf ist das Maximum der gleichzeitig laufenden.
 * Aufgaben ohne Zeitblock gelten als ganztägig und zählen immer mit.
 */
export type HelperDemandTask = { startMs: number | null; endMs: number | null; total: number }

export type HelperDemand = { peak: number; undated: number }

export function helperDemandPeak(tasks: HelperDemandTask[]): HelperDemand {
  let undated = 0
  const events: Array<[number, number]> = []
  for (const task of tasks) {
    if (task.total < 1) continue
    if (task.startMs == null || task.endMs == null || task.endMs <= task.startMs) {
      undated += task.total
      continue
    }
    events.push([task.startMs, task.total], [task.endMs, -task.total])
  }
  // Ende vor Beginn bei gleichem Zeitpunkt: angrenzende Aufgaben überlappen nicht.
  events.sort((a, b) => a[0] - b[0] || a[1] - b[1])
  let current = 0
  let peak = 0
  for (const [, delta] of events) {
    current += delta
    peak = Math.max(peak, current)
  }
  return { peak: peak + undated, undated }
}
