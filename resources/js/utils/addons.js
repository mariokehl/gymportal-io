import { getDisplayTimezone, todayInDisplayTimezone } from '@/utils/formatters'

/**
 * Calendar date of a timestamp, read in the display timezone so the date never
 * shifts for visitors in another timezone.
 *
 * @param {string|Date} value
 * @returns {{year: number, month: number, day: number}|null} month is 1-based
 */
function calendarDateOf(value) {
  if (!value) {
    return null
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return null
  }

  // en-CA renders as YYYY-MM-DD, which is safe to split.
  const [year, month, day] = date
    .toLocaleDateString('en-CA', { timeZone: getDisplayTimezone() })
    .split('-')
    .map(Number)

  return { year, month, day }
}

/**
 * Number of days in a 1-based month.
 */
function daysInMonth(year, month) {
  // Day 0 of the next month is the last day of this one.
  return new Date(Date.UTC(year, month, 0)).getUTCDate()
}

function toIsoDate(year, month, day) {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

/**
 * Next monthly billing date on or after `from`, anchored to the membership's
 * start date — the due date a recurring add-on shares with the membership fee.
 *
 * Recurring charges are anchored to the membership start date and advanced by
 * the billing cycle, so they only land on the 1st when the contract itself
 * started on the 1st (e.g. because the gym runs "Verträge immer zum 1. des
 * Monats starten"). Shorter months clamp to the last day, so a contract that
 * started on the 31st bills on the 28th/30th where needed.
 *
 * @param {string|Date} startDate membership start date
 * @param {string|Date} from date to search from (defaults to today)
 * @returns {string|null} YYYY-MM-DD, or null for a missing/invalid date
 */
export function nextMonthlyBillingDate(startDate, from = new Date()) {
  const start = calendarDateOf(startDate)
  const reference = calendarDateOf(from)

  if (!start || !reference) {
    return null
  }

  // Start at the billing anchor in the reference month, then step forward until
  // the date is strictly after the reference date.
  let { year, month } = reference

  for (let i = 0; i < 2; i += 1) {
    const day = Math.min(start.day, daysInMonth(year, month))
    const candidate = toIsoDate(year, month, day)

    if (candidate > toIsoDate(reference.year, reference.month, reference.day)) {
      return candidate
    }

    month += 1

    if (month > 12) {
      month = 1
      year += 1
    }
  }

  return toIsoDate(year, month, Math.min(start.day, daysInMonth(year, month)))
}

/**
 * Last day of the month the add-on was booked in — the end of a
 * "rest of the month free" trial.
 *
 * @param {string|Date} bookedAt
 * @returns {string|null} YYYY-MM-DD, or null for a missing/invalid date
 */
export function endOfBookingMonth(bookedAt) {
  const booked = calendarDateOf(bookedAt)

  if (!booked) {
    return null
  }

  return toIsoDate(booked.year, booked.month, daysInMonth(booked.year, booked.month))
}

/**
 * Whether a "rest of the month free" trial is still running.
 *
 * @param {string|null} trialEndsAt YYYY-MM-DD
 * @returns {boolean}
 */
export function isTrialActive(trialEndsAt) {
  return trialEndsAt !== null
    && trialEndsAt !== undefined
    && trialEndsAt >= todayInDisplayTimezone()
}

/**
 * Weeks per month (12 months / 52 weeks), used to derive the weekly price.
 * Kept in sync with Addon::WEEKS_PER_MONTH.
 */
export const WEEKS_PER_MONTH = 52 / 12

/**
 * Monthly price converted to a weekly comparison figure. This is never charged
 * — the monthly price stays the amount billed.
 *
 * @param {number} monthlyPrice
 * @returns {number} the weekly equivalent, rounded to cents
 */
export function weeklyPriceOf(monthlyPrice) {
  const price = Number(monthlyPrice)

  if (!Number.isFinite(price)) {
    return 0
  }

  return Math.round((price / WEEKS_PER_MONTH) * 100) / 100
}

/**
 * Determine which add-on ids should be submitted for a chosen plan.
 *
 * Included add-ons are preselected and not deselectable, so they are always
 * part of the result. Optional add-ons are only included when the customer
 * checked them. This mirrors the checkbox state collected from the widget DOM
 * and is kept here as a pure function so it can be unit-tested.
 *
 * @param {Array<{id: number, mode: 'included'|'optional', checked?: boolean}>} addons
 * @returns {number[]} the selected add-on ids
 */
export function resolveSelectedAddonIds(addons) {
  if (!Array.isArray(addons)) {
    return []
  }

  return addons
    .filter((addon) => addon.mode === 'included' || addon.checked === true)
    .map((addon) => addon.id)
}
