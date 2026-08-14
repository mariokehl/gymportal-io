import { formatDate, formatDateForInput, todayInDisplayTimezone } from '@/utils/formatters'

/**
 * Months a new device's token expiry is pre-filled with.
 * Mirrors App\Models\GymScanner::DEFAULT_TOKEN_LIFETIME_MONTHS.
 */
export const DEFAULT_TOKEN_LIFETIME_MONTHS = 12

/**
 * Days before expiry at which the device list starts warning.
 *
 * Long enough that a gym can schedule the swap into normal opening hours
 * instead of discovering it when the door stays shut.
 */
export const EXPIRY_WARNING_DAYS = 30

/**
 * The expiry a newly created device is suggested, as a date input value.
 *
 * Clamps to the last day of the target month, so creating a device on the
 * 31st does not roll the suggestion into the following month.
 */
export const defaultTokenExpiry = (from = new Date()) => {
  const target = new Date(from.getFullYear(), from.getMonth(), 1)
  target.setMonth(target.getMonth() + DEFAULT_TOKEN_LIFETIME_MONTHS)

  const lastDayOfTargetMonth = new Date(
    target.getFullYear(),
    target.getMonth() + 1,
    0
  ).getDate()

  target.setDate(Math.min(from.getDate(), lastDayOfTargetMonth))

  return formatDateForInput(target)
}

/**
 * Whole days until the token expires. Negative once it has expired, null when
 * the device has no expiry at all.
 *
 * Compares calendar dates in the display timezone rather than timestamps: a
 * token expiring later today has 0 days left, not "half a day", and must not
 * read as already expired.
 */
export const daysUntilExpiry = (expiresAt, today = todayInDisplayTimezone()) => {
  const expiry = formatDateForInput(expiresAt)

  if (!expiry) {
    return null
  }

  // Both are YYYY-MM-DD, so UTC parsing is safe and offset-free here.
  const diff = Date.parse(`${expiry}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)

  return Number.isNaN(diff) ? null : Math.round(diff / 86400000)
}

/**
 * How the device list should treat the token: 'valid' needs no attention,
 * 'expiring' is close enough to warn about, 'expired' already locks members
 * out. Devices without an expiry are always 'valid'.
 */
export const tokenExpiryState = (expiresAt, today = todayInDisplayTimezone()) => {
  const days = daysUntilExpiry(expiresAt, today)

  if (days === null) {
    return 'valid'
  }

  if (days < 0) {
    return 'expired'
  }

  return days <= EXPIRY_WARNING_DAYS ? 'expiring' : 'valid'
}

/**
 * The warning shown next to the device status.
 *
 * Kept to a single line: the tooltip renders it without wrapping, like the
 * outstanding-balance hint on the member list.
 */
export const tokenExpiryWarning = (expiresAt, today = todayInDisplayTimezone()) => {
  const state = tokenExpiryState(expiresAt, today)

  if (state === 'valid') {
    return ''
  }

  const date = formatDate(expiresAt)

  if (state === 'expired') {
    return `Token abgelaufen am ${date}`
  }

  const days = daysUntilExpiry(expiresAt, today)

  if (days === 0) {
    return `Token läuft heute ab (${date})`
  }

  return `Token läuft in ${days} ${days === 1 ? 'Tag' : 'Tagen'} ab (${date})`
}
