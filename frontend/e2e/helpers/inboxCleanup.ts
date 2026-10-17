import { execSync } from 'node:child_process'

/**
 * Fertigmeldung/Abnahme erzeugen Direktnachrichten («E2E …») im Posteingang; dafür gibt es keine API zum Löschen.
 * Ist `E2E_CLEANUP_INBOX_CMD` gesetzt (z. B. ein `docker compose exec -T db psql … -c "DELETE FROM inbox_message WHERE subject LIKE '%E2E %'"`),
 * wird der Befehl nach dem Test ausgeführt. Ohne die Variable bleibt die Bereinigung manuell (siehe docs/E2E.md).
 */
export function cleanupE2eInbox(): void {
  const command = process.env.E2E_CLEANUP_INBOX_CMD
  if (!command) return
  try {
    execSync(command, { stdio: 'ignore', timeout: 30_000 })
  } catch (error) {
    console.warn(`E2E-Posteingang konnte nicht bereinigt werden: ${(error as Error).message}`)
  }
}
