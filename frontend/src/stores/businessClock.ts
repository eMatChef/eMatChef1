import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  getDepartmentClock,
  resetDepartmentClock,
  setDepartmentClock,
  type DepartmentClock,
} from '@/api/departmentClock'

/**
 * Fachzeit des aktiven Departments (Quelle: Backend-BusinessClock).
 * `now` ist naive Wandzeit (wie alle Grossanlass-Zeiten) und wird ohne Zeitzonen-Umrechnung angezeigt, damit
 * Header und Backend-Vergleiche dieselbe Zeit meinen. Die Uhr läuft lokal ab dem Abruf weiter; `revision` steigt
 * nach jeder Zeitreise, damit Ansichten über ihr normales Laden neu aufgebaut werden.
 */
const pad = (n: number) => String(n).padStart(2, '0')

/** `YYYY-MM-DDTHH:mm:ss` aus den lokalen Feldern (keine Zeitzonen-Umrechnung). */
export function formatWallClock(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

/** Naive Wandzeit des Servers als lokale Felder lesen. */
export function parseWallClock(value: string): Date {
  const [datePart, timePart = '00:00:00'] = value.split('T')
  const [y, mo, d] = datePart.split('-').map(Number)
  const [h, mi, s] = timePart.split(':').map(Number)
  return new Date(y, mo - 1, d, h, mi, s || 0)
}

export const useBusinessClockStore = defineStore('businessClock', () => {
  const departmentId = ref<string | null>(null)
  const clock = ref<DepartmentClock | null>(null)
  const tick = ref(Date.now())
  const fetchedAt = ref(Date.now())
  const revision = ref(0)
  let timer: ReturnType<typeof setInterval> | null = null

  const canTravel = computed(() => clock.value?.can_travel === true)
  const mode = computed(() => clock.value?.mode ?? 'real')
  const presets = computed(() => clock.value?.presets ?? [])
  /** Angezeigte Fachzeit (Wandzeit-Felder als lokale Felder), läuft sichtbar weiter. */
  const displayNow = computed(() => {
    const base = clock.value ? parseWallClock(clock.value.now) : new Date(tick.value)
    return new Date(base.getTime() + (clock.value ? tick.value - fetchedAt.value : 0))
  })

  function startTicking() {
    if (timer) return
    timer = setInterval(() => {
      tick.value = Date.now()
    }, 1000)
  }

  function stopTicking() {
    if (timer) clearInterval(timer)
    timer = null
  }

  function apply(next: DepartmentClock) {
    clock.value = next
    fetchedAt.value = tick.value = Date.now()
  }

  async function load(id: string | null) {
    departmentId.value = id
    if (!id) {
      clock.value = null
      stopTicking()
      return
    }
    try {
      const next = await getDepartmentClock(id)
      if (departmentId.value !== id) return
      apply(next)
      if (next.can_travel) startTicking()
      else stopTicking()
    } catch {
      // Kein Zugriff o. Ä.: keine Uhr anzeigen.
      if (departmentId.value === id) clock.value = null
      stopTicking()
    }
  }

  /** Setzt die Fachzeit auf eine Wandzeit `YYYY-MM-DDTHH:mm[:ss]` (ohne Zeitzonen- oder Sommerzeit-Umrechnung). */
  async function travelToWall(wall: string) {
    if (!departmentId.value) return
    const value = /T\d{2}:\d{2}$/.test(wall) ? `${wall}:00` : wall
    apply(await setDepartmentClock(departmentId.value, value))
    revision.value++
  }

  async function travelTo(target: Date) {
    await travelToWall(formatWallClock(target))
  }

  /**
   * Relativ springen, gerechnet auf der Wandzeit: ein Tag bleibt auch über die Sommerzeitumstellung derselbe Uhrzeit-Wert
   * (UTC-Arithmetik auf den Wandzeit-Feldern statt Millisekunden der Browser-Zeitzone).
   */
  async function shift(deltaMs: number) {
    const [datePart, timePart] = formatWallClock(displayNow.value).split('T')
    const [y, mo, d] = datePart.split('-').map(Number)
    const [h, mi, sec] = timePart.split(':').map(Number)
    const moved = new Date(Date.UTC(y, mo - 1, d, h, mi, sec) + deltaMs)
    const p = (n: number) => String(n).padStart(2, '0')
    await travelToWall(`${moved.getUTCFullYear()}-${p(moved.getUTCMonth() + 1)}-${p(moved.getUTCDate())}T${p(moved.getUTCHours())}:${p(moved.getUTCMinutes())}:${p(moved.getUTCSeconds())}`)
  }

  async function reset() {
    if (!departmentId.value) return
    apply(await resetDepartmentClock(departmentId.value))
    revision.value++
  }

  return { clock, canTravel, mode, presets, displayNow, revision, load, travelTo, travelToWall, shift, reset, stopTicking }
})
