import { describe, it, expect } from 'vitest'
import { membershipStatusOption } from '@/utils/membershipStatus'

describe('membershipStatusOption', () => {
  it('returns the label and badge of a known status', () => {
    expect(membershipStatusOption('pending')).toMatchObject({
      label: 'Ausstehend',
      badgeClass: 'bg-orange-100 text-orange-800'
    })
  })

  it('falls back to the raw value on a neutral badge', () => {
    expect(membershipStatusOption('unknown')).toMatchObject({
      label: 'unknown',
      badgeClass: 'bg-gray-100 text-gray-800'
    })
  })
})
