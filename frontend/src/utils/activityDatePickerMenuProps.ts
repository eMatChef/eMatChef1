/** menu-props.contentClass für VDateInput — Kalender-Styling in activity-datetime-field.css */
export const ACTIVITY_DATE_PICKER_MENU_CLASS = 'activity-date-picker-menu'

export const activityDatePickerMenuProps = {
  attach: 'body' as const,
  contentClass: ACTIVITY_DATE_PICKER_MENU_CLASS,
  zIndex: 10100,
  /** Desktop-Dropdown: max. Höhe; Mobile nutzt Bottom Sheet */
  maxHeight: 'min(90dvh, calc(100vh - 24px))',
  maxWidth: 'min(calc(100vw - 24px), 720px)',
}
