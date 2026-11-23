import { describe, it, expect } from 'vitest'
import { averageRowHeight } from './skeletonSize'

const container = height => ({ getBoundingClientRect: () => ({ height }) })

describe('averageRowHeight', () => {
  it('divides the rendered height by the number of rows', () => {
    expect(averageRowHeight(container(570), 10)).toBe(57)
  })

  it('returns null for a hidden container', () => {
    expect(averageRowHeight(container(0), 10)).toBeNull()
  })

  it('returns null without rows or container', () => {
    expect(averageRowHeight(container(570), 0)).toBeNull()
    expect(averageRowHeight(null, 10)).toBeNull()
  })
})
