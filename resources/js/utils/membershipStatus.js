/**
 * Statuses a membership can have, with their label and badge styling.
 */
export const MEMBERSHIP_STATUSES = [
  { value: 'active', label: 'Aktiv', dotClass: 'bg-success-solid', badgeClass: 'bg-success-soft text-success-strong' },
  { value: 'paused', label: 'Pausiert', dotClass: 'bg-warning-solid', badgeClass: 'bg-warning-soft text-warning-strong' },
  { value: 'cancelled', label: 'Gekündigt', dotClass: 'bg-danger-solid', badgeClass: 'bg-danger-soft text-danger-strong' },
  { value: 'expired', label: 'Abgelaufen', dotClass: 'bg-gray-500', badgeClass: 'bg-fill text-ink' },
  { value: 'pending', label: 'Ausstehend', dotClass: 'bg-orange-500', badgeClass: 'bg-orange-100 text-orange-800' },
  { value: 'withdrawn', label: 'Widerrufen', dotClass: 'bg-purple-500', badgeClass: 'bg-purple-100 text-purple-800' },
]

/**
 * Label and badge styling of a membership status. Unknown statuses fall back
 * to the raw value on a neutral badge.
 *
 * @param {string} status
 * @returns {{value: string, label: string, dotClass: string, badgeClass: string}}
 */
export function membershipStatusOption(status) {
  return MEMBERSHIP_STATUSES.find((option) => option.value === status)
    || { value: status, label: status, dotClass: 'bg-gray-500', badgeClass: 'bg-fill text-ink' }
}
