import type { InjectionKey } from 'vue'
import type { DisplayMapData, GaLiveData, LayoutPage } from '@/api/displayLayout'

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

/** Kartendaten des Infoscreens für das Widget «ga_site_map» (vom Gerät bzw. der Vorschau geladen, Renderer nur Anzeige). */
export const LAYOUT_MAP_DATA_KEY: InjectionKey<() => DisplayMapData | null> = Symbol('displayLayoutMapData')

/** Datenmodus der Darstellung: «demo» = Beispieldaten (Editor-Standard, ausdrücklich veröffentlichter Demomodus), «live» = echte GA-Daten. */
export type LayoutDataMode = 'demo' | 'live'
export const LAYOUT_DATA_MODE_KEY: InjectionKey<() => LayoutDataMode> = Symbol('displayLayoutDataMode')

/** Live-Daten der GA-Fachwidgets; null = (noch) nicht geladen. Im Demomodus unbenutzt. */
export const LAYOUT_LIVE_DATA_KEY: InjectionKey<() => GaLiveData | null> = Symbol('displayLayoutLiveData')
