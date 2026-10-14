/**
 * Statuses a membership can have, with their label and badge styling.
 */
export const MEMBERSHIP_STATUSES = [
  { value: 'active', label: 'Aktiv', dotClass: 'bg-green-500', badgeClass: 'bg-green-100 text-green-800' },
  { value: 'paused', label: 'Pausiert', dotClass: 'bg-yellow-500', badgeClass: 'bg-yellow-100 text-yellow-800' },
  { value: 'cancelled', label: 'Gekündigt', dotClass: 'bg-red-500', badgeClass: 'bg-red-100 text-red-800' },
  { value: 'expired', label: 'Abgelaufen', dotClass: 'bg-gray-500', badgeClass: 'bg-gray-100 text-gray-800' },
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
    || { value: status, label: status, dotClass: 'bg-gray-500', badgeClass: 'bg-gray-100 text-gray-800' }
}
