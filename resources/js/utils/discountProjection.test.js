import { describe, expect, it } from 'vitest'
import {
  DEFAULT_TERM_MONTHS,
  formatCents,
  projectDiscounts,
  toCents,
} from './discountProjection'

/**
 * These cases mirror tests/Unit/Services/DiscountProjectionServiceTest.php
 * one-for-one. The client projection powers the live "Preisverlauf" preview
 * while the PHP service is authoritative for anything persisted, so the two
 * implementations must agree on every expected value below.
 */
describe('projectDiscounts', () => {
  it('projects the two-phase template', () => {
    const result = projectDiscounts({
      price: 49.95,
      commitmentMonths: 12,
      phases: [
        { duration_months: 3, price: 19.95 },
        { duration_months: 9, price: 34.95 },
      ],
    })

    expect(result.termMonths).toBe(12)
    expect(result.regularTotalCents).toBe(59940)
    expect(result.discountedTotalCents).toBe(37440)
    expect(result.savingsCents).toBe(22500)
    expect(formatCents(result.savingsCents)).toBe('225,00 €')
  })

  it('appends a regular price segment for the remainder of the term', () => {
    const result = projectDiscounts({
      price: 50.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 3, price: 1.0 }],
    })

    expect(result.segments).toHaveLength(2)

    const [promo, regular] = result.segments

    expect(promo.isDiscounted).toBe(true)
    expect(promo.startMonth).toBe(1)
    expect(promo.endMonth).toBe(3)
    expect(promo.priceCents).toBe(100)

    expect(regular.isDiscounted).toBe(false)
    expect(regular.startMonth).toBe(4)
    expect(regular.endMonth).toBe(12)
    expect(regular.priceCents).toBe(5000)

    expect(result.discountedTotalCents).toBe(45300)
  })

  it('returns a single regular segment when there are no phases', () => {
    const result = projectDiscounts({ price: 29.9, commitmentMonths: 6, phases: [] })

    expect(result.segments).toHaveLength(1)
    expect(result.segments[0].isDiscounted).toBe(false)
    expect(result.savingsCents).toBe(0)
    expect(result.discountedTotalCents).toBe(result.regularTotalCents)
  })

  it('falls back to a default horizon without a minimum commitment', () => {
    const result = projectDiscounts({ price: 10.0, commitmentMonths: '', phases: [] })

    expect(result.termMonths).toBe(DEFAULT_TERM_MONTHS)
  })

  it('extends the term so phases are never cut off', () => {
    const result = projectDiscounts({
      price: 40.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 18, price: 20.0 }],
    })

    expect(result.termMonths).toBe(18)
    expect(result.segments).toHaveLength(1)
    expect(result.segments[0].durationMonths).toBe(18)
    expect(result.exceedsTerm).toBe(true)
  })

  it('flags phases exceeding the minimum commitment', () => {
    const within = projectDiscounts({
      price: 40.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 12, price: 20.0 }],
    })

    expect(within.exceedsTerm).toBe(false)

    const beyond = projectDiscounts({
      price: 40.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 13, price: 20.0 }],
    })

    expect(beyond.exceedsTerm).toBe(true)
  })

  it('ignores blank and zero-length phase rows', () => {
    const result = projectDiscounts({
      price: 30.0,
      commitmentMonths: 6,
      phases: [
        { duration_months: 0, price: 5.0 },
        { duration_months: 2, price: 10.0 },
        { duration_months: '', price: '' },
      ],
    })

    const discounted = result.segments.filter((segment) => segment.isDiscounted)

    expect(discounted).toHaveLength(1)
    expect(discounted[0].durationMonths).toBe(2)
  })

  it('treats a free phase as zero rather than falling back to the regular price', () => {
    const result = projectDiscounts({
      price: 45.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 3, price: 0 }],
    })

    expect(result.segments[0].priceCents).toBe(0)
    expect(result.discountedTotalCents).toBe(40500)
    expect(result.savingsCents).toBe(13500)
  })

  it('never reports negative savings when a phase costs more', () => {
    const result = projectDiscounts({
      price: 20.0,
      commitmentMonths: 12,
      phases: [{ duration_months: 3, price: 30.0 }],
    })

    expect(result.savingsCents).toBe(0)
  })

  it('accumulates long terms without rounding error', () => {
    const result = projectDiscounts({ price: 0.1, commitmentMonths: 240, phases: [] })

    expect(result.regularTotalCents).toBe(2400)
    expect(formatCents(result.regularTotalCents)).toBe('24,00 €')
  })
})

describe('toCents', () => {
  it('parses German decimal input without float drift', () => {
    expect(toCents('19,95')).toBe(1995)
    expect(toCents('19.95')).toBe(1995)
    expect(toCents('1.234,50')).toBe(123450)
    expect(toCents('1,234.50')).toBe(123450)
    expect(toCents('10')).toBe(1000)
    expect(toCents('')).toBe(0)
    expect(toCents(null)).toBe(0)
    expect(toCents(undefined)).toBe(0)
    expect(toCents('19,95 €')).toBe(1995)
  })
})

describe('formatCents', () => {
  it('formats cents in German notation', () => {
    expect(formatCents(0)).toBe('0,00 €')
    expect(formatCents(1995)).toBe('19,95 €')
    expect(formatCents(123450)).toBe('1.234,50 €')
  })
})
