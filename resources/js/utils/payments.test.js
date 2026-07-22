import { describe, it, expect } from 'vitest'
import {
  getCreditRedemption, hasCreditRedemption, isCreditTopup, creditTopupSource, getMemberInitials
} from '@/utils/payments'

describe('getCreditRedemption', () => {
  it('returns null when no credit was redeemed', () => {
    expect(getCreditRedemption({ metadata: {} })).toBeNull()
    expect(getCreditRedemption({})).toBeNull()
    expect(getCreditRedemption(null)).toBeNull()
  })

  it('derives the breakdown from the payment metadata', () => {
    const redemption = getCreditRedemption({
      metadata: {
        credit_redeemed: true,
        credit_redeemed_cents: 200,
        original_amount_cents: 500,
        credit_balance_after_cents: 300,
        credit_ledger_id: 42,
      },
    })

    expect(redemption).toEqual({
      evId: '#42',
      redeemedCents: 200,
      remainingCents: 300,
      balanceAfterCents: 300,
    })
  })

  it('never reports a negative remaining amount', () => {
    const redemption = getCreditRedemption({
      metadata: {
        credit_redeemed: true,
        credit_redeemed_cents: 800,
        original_amount_cents: 500,
      },
    })

    expect(redemption.remainingCents).toBe(0)
  })

  it('falls back to a dash when the ledger id is missing', () => {
    const redemption = getCreditRedemption({
      metadata: { credit_redeemed: true, credit_redeemed_cents: 100 },
    })

    expect(redemption.evId).toBe('—')
  })
})

describe('hasCreditRedemption', () => {
  it('reflects whether a redemption could be derived', () => {
    expect(hasCreditRedemption({ metadata: { credit_redeemed: true, credit_redeemed_cents: 100 } })).toBe(true)
    expect(hasCreditRedemption({ metadata: {} })).toBe(false)
  })
})

describe('isCreditTopup', () => {
  it('only matches an explicit top-up flag', () => {
    expect(isCreditTopup({ is_credit_topup: true })).toBe(true)
    expect(isCreditTopup({ is_credit_topup: false })).toBe(false)
    expect(isCreditTopup({})).toBe(false)
    expect(isCreditTopup(null)).toBe(false)
  })
})

describe('creditTopupSource', () => {
  it('appends the staff name when it is known', () => {
    expect(creditTopupSource({ metadata: { created_by_name: 'Mia' } })).toBe('manuell erfasst · Mia')
  })

  it('falls back to the plain label without a name', () => {
    expect(creditTopupSource({ metadata: {} })).toBe('manuell erfasst')
    expect(creditTopupSource(null)).toBe('manuell erfasst')
  })
})

describe('getMemberInitials', () => {
  it('builds uppercase initials from the first and last name', () => {
    expect(getMemberInitials({ first_name: 'anna', last_name: 'beck' })).toBe('AB')
  })

  it('handles partial and missing names', () => {
    expect(getMemberInitials({ first_name: 'Anna' })).toBe('A')
    expect(getMemberInitials({})).toBe('')
    expect(getMemberInitials(null)).toBe('??')
  })
})
