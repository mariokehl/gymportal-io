import { describe, it, expect } from 'vitest'
import {
  aggregators, aggregatorBadgeStyle, aggregatorFilterOptions, aggregatorStatusLabel, findAggregator,
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

describe('aggregatorFilterOptions', () => {
  it('lists the generic options before every provider', () => {
    const values = aggregatorFilterOptions().map(option => option.value)

    expect(values.slice(0, 3)).toEqual(['', 'any', 'none'])
    expect(values.slice(3)).toEqual(aggregators.map(aggregator => aggregator.key))
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
    expect(aggregatorBadgeStyle(wellpass, 'pending').background).toBe('#ffffff')
  })

  it('returns no style without a provider', () => {
    expect(aggregatorBadgeStyle(null, 'linked')).toEqual({})
  })
})
