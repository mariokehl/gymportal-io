import { describe, it, expect } from 'vitest'
import {
  aggregatorBadgeStyle, aggregatorStatusLabel, findAggregator,
} from '@/utils/aggregators'

describe('findAggregator', () => {
  it('finds a provider by key', () => {
    expect(findAggregator('hansefit')?.name).toBe('Hansefit')
  })

  it('returns null for unknown or empty keys', () => {
    expect(findAggregator('unknown')).toBeNull()
    expect(findAggregator(null)).toBeNull()
  })
})

describe('aggregatorStatusLabel', () => {
  it('labels linked and pending accounts', () => {
    expect(aggregatorStatusLabel('linked')).toBe('Verknüpft')
    expect(aggregatorStatusLabel('pending')).toBe('Verknüpfung ausstehend')
  })
})

describe('aggregatorBadgeStyle', () => {
  const wellpass = findAggregator('wellpass')

  it('fills the badge once the account is linked', () => {
    expect(aggregatorBadgeStyle(wellpass, 'linked')).toEqual({
      background: wellpass.bg, color: wellpass.fg, border: `1px solid ${wellpass.bg}`,
    })
  })

  it('draws a dashed outline while the link is pending', () => {
    expect(aggregatorBadgeStyle(wellpass, 'pending').border).toBe(`1px dashed ${wellpass.border}`)
    const style = aggregatorBadgeStyle(wellpass, 'pending')
    // Transparent so the badge works on light and dark surfaces
    expect(style.background).toBe('transparent')
    expect(style.color).toBe('light-dark(#155e75, #22d3ee)')
  })

  it('returns no style without a provider', () => {
    expect(aggregatorBadgeStyle(null, 'linked')).toEqual({})
  })
})
