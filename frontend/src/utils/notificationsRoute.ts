/**
 * Kanonischer Pfad der Nachrichtenzentrale: Grossanlass unter `/ga/notifications`,
 * jedes andere Department unter `/dept/notifications` (dieselbe View, dieselben API-Endpunkte).
 */
export function notificationsCenterPath(departmentId: string, isGrossanlass: boolean): string {
  return `/${departmentId}/${isGrossanlass ? 'ga' : 'dept'}/notifications`
}
