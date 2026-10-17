import type { InjectionKey } from 'vue'
import type { LayoutPage } from '@/api/displayLayout'

/** Seiten des Layout-Dokuments (für eingebettete Layouts); der Renderer stellt sie bereit. */
export const LAYOUT_PAGES_KEY: InjectionKey<() => LayoutPage[]> = Symbol('displayLayoutPages')

/** Schutz gegen unerwartet tiefe Verschachtelung beim Rendern (das Backend begrenzt bereits). */
export const MAX_RENDER_DEPTH = 10

/** Rotationsdauer eines Schrittes in Sekunden. */
export function stepSeconds(rotation: { interval_sec: number; steps: { duration_sec: number | null }[] }, index: number): number {
  return rotation.steps[index]?.duration_sec ?? rotation.interval_sec
}

/** Im Editor eingefroren: Rotationen zeigen den ersten Schritt und wechseln nicht. */
export const LAYOUT_FREEZE_KEY: InjectionKey<boolean> = Symbol('displayLayoutFreeze')
