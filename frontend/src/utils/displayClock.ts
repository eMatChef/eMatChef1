import { reactive } from 'vue'
import type { DisplayClockPayload } from '@/api/displayLayout'
import { parseWallClock } from '@/stores/businessClock'

/**
 * Fachzeit des Infoscreens. Hat das Department des Bildschirms den Demomodus, liefert das Backend dessen
 * BusinessClock-Zeit (naive Wandzeit, wie das GA-Control sie zeigt); alle Uhren und zeitabhängigen Widgets der Anzeige
 * lesen sie über `displayNow()`. Sonst (normales Department) gilt unverändert die echte Zeit des Geräts.
 * Der Versatz zur Gerätezeit wird bei jedem Abruf neu bestimmt, die Uhr läuft dazwischen lokal weiter.
 */
const JUMP_TOLERANCE_MS = 5_000

const state = reactive({ demo: false, offsetMs: 0, revision: 0 })

/** Übernimmt die Uhr aus einer Server-Antwort; liefert `true`, wenn die Fachzeit seit dem letzten Abruf gesprungen ist. */
export function applyDisplayClock(clock: DisplayClockPayload | null | undefined, receivedAt: number = Date.now()): boolean {
  if (!clock || clock.mode !== 'demo' || !clock.now) {
    const wasDemo = state.demo
    state.demo = false
    state.offsetMs = 0
    if (wasDemo) state.revision++
    return wasDemo
  }
  const offsetMs = parseWallClock(clock.now).getTime() - receivedAt
  const jumped = !state.demo || Math.abs(offsetMs - state.offsetMs) > JUMP_TOLERANCE_MS
  state.demo = true
  if (jumped) {
    state.offsetMs = offsetMs
    state.revision++
  }
  return jumped
}

export function resetDisplayClock(): void {
  applyDisplayClock(null)
}

/** Aktuelle Fachzeit des Bildschirms (Wandzeit-Felder als lokale Felder), ohne Demo die echte Zeit. */
export function displayNow(): Date {
  return new Date(Date.now() + state.offsetMs)
}

export function isDisplayClockDemo(): boolean {
  return state.demo
}

/** Steigt nach jedem Zeitsprung; zum Beobachten in Ansichten. */
export function displayClockRevision(): number {
  return state.revision
}
