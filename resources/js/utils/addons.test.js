import { describe, it, expect, afterEach, vi } from 'vitest'
import {
  cappedBillingDate, endOfBookingMonth, isTrialActive, nextMonthlyBillingDate, resolveSelectedAddonIds,
  weeklyPriceOf
} from '@/utils/addons'

describe('nextMonthlyBillingDate', () => {
  it('bills on the 1st when the contract started on the 1st', () => {
    // "Verträge immer zum 1. des Monats starten" is enabled.
    expect(nextMonthlyBillingDate('2026-07-01', '2026-07-15')).toBe('2026-08-01')
  })

  it('keeps the contract day-of-month when the contract did not start on the 1st', () => {
    // The setting is off, so billing is anchored to the start date.
    expect(nextMonthlyBillingDate('2026-07-15', '2026-07-20')).toBe('2026-08-15')
  })

  it('returns the anchor day later in the same month when it is still ahead', () => {
    expect(nextMonthlyBillingDate('2026-07-15', '2026-08-03')).toBe('2026-08-15')
  })

  it('rolls over into the next year in December', () => {
    expect(nextMonthlyBillingDate('2026-03-20', '2026-12-25')).toBe('2027-01-20')
  })

  it('clamps to the last day of a shorter month', () => {
    // A contract started on the 31st bills on the 28th in February.
    expect(nextMonthlyBillingDate('2026-01-31', '2026-01-31')).toBe('2026-02-28')
    expect(nextMonthlyBillingDate('2026-01-31', '2026-03-31')).toBe('2026-04-30')
  })

  it('uses the display timezone, not the browser timezone', () => {
    // 23:30 UTC on the 31st is already the 1st of August in Europe/Berlin.
    expect(nextMonthlyBillingDate('2026-07-15', '2026-07-31T23:30:00Z')).toBe('2026-08-15')
  })

  it('returns null for a missing or invalid date', () => {
    expect(nextMonthlyBillingDate(null)).toBeNull()
    expect(nextMonthlyBillingDate(undefined)).toBeNull()
    expect(nextMonthlyBillingDate('not-a-date')).toBeNull()
  })
})

describe('cappedBillingDate', () => {
  it('drops a date past the end of the contract', () => {
    expect(cappedBillingDate('2026-09-01', '2026-08-31')).toBeNull()
  })

  it('keeps a date on the last contract day', () => {
    expect(cappedBillingDate('2026-08-31', '2026-08-31')).toBe('2026-08-31')
  })

  it('keeps the date when the contract has no end', () => {
    expect(cappedBillingDate('2026-09-01', null)).toBe('2026-09-01')
  })

  it('passes a missing date through', () => {
    expect(cappedBillingDate(null, '2026-08-31')).toBeNull()
  })
})

describe('endOfBookingMonth', () => {
  it('returns the last day of a 31-day month', () => {
    expect(endOfBookingMonth('2026-07-15T10:00:00Z')).toBe('2026-07-31')
  })

  it('returns the last day of a 30-day month', () => {
    expect(endOfBookingMonth('2026-04-02T10:00:00Z')).toBe('2026-04-30')
  })

  it('handles February in a leap year', () => {
    expect(endOfBookingMonth('2028-02-10T10:00:00Z')).toBe('2028-02-29')
  })

  it('handles February in a non-leap year', () => {
    expect(endOfBookingMonth('2026-02-10T10:00:00Z')).toBe('2026-02-28')
  })

  it('returns null for a missing or invalid date', () => {
    expect(endOfBookingMonth(null)).toBeNull()
    expect(endOfBookingMonth('not-a-date')).toBeNull()
  })
})

describe('isTrialActive', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  const freeze = (iso) => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(iso))
  }

  it('is active before the trial end date', () => {
    freeze('2026-07-20T12:00:00Z')
    expect(isTrialActive('2026-07-31')).toBe(true)
  })

  it('is still active on the last day of the trial', () => {
    freeze('2026-07-31T12:00:00Z')
    expect(isTrialActive('2026-07-31')).toBe(true)
  })

  it('is over the day after the trial ends', () => {
    freeze('2026-08-01T12:00:00Z')
    expect(isTrialActive('2026-07-31')).toBe(false)
  })

  it('is inactive without a trial', () => {
    expect(isTrialActive(null)).toBe(false)
    expect(isTrialActive(undefined)).toBe(false)
  })
})

describe('resolveSelectedAddonIds', () => {
  it('always includes included add-ons regardless of checked state', () => {
    const result = resolveSelectedAddonIds([
      { id: 1, mode: 'included', checked: true },
      { id: 2, mode: 'included', checked: false },
    ])
    expect(result).toEqual([1, 2])
  })

  it('includes optional add-ons only when checked', () => {
    const result = resolveSelectedAddonIds([
      { id: 1, mode: 'optional', checked: true },
      { id: 2, mode: 'optional', checked: false },
    ])
    expect(result).toEqual([1])
  })

  it('combines included and selected optional add-ons', () => {
    const result = resolveSelectedAddonIds([
      { id: 10, mode: 'included', checked: true },
      { id: 20, mode: 'optional', checked: true },
      { id: 30, mode: 'optional', checked: false },
    ])
    expect(result).toEqual([10, 20])
  })

  it('returns an empty array for no add-ons or invalid input', () => {
    expect(resolveSelectedAddonIds([])).toEqual([])
    expect(resolveSelectedAddonIds(null)).toEqual([])
    expect(resolveSelectedAddonIds(undefined)).toEqual([])
  })
})

describe('weeklyPriceOf', () => {
  it('converts a monthly price to its weekly equivalent', () => {
    // 8.62 × 12 ÷ 52 = 1.9892… → 1.99
    expect(weeklyPriceOf(8.62)).toBe(1.99)
    expect(weeklyPriceOf(12)).toBe(2.77)
  })

  it('matches the monthly price when it is zero', () => {
    expect(weeklyPriceOf(0)).toBe(0)
  })

  it('returns 0 for invalid input', () => {
    expect(weeklyPriceOf(null)).toBe(0)
    expect(weeklyPriceOf(undefined)).toBe(0)
    expect(weeklyPriceOf('abc')).toBe(0)
  })
})
